<?php
namespace App\Services\Product;

use App\Enums\IsActive;
use App\Models\Product\ProductPrice;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
class ProductPriceService
{
    public function allProductPrices(){

        $perPage = request()->get('pageSize', 10);

        $productPrices = QueryBuilder::for(ProductPrice::class)
            ->allowedFilters([
                AllowedFilter::exact('productId', 'product_id'),
            ])
            ->paginate($perPage); // Pagination applied here


        return $productPrices;
    }
    public function createProductPrice(array $data): productPrice
    {
        $productPrice= productPrice::create([
            'cost'=>$data['cost'],
            'unit_price'=>$data['unitPrice'],
            'sub_unit_price'=>$data['subUnitPrice'],
            'start_at'=>$data['startAt'],
            'is_active'=>IsActive::from($data['isActive'])->value,
            'product_id'=>$data['productId'],
        ]);

        return $productPrice;
    }
    public function editProductPrice(int $id){
        return ProductPrice::find($id);
    }
    public function updateProductPrice(int $id, array $data){
        $productPrice= ProductPrice::find($id);

        $productPrice->update([
            'cost'=>$data['cost'],
            'unit_price'=>$data['unitPrice'],
            'sub_unit_price'=>$data['subUnitPrice'],
            'start_at'=>$data['startAt'],
            'is_active'=>IsActive::from($data['isActive'])->value,
        ]);

        return $productPrice;

    }
    public function deleteProductPrice(int $id): void
    {
        ProductPrice::findOrFail($id)->delete();
    }

}
