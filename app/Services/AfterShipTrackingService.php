<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\ShipmentTrackingStatus;
use App\Enums\SubOrderStatus;
use App\Models\SubOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

class AfterShipTrackingService
{
    public function configured(): bool
    {
        return config('tracking.provider') === 'aftership'
            && filled(config('tracking.aftership.api_key'));
    }

    /**
     * Register one shipped parcel with AfterShip. Safe to call repeatedly:
     * an existing provider id makes this a no-op.
     */
    public function register(SubOrder $subOrder): bool
    {
        if (! $this->configured()
            || blank($subOrder->tracking_no)
            || filled($subOrder->tracking_provider_id)) {
            return false;
        }

        $response = $this->client()->post('/trackings', [
            'tracking' => [
                'tracking_number' => $subOrder->tracking_no,
                'title' => $subOrder->sub_order_no,
                'order_id' => $subOrder->sub_order_no,
                'order_number' => $subOrder->order?->order_no,
                'tracking_ship_date' => $subOrder->shipped_at?->toIso8601String(),
            ],
        ])->throw();

        $tracking = $response->json('data.tracking')
            ?? $response->json('value.data.tracking')
            ?? $response->json('tracking')
            ?? $response->json('data');

        if (! is_array($tracking) || blank($tracking['id'] ?? null)) {
            throw new UnexpectedValueException('AfterShip registration response did not contain a tracking id.');
        }

        $subOrder->forceFill([
            'tracking_provider' => 'aftership',
            'tracking_provider_id' => (string) $tracking['id'],
            'tracking_status' => ShipmentTrackingStatus::fromAfterShip($tracking['tag'] ?? null),
            'tracking_url' => $this->safeUrl($tracking['courier_tracking_link'] ?? null),
        ])->save();

        return true;
    }

    /**
     * Store a signed AfterShip webhook as normalized, idempotent checkpoints.
     * Returns null for non-tracking events and unknown shipments.
     */
    public function ingest(array $payload): ?SubOrder
    {
        if (($payload['event'] ?? null) !== 'tracking_update') {
            return null;
        }

        $message = $payload['msg'] ?? [];
        $tracking = is_array($message) && is_array($message['tracking'] ?? null)
            ? $message['tracking']
            : $message;

        if (! is_array($tracking)) {
            return null;
        }

        $subOrder = $this->findSubOrder($tracking);

        if ($subOrder === null) {
            Log::info('AfterShip webhook for unknown tracking.', [
                'event_id' => $payload['event_id'] ?? null,
                'provider_id' => $tracking['id'] ?? null,
                'tracking_number' => $tracking['tracking_number'] ?? null,
            ]);

            return null;
        }

        return DB::transaction(function () use ($subOrder, $tracking) {
            $latestAt = $subOrder->tracking_last_event_at;

            foreach (($tracking['checkpoints'] ?? []) as $checkpoint) {
                if (! is_array($checkpoint)) {
                    continue;
                }

                $status = ShipmentTrackingStatus::fromAfterShip($checkpoint['tag'] ?? null);
                $occurredAt = $this->checkpointTime($checkpoint);
                $externalId = filled($checkpoint['hash'] ?? null)
                    ? (string) $checkpoint['hash']
                    : hash('sha256', json_encode([
                        $tracking['id'] ?? $tracking['tracking_number'] ?? '',
                        $checkpoint['checkpoint_time'] ?? $checkpoint['created_at'] ?? '',
                        $checkpoint['tag'] ?? '',
                        $checkpoint['subtag'] ?? '',
                        $checkpoint['message'] ?? '',
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                $subOrder->trackingEvents()->firstOrCreate(
                    ['provider' => 'aftership', 'external_id' => $externalId],
                    [
                        'status' => $status,
                        'raw_status' => $checkpoint['subtag'] ?? $checkpoint['raw_tag'] ?? null,
                        'message' => $checkpoint['message'] ?? $checkpoint['subtag_message'] ?? $status->value,
                        'location' => $this->checkpointLocation($checkpoint),
                        'occurred_at' => $occurredAt,
                    ],
                );

                if ($latestAt === null || $occurredAt->isAfter($latestAt)) {
                    $latestAt = $occurredAt;
                }
            }

            $status = ShipmentTrackingStatus::fromAfterShip($tracking['tag'] ?? null);

            $subOrder->forceFill([
                'tracking_provider' => 'aftership',
                'tracking_provider_id' => $tracking['id'] ?? $subOrder->tracking_provider_id,
                'tracking_status' => $status,
                'tracking_url' => $this->safeUrl($tracking['courier_tracking_link'] ?? null) ?? $subOrder->tracking_url,
                'tracking_last_event_at' => $latestAt,
            ])->save();

            if ($status === ShipmentTrackingStatus::Delivered
                && $subOrder->status === SubOrderStatus::Shipped) {
                $subOrder = app(OrderService::class)->markDelivered($subOrder, ActorType::System);
            }

            return $subOrder->fresh(['trackingEvents']);
        });
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('tracking.aftership.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders(['as-api-key' => (string) config('tracking.aftership.api_key')])
            ->timeout((int) config('tracking.aftership.timeout', 10));
    }

    /** @param array<string, mixed> $tracking */
    private function findSubOrder(array $tracking): ?SubOrder
    {
        if (filled($tracking['id'] ?? null)) {
            $match = SubOrder::query()
                ->where('tracking_provider', 'aftership')
                ->where('tracking_provider_id', $tracking['id'])
                ->first();

            if ($match !== null) {
                return $match;
            }
        }

        if (filled($tracking['order_id'] ?? null)) {
            $query = SubOrder::query()->where('sub_order_no', $tracking['order_id']);

            if (filled($tracking['tracking_number'] ?? null)) {
                $query->where('tracking_no', $tracking['tracking_number']);
            }

            if (($match = $query->first()) !== null) {
                return $match;
            }
        }

        if (blank($tracking['tracking_number'] ?? null)) {
            return null;
        }

        $matches = SubOrder::query()
            ->where('tracking_no', $tracking['tracking_number'])
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @param array<string, mixed> $checkpoint */
    private function checkpointTime(array $checkpoint): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($checkpoint['checkpoint_time'] ?? $checkpoint['created_at'] ?? 'now');
        } catch (Throwable) {
            return CarbonImmutable::now();
        }
    }

    /** @param array<string, mixed> $checkpoint */
    private function checkpointLocation(array $checkpoint): ?string
    {
        $parts = array_filter([
            $checkpoint['city'] ?? null,
            $checkpoint['state'] ?? null,
            $checkpoint['country_region_name'] ?? $checkpoint['country_name'] ?? null,
        ], fn ($value) => is_string($value) && trim($value) !== '');

        return $parts === [] ? null : implode(', ', array_unique($parts));
    }

    private function safeUrl(mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true) ? $value : null;
    }
}
