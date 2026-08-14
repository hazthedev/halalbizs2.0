<?php

namespace App\Observers;

use App\Models\HalalCertificate;
use App\Models\Product;
use RuntimeException;

/**
 * A product may only cite its OWN store's halal certificate (audit H-6).
 *
 * `halal_certificate_id` sits in Product::$fillable with no validation
 * anywhere, and until the seller screen shipped nothing wrote it but the demo
 * seeders — so the hole was theoretical. The audit named it precisely because
 * the day a UI shipped without this check, seller A could cite seller B's
 * JAKIM certificate and wear a verified badge on the strength of it.
 *
 * It lives on the model rather than in the form because the form is not the
 * only writer: the bulk importer, a future API, an admin tool and any seeder
 * all reach the same column. A rule enforced at one call site is not an
 * invariant — this is the point every writer passes through.
 *
 * Throws rather than nulling: silently dropping the binding would leave a
 * seller staring at a product that will not badge, with nothing said.
 */
class HalalCertificateBindingObserver
{
    public function saving(Product $product): void
    {
        if (! $product->isDirty('halal_certificate_id') || $product->halal_certificate_id === null) {
            return;
        }

        $certificate = HalalCertificate::query()
            ->whereKey($product->halal_certificate_id)
            ->first(['id', 'store_id']);

        if ($certificate === null) {
            throw new RuntimeException("Halal certificate {$product->halal_certificate_id} does not exist.");
        }

        // store_id can itself be dirty on a create, so read it off the product
        // being saved rather than a relation that may not be loaded yet.
        if ((int) $certificate->store_id !== (int) $product->store_id) {
            throw new RuntimeException(
                "Halal certificate {$certificate->id} belongs to store {$certificate->store_id}, not store {$product->store_id}."
            );
        }
    }
}
