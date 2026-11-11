<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HalalCertificate;
use Illuminate\Http\JsonResponse;

/** The public certificate register as JSON: exactly the record the web page shows. */
class CertificateController extends Controller
{
    public function show(string $number): JsonResponse
    {
        $cert = HalalCertificate::forRegister($number);
        abort_if($cert === null, 404);

        return ApiResponse::ok([
            'number' => $cert->number,
            'valid' => $cert->isValid(),
            'days_remaining' => $cert->daysRemaining(),
            'issuing_body' => $cert->issuing_body,
            'issuing_body_name' => $cert->issuing_body_name,
            'holder_name' => $cert->holder_name,
            'holder_state' => $cert->store?->state,
            'scheme' => $cert->scheme,
            'scope_note' => $cert->scope_note,
            'valid_from' => $cert->valid_from->toDateString(),
            'valid_to' => $cert->valid_to->toDateString(),
            'facility' => $cert->facility,
            'products_count' => $cert->products_count,
            'dedicated_facility' => (bool) $cert->dedicated_facility,
            'export_paperwork' => (bool) $cert->export_paperwork,
            'store_slug' => $cert->store?->slug,
            'events' => $cert->events->map(fn ($e) => [
                'occurred_on' => $e->occurred_on->toDateString(),
                'summary' => $e->summary,
            ])->values()->all(),
        ]);
    }
}
