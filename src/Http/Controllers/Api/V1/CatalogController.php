<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Padosoft\PriceIntelligence\Data\ProductData;
use Padosoft\PriceIntelligence\Http\Requests\BulkUpsertProductsRequest;
use Padosoft\PriceIntelligence\Http\Resources\ProductResource;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Services\Catalog\CatalogImporter;
use Padosoft\PriceIntelligence\Services\Catalog\CsvCatalogReader;

final class CatalogController
{
    public function __construct(
        private readonly CatalogImporter $importer,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($request->filled('brand')) {
            $query->where('brand', $request->string('brand')->toString());
        }

        if ($request->filled('gtin')) {
            $query->where('gtin', $request->string('gtin')->toString());
        }

        $products = $query->orderByDesc('id')->cursorPaginate(
            perPage: (int) $request->integer('per_page', 50),
        );

        return ProductResource::collection($products)->response();
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::query()->findOrFail($id);

        return (new ProductResource($product))->response();
    }

    public function bulkUpsert(BulkUpsertProductsRequest $request): JsonResponse
    {
        $rows = array_map(
            static fn (array $row): ProductData => ProductData::fromArray($row),
            $request->validated()['products'],
        );

        $result = $this->importer->importInTransaction($rows);

        return response()->json(['data' => $result], 200);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        $path = $request->file('file')->getRealPath();

        abort_if($path === false, 422, 'Unable to read the uploaded file.');

        $result = $this->importer->importInTransaction((new CsvCatalogReader())->read($path));

        return response()->json(['data' => $result], 200);
    }

    public function destroy(int $id): Response
    {
        $product = Product::query()->findOrFail($id);
        $product->delete();

        return response()->noContent();
    }
}
