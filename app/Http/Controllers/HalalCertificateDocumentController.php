<?php

namespace App\Http\Controllers;

use App\Models\HalalCertificate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HalalCertificateDocumentController extends Controller
{
    /**
     * Stream a seller's uploaded halal certificate to a reviewing admin.
     *
     * Same shape as StoreDocumentController: the scan lives on the private
     * disk and this is the ONLY way to read it. Authorization is the route's
     * EnsureAdmin + can:certificates.manage — it is never linked from the shop
     * or from the public register, which shows the RECORD, not the document.
     */
    public function show(Request $request, HalalCertificate $halalCertificate): StreamedResponse
    {
        $media = $halalCertificate->getFirstMedia('document');

        abort_unless($media !== null, 404);

        return $media->toInlineResponse($request);
    }
}
