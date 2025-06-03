<?php

namespace App\Services\ProductStock;

use App\Enums\Product\HasSubUnit;
use App\Models\Product\Product;
use App\Models\Stock\ProductStock;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductStockService
{
    public function allProductStocks(): LengthAwarePaginator
    {
        $perPage = request()->get('pageSize', 10);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        // Step 1: Paginate distinct product_ids
        $productIdsQuery = ProductStock::select('product_id')
            ->groupBy('product_id')
            ->orderBy('product_id');

        $total = $productIdsQuery->get()->count(); // Total grouped products
        $productIds = $productIdsQuery
            ->skip(($currentPage - 1) * $perPage)
            ->take($perPage)
            ->pluck('product_id');

        // Step 2: Fetch related ProductStocks only for paginated product_ids
        $productStocks = ProductStock::with('product')
            ->whereIn('product_id', $productIds)
            ->get()
            ->groupBy('product_id');

        // Step 3: Build structured product-wise data
        $grouped = $productStocks->map(function ($stocks, $productId) {
            $firstStock = $stocks->first();
            $product = $firstStock->product;


            return [
                'productId' => $firstStock->id,
                'productName' => $product->name,
                'serialNumber' => $product->serial_number ?? '',
                'barCode' => $product->bar_code ?? '',
                'minQuantity' => $product->min_quantity,
                'hasSubUnit' => $product->has_sub_unit,
                'media' => $product->main_media->path ?? '',
                'totalQuantity' => $stocks->sum('unitQuantity'),
                'totalSubQuantity' => $stocks->sum('subUnitQuantity'),
                'totalCost' => $stocks->sum('totalCost'),
                'stocks' => $stocks->map(function ($stock) {
                    return [
                        'productStockId' => $stock->id,
                        'quantity' => $stock->unitQuantity,
                        'subQuantity' => $stock->subUnitQuantity,
                        'cost' => $stock->unitCost,
                        'subUnitCost' => $stock->subUnitCost,
                        'totalCost' => $stock->totalCost,
                    ];
                })->values()
            ];
        })->values();

        // Step 4: Paginate result
        return new LengthAwarePaginator(
            $grouped,
            $total,
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }


    /*public function editProductStock(int $id)
    {
        return ProductStock::find($id);
    }
    public function createProductStock($id, array $data)
    {

        $qunatity = $data['quantity'];

        $product = Product::find($data['productId']);

        if($product->has_sub_unit == HasSubUnit::YES && $data['isSubUnit'] == HasSubUnit::NO){
            $qunatity = $qunatity * $product->sub_unit_conversion_rate;
        }

        $productStock = ProductStock::create([
            'product_id'=>$product->id,
            'quantity'=> $qunatity,
            'purchase_invoice_item_id'=> $data['productStockId']??null
        ]);

        return $productStock;
    }

    public function updateProductStock(int $id, array $data)
    {
        $qunatity = $data['quantity'];

        $product = Product::find($data['productId']);

        if($product->has_sub_unit == HasSubUnit::YES && $data['isSubUnit'] == HasSubUnit::NO){
            $qunatity = $qunatity * $product->sub_unit_conversion_rate;
        }

        $productStock = ProductStock::find($id);

        $productStock->update([
            'quantity'=> $qunatity,
            'product_id'=>$product->id
        ]);

        return $productStock;
    }
    public function deleteProductStock($id): void
    {
        ProductStock::find($id)->delete();
    }*/
}
