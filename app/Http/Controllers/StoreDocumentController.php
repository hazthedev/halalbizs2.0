<?php

namespace App\Http\Controllers;

use App\Models\StoreDocument;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoreDocumentController extends Controller
{
    /**
     * Stream a seller's KYC document (IC / SSM) to a reviewing admin.
     *
     * The file lives on the private disk; this is the ONLY way to read it.
     * Authorization is the route's `EnsureAdmin` + `can:sellers.manage` gate
     * (same as the seller-application screens), so a guest/buyer/seller can't
     * reach it and the old enumerable public /storage URL no longer exists.
     */
    public function show(Request $request, StoreDocument $storeDocument): StreamedResponse
    {
        $media = $storeDocument->getFirstMedia('file');

        abort_unless($media !== null, 404);

        return $media->toInlineResponse($request);
    }
}
