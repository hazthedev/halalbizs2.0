<?php

namespace App\Services\EInvoice;

use App\Enums\EInvoiceStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LHDN MyInvois (Malaysia) driver — IRBM SDK (sdk.myinvois.hasil.gov.my).
 *
 * The platform authenticates as an Intermediary (OAuth2 client-credentials,
 * onbehalfof the supplier TIN), submits a SIGNED UBL2.1/JSON document, then
 * polls for the validation result (UIN + long-id → validation QR URL). Live
 * filing needs Intermediary credentials + a digital-signature certificate
 * (authorised dependency, docs/10 cutover). Until those env values exist this
 * driver is inert and AppServiceProvider falls back to NullProvider.
 *
 * NOTE: the precise UBL field mapping + XAdES signature are finalised against
 * the current SDK at onboarding — kept behind this single class so nothing
 * else in the app changes when it lands.
 */
class MyInvoisProvider implements EInvoiceProvider
{
    /** @param  array<string, mixed>  $config  config('einvoice.myinvois') */
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'myinvois';
    }

    public function configured(): bool
    {
        return ! empty($this->config['client_id']) && ! empty($this->config['client_secret']);
    }

    public function submit(array $document): EInvoiceResult
    {
        if (! $this->configured()) {
            return EInvoiceResult::pending('MyInvois credentials not configured.');
        }

        try {
            $token = $this->accessToken();

            $response = Http::withToken($token)
                ->baseUrl(rtrim((string) $this->config['base_url'], '/'))
                ->acceptJson()
                ->post('/api/v1.0/documentsubmissions', [
                    'documents' => [$this->signed($document)],
                ]);

            if ($response->failed()) {
                return EInvoiceResult::failed('MyInvois submission HTTP '.$response->status().': '.$response->body());
            }

            $body = $response->json();
            $accepted = $body['acceptedDocuments'][0] ?? null;

            if ($accepted === null) {
                return EInvoiceResult::failed('MyInvois rejected the document: '.json_encode($body['rejectedDocuments'] ?? $body));
            }

            $uin = $accepted['uuid'] ?? null;

            return new EInvoiceResult(
                status: EInvoiceStatus::Submitted,
                submissionUid: $body['submissionUid'] ?? null,
                uin: $uin,
                validationUrl: $uin !== null ? $this->validationUrl($uin, $document['longId'] ?? '') : null,
            );
        } catch (ConnectionException $e) {
            // M-13: a TRANSPORT failure is not a rejection. Returning
            // EInvoiceResult::failed() here made a network blip indistinguishable
            // from LHDN saying no — and the document row then wrote itself into a
            // terminal `failed` state that every retry surface filters out
            // (issueIndividual returns the existing row regardless of status,
            // consolidate skips anything with a document). There is no retry
            // command, so that invoice was simply lost.
            //
            // Rethrowing lets the QUEUED listener retry it, which is the recovery
            // path M-12 just restored. A genuine rejection still returns failed()
            // below and stays terminal, which is correct — LHDN will not change
            // its mind on a re-send.
            Log::warning('MyInvois submission could not reach the gateway — retrying.', ['error' => $e->getMessage()]);

            throw $e;
        } catch (Throwable $e) {
            Log::error('MyInvois submission failed.', ['error' => $e->getMessage()]);

            return EInvoiceResult::failed($e->getMessage());
        }
    }

    public function cancel(string $uin, string $reason): bool
    {
        if (! $this->configured()) {
            return false;
        }

        try {
            $response = Http::withToken($this->accessToken())
                ->baseUrl(rtrim((string) $this->config['base_url'], '/'))
                ->acceptJson()
                ->put("/api/v1.0/documents/state/{$uin}/state", [
                    'status' => 'cancelled',
                    'reason' => $reason,
                ]);

            return $response->successful();
        } catch (Throwable $e) {
            Log::error('MyInvois cancellation failed.', ['uin' => $uin, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** OAuth2 client-credentials token, cached just under its lifetime. */
    private function accessToken(): string
    {
        return Cache::remember('einvoice:myinvois:token', now()->addMinutes(50), function () {
            $response = Http::asForm()
                ->baseUrl(rtrim((string) $this->config['identity_url'], '/'))
                ->post('/connect/token', array_filter([
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'scope' => 'InvoicingAPI',
                    'onbehalfof' => $this->config['on_behalf_of'] ?? null,
                ]));

            $response->throw();

            return (string) $response->json('access_token');
        });
    }

    /**
     * XAdES digital signature over the UBL document. Stubbed until the signing
     * certificate is provisioned; the document is returned unsigned so the API
     * (in non-production) can still echo validation errors during onboarding.
     */
    private function signed(array $document): array
    {
        // @todo Apply XAdES signature with the provisioned cert/key (docs/10).
        return $document;
    }

    private function validationUrl(string $uin, string $longId): string
    {
        $base = rtrim((string) $this->config['base_url'], '/');

        return "{$base}/{$uin}/share/{$longId}";
    }
}
