<?php

namespace App\Mcp\Tools;

use App\Models\Product;
use App\Settings\GeneralSettings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[IsReadOnly]
class FindHalalProduct extends Tool
{
    protected string $name = 'find_halal_product';

    protected string $description = 'Find up to 20 certified products. Supply either a text query or an exact barcode (HalalBizs variant SKU). Returns public names and listing URLs only.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->min(2)->max(200)->description('Product, brand or store search text. Supply this OR barcode.'),
            'barcode' => $schema->string()->min(1)->max(100)->description('Exact barcode, including leading zeros. Supply this OR query.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if (! app(GeneralSettings::class)->mcp_public_enabled) {
            return Response::error('Public halal tools are disabled by the administrator.');
        }

        $data = $request->validate([
            'query' => ['required_without:barcode', 'prohibits:barcode', 'string', 'min:2', 'max:200'],
            'barcode' => ['required_without:query', 'prohibits:query', 'string', 'min:1', 'max:100'],
        ]);

        try {
            $query = Product::query()->live()->with(['halalCertificate', 'store']);
            if (isset($data['barcode'])) {
                $query->whereHas('variants', fn ($variant) => $variant->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower(trim($data['barcode']))]));
            } else {
                $query->keywordSearch($data['query']);
            }

            $products = [];
            // Filter before the result cap: pending rows must not hide later verified matches.
            foreach ($query->lazyById(100) as $product) {
                if ($product->halalVerdict() !== 'verified') {
                    continue;
                }
                $products[] = [
                    'name' => $product->name, 'store_name' => $product->store->name,
                    'url' => route('product.show', $product->slug),
                ];
                if (count($products) === 20) {
                    break;
                }
            }

            return Response::structured(['products' => $products]);
        } catch (Throwable) {
            return Response::error('Product search is unavailable. Please try again later.');
        }
    }
}
