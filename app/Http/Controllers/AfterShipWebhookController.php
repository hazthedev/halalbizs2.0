<?php

namespace App\Http\Controllers;

use App\Services\AfterShipTrackingService;
use Illuminate\Http\Request;

class AfterShipWebhookController extends Controller
{
    public function tracking(Request $request, AfterShipTrackingService $tracking)
    {
        $secret = (string) config('tracking.aftership.webhook_secret');
        $provided = (string) $request->header('aftership-hmac-sha256');

        if ($secret === '' || $provided === '') {
            abort(401);
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        if (! hash_equals($expected, $provided)) {
            abort(401);
        }

        $tracking->ingest($request->json()->all());

        return response('OK');
    }
}
