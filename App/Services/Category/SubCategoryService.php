<?php

namespace App\Services\Category;

use App\Enums\Product\CategoryStatus;
use App\Models\Product\Category;
use App\Services\Upload\UploadService;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SubCategoryService{

    protected $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    public function allSubCategories()
    {
        $subCategories = QueryBuilder::for(Category::class)
        ->allowedFilters([
            AllowedFilter::exact('categoryId', 'parent_id')
        ])
        ->SubCategories()
        ->get();

        return $subCategories;

    }

    public function createSubCategory(array $subCategoryData)
    {

        $path = isset($subCategoryData['path'])? $this->uploadService->uploadFile($subCategoryData['path'], 'categories'):null;
        $subCategory = Category::create([
            'name' => $subCategoryData['name'],
            'is_active' => CategoryStatus::from($subCategoryData['isActive'])->value,
            'path' => $path,
            'parent_id' => $subCategoryData['categoryId'],
        ]);

        return $subCategory;

    }

    public function editSubCategory($SubCategoryId)
    {
        return Category::find($SubCategoryId);
    }

    public function updateSubCategory($id,array $subCategoryData)
    {


        $subCategory = Category::find($id);

        if(isset($subCategoryData['path'])){
            if ($subCategory->path) {
                Storage::disk('public')->delete($subCategory->getRawOriginal('path'));
            }
            $subCategory->path = $this->uploadService->uploadFile($subCategoryData['path'], 'categories');
        }
        $subCategory->name = $subCategoryData['name'];
        $subCategory->is_active = CategoryStatus::from($subCategoryData['isActive'])->value;
        $subCategory->parent_id = $subCategoryData['categoryId']??null;
        $subCategory->save();

        return $subCategory;

    }


    public function deleteSubCategory($SubCategoryId)
    {

        Category::find($SubCategoryId)->delete();

    }

}
