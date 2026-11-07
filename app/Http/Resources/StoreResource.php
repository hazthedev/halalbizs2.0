<?php

namespace App\Http\Resources;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * The seller's own store (mobile plan §4). The bank account number is masked
 * to its last four digits; the full number never leaves the server.
 *
 * @mixin Store
 */
class StoreResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $bank = $this->bank_details ?? [];
        $accountNo = (string) ($bank['account_number'] ?? '');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'state' => $this->state,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'holiday_mode' => $this->holiday_mode,
            'logo' => $this->getFirstMediaUrl('logo', 'thumb') ?: null,
            'banner' => $this->getFirstMediaUrl('banner', 'card') ?: null,
            'shipping' => [
                'mode' => $this->shipping_mode ?? 'flat',
                'flat_fee_sen' => $this->shipping_flat_fee_sen,
                'free_above_sen' => $this->free_shipping_over_sen,
                'matrix' => $this->shipping_matrix,
            ],
            'bank' => $bank === [] ? null : [
                'bank_name' => $bank['bank_name'] ?? null,
                'account_name' => $bank['account_name'] ?? null,
                'account_no' => $accountNo === '' ? null : Str::mask($accountNo, '*', 0, -4),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
