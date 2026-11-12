<?php

namespace App\Mcp\Tools;

use App\Models\HalalCertificate;
use App\Models\Store;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SellerCertificates extends InternalTool
{
    protected string $name = 'seller_certificates';

    protected string $description = 'Admin only. Read a store certificate list, review status, manual/WHIP source, sync time and unmatched products. Pass the store slug or ID as a string.';

    public function schema(JsonSchema $schema): array
    {
        return ['store' => $schema->string()->required()->max(255)];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate(['store' => ['required', 'string', 'max:255']]);
        $key = trim($data['store']);
        $store = ctype_digit($key) ? Store::find($key) : null;
        $store ??= Store::where('slug', $key)->first();

        if ($store === null) {
            return Response::error('Store not found.');
        }

        return Response::structured([
            'certificates' => HalalCertificate::where('store_id', $store->id)->orderBy('id')->get()
                ->map(fn (HalalCertificate $certificate) => [
                    'certificate_no' => $certificate->number,
                    'status' => $certificate->status->value,
                    'source' => $certificate->source,
                    'whip_synced_at' => $certificate->whip_synced_at?->toIso8601String(),
                    'unmatched_products' => $certificate->unmatchedProducts()
                        ->map(fn ($product) => ['id' => $product->id, 'name' => $product->name])->all(),
                ])->all(),
        ]);
    }
}
