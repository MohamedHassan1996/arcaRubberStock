<?php
namespace App\Services\Product;

use App\Enums\Product\HasSubUnit;
use App\Enums\Product\ProductStatus;
use App\Filters\Product\FilterProduct;
use App\Models\Product\Product;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\ProductMedia\ProductMediaService;
use Spatie\QueryBuilder\AllowedFilter;

class ProductService
{
    public  $productMediaService;
    public function __construct(ProductMediaService $productMediaService)
    {
        $this->productMediaService = $productMediaService;
    }
    public function allProducts(){

        $perPage = request()->get('pageSize', 10);

        $products = QueryBuilder::for(Product::class)
            ->allowedFilters([
                AllowedFilter::custom('search', new FilterProduct()),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('categoryId', 'category_id'),
                AllowedFilter::exact('subCategoryId', 'sub_category_id')
            ])
            ->paginate($perPage); // Pagination applied here


        return $products;
    }
    public function createProduct(array $data): Product
    {

        $product= Product::create([
            'name'=>$data['name'],
            'serial_number'=>$data['serialNumber']??null,
            'min_quantity'=>$data['minQuantity'],
            'has_sub_unit'=>HasSubUnit::from($data['hasSubUnit']),
            'sub_unit_conversion_rate'=>$data['subUnitConversionRate']??0,
            'status'=> ProductStatus::from($data['status']),
            'description'=>$data['description']??null,
            // 'price'=>$data['price']??0,
            // 'sub_unit_price'=>$data['subUnitPrice']??null,
            'category_id'=>$data['categoryId']??null,
            'sub_category_id'=>$data['subCategoryId']??null,
        ]);

        foreach($data['productMedia'] as $media){
            $this->productMediaService->createProductMedia([
                'productId' => $product->id,
                ...$media
            ]);
        }

       /* if($data['quantity'] > 0){

            $quantity = $product->has_sub_unit == HasSubUnit::YES ? $data['quantity'] * $product->sub_unit_conversion_rate : $data['quantity'];

            $cost = $data['cost'] / $quantity;

           $inStock = InStock::create([
                'date'=>now(),
                'supplier_id'=>null,
                'status'=>InStockStatus::APPROVED->value,
                'type'=>InStockType::IN_STOCK->value,
                'note' => null,
                'supplier_in_stock_number' => null,
            ]);


            $inStockItem = InStockItem::create([
                'in_stock_id' => $inStock->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'is_sub_unit' => $product->has_sub_unit,
                'cost' => $cost
            ]);

            $stockCost = $product->has_sub_unit == HasSubUnit::YES  && $? $cost * $product->sub_unit_conversion_rate : $cost;

            ProductStock::create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'in_stock_item_id' => $inStockItem->id,
                'cost' => $stockCost
            ]);


        }*/

        return $product;
    }
    public function editProduct(int $id){
        return Product::with(['productMedia'])->find($id);
    }
    public function updateProduct(int $id, array $data){
        $product= Product::find($id);

        $product->update([
            'name'=>$data['name'],
            'serial_number'=>$data['serialNumber']??"",
            'min_quantity'=>$data['minQuantity'],
            'has_sub_unit'=>HasSubUnit::from($data['hasSubUnit']),
            'sub_unit_conversion_rate'=>$data['subUnitConversionRate']??0,
            'status'=> ProductStatus::from($data['status']),
            'description'=>$data['description']??null,
            // 'price'=>$data['price']??0,
            // 'sub_unit_price'=>$data['subUnitPrice']??null,
            'category_id'=>$data['categoryId']??null,
            'sub_category_id'=>$data['subCategoryId']??null,
        ]);

        return $product;

    }
    public function deleteProduct(int $id): void
    {
        Product::findOrFail($id)->delete();
    }

}
