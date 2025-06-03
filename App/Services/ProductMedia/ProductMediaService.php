<?php
namespace App\Services\ProductMedia;

use App\Enums\IsMain;
use App\Services\Upload\UploadService;
use Illuminate\Support\Facades\Storage;
use App\Models\Product\ProductMedia;

class ProductMediaService{
    public  $uploadService;
    public function __construct(UploadService $uploadService)
    {
        $this->uploadService =$uploadService;
    }
    public function allProductMedia($productId){
        return ProductMedia::where('product_id',$productId)->get();
    }
    public function createProductMedia(array $data)
    {
        $path = $this->uploadService->uploadFile($data['path'], 'products/'.$data['productId']);
        $productMedia= ProductMedia::create([
            'path'=>$path,
            'media_type'=>$data['mediaType'],
            'is_main'=> IsMain::from($data['isMain']),
            'product_id'=>$data['productId'],
        ]);

        if($productMedia->is_main==IsMain::MAIN){
            ProductMedia::where('product_id', $productMedia->product_id)->where('id', '!=', $productMedia->id)->update(['is_main'=>IsMain::SECONDARY]);
        }

        return $productMedia;

    }

    public function editProductMedia(int $id){
        return ProductMedia::findOrFail($id);
    }

    public function updateProductMedia(int $id, array $data){

        $productMedia=ProductMedia::findOrFail($id);

        $productMedia->update([
            'is_main'=> IsMain::from($data['isMain']),
        ]);


        if($productMedia->is_main==IsMain::MAIN){
            ProductMedia::where('product_id', $productMedia->product_id)->where('id', '!=', $productMedia->id)->update(['is_main'=>IsMain::SECONDARY]);
        }

        return $productMedia;
    }

    public function deleteProductMedia(int $id): void
    {
        $productMedia=ProductMedia::find($id);
        Storage::disk('public')->delete($productMedia->getRawOriginal('path'));
        $productMedia->delete();
    }

}
