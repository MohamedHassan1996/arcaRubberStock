<?php

namespace App\Services\Category;

use App\Enums\Product\CategoryStatus;
use App\Filters\Category\FilterCategory;
use App\Models\Product\Category;
use App\Services\Upload\UploadService;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryService{

    protected $uploadService;
    protected $subCategoryService;


    public function __construct(UploadService $uploadService, SubCategoryService $subCategoryService)
    {
        $this->uploadService = $uploadService;
        $this->subCategoryService = $subCategoryService;

    }

    public function allCategories()
    {
        $categories = QueryBuilder::for(Category::class)
        ->allowedFilters([
            AllowedFilter::custom('search', new FilterCategory()),
            AllowedFilter::exact('isActive', 'is_active')
        ])
        ->mainCategories()
        ->with('subCategories')
        ->get();

        return $categories;

    }

    public function createCategory(array $categoryData): Category
    {

        $path = isset($categoryData['path'])? $this->uploadService->uploadFile($categoryData['path'], 'categories'):null;


        $category = Category::create([
            'name' => $categoryData['name'],
            'is_active' => CategoryStatus::from($categoryData['isActive'])->value,
            'path' => $path,
            'parent_id' => null,
        ]);

        if (isset($categoryData['subCategories'])) {
            foreach ($categoryData['subCategories'] as $subCategoryData) {
                $this->subCategoryService->createSubCategory([
                    'categoryId' => $category->id,
                    ...$subCategoryData
                ]);
            }
        }

        return $category;

    }

    public function editCategory(int $categoryId)
    {
        return Category::with('subCategories')->findOrFail($categoryId);
    }

    public function updateCategory(int $id,array $categoryData)
    {

        $category = Category::find($id);

        if(isset($categoryData['path'])){
            if ($category->path) {
                Storage::disk('public')->delete($category->getRawOriginal('path'));
            }
            $category->path = $this->uploadService->uploadFile($categoryData['path'], 'categories');
        }

        $category->name = $categoryData['name'];
        $category->is_active = CategoryStatus::from($categoryData['isActive'])->value;
        $category->save();

        return $category;

    }


    public function deleteCategory(int $categoryId)
    {

        Category::find($categoryId)->delete();

    }

}
