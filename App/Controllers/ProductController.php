<?php
namespace App\Controllers;

use App\Helpers\ApiResponse;
use Core\Auth;
use Core\Contracts\HasMiddleware;
use Core\Controller;
use Core\DB;
use Core\Middleware;

/**
 * Home controller
 *
 * PHP version 5.4
 */
class ProductController extends Controller implements HasMiddleware
{

    public function __construct()
    {
    }

    /**
     * Before filter
     *
     * @return void
     */
    protected function before()
    {
        //echo "(before) ";
        //return true;
    }

    /**
     * After filter
     *
     * @return void
     */
    protected function after()
    {
        echo " (after)";
    }

    /**
     * Show the index page
     *
     * @return void
     */

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('permission:all_products', ['index']),
            new Middleware('permission:store_product', ['store']),
            new Middleware('permission:show_product', ['show']),
            new Middleware('permission:update_product', ['update']),
            new Middleware('permission:destroy_product', ['destroy']),
        ];
    }

    public function index()
    {
        $auth = Auth::user();
        $data = request();
        $pageSize = 10000;//(int) $data['pageSize'];
        $page = (int) ($data['page'] ?? 1);
        $offset = ($page - 1) * $pageSize;

        $search = $data['filter']['search'] ?? '';
        $search = '%' . $search . '%';

        $searchCondition = '';
        $searchParams = [];

        if (!empty(trim($data['filter']['search'] ?? ''))) {
            $searchCondition = " AND (`name` LIKE ? OR `description` LIKE ?)";
            $searchParams = [$search, $search];
        }

        $sql = "SELECT id AS productId, `name`, `description`
                FROM products 
                WHERE deleted_at IS NULL $searchCondition
                LIMIT $pageSize OFFSET $offset";

        $products = DB::raw($sql, $searchParams);
        $prductsData = [];
        foreach ($products as $product) {

            $productRole = DB::raw(
                "SELECT role_id, period_id, quantity
                FROM role_product
                WHERE deleted_at IS NULL AND product_id = ? AND role_id = ?", [
                    $product['productId'],
                    $auth->product_role_id
                ]
            );

            if(empty($productRole) && $auth->product_role_id != null && $auth->role['name'] != 'admin') {
                continue;
            }

            $prductsData[] = [
                'productId' => $product['productId'],
                'name' => $product['name'],
                'description' => $product['description'] ?? '',
            ];
        }
        $productsCount = DB::raw("SELECT count(*) as total FROM products WHERE deleted_at IS NULL $searchCondition", $searchParams);
        $responseData = [
            'products' => $prductsData,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalInPage' => count($products),
                'total' => $productsCount[0]['total'] ?? 0
            ]
        ];
        return ApiResponse::success($responseData);

                   // Step 1: Get product codes
            // $productCodes = DB::select(
            //     "SELECT id AS productCodeId
            //     FROM product_codes 
            //     WHERE deleted_at IS NULL AND product_id = ?",
            //     [$product['productId']]
            // );

            // Step 2: Extract IDs as array
            // $productCodeIds = array_map(fn($row) => is_array($row) ? $row['productCodeId'] : $row->productCodeId, $productCodes);
    }
    public function store()
    {

        try {
            $data = request();

            DB::beginTransaction();

                // $product = DB::raw("INSERT INTO `products` (`name`, `description`, `min_quantity`) VALUES (?, ?, ?)", [$data['name'], $data['description'], $data['minQuantity']], false);

                // foreach ($data['productCodes'] as $key => $productCodeData) {

                //     $productCode = DB::raw("INSERT INTO `product_codes` (`product_id`, `code`, `description`) VALUES (?, ?, ?)", [$product, $productCodeData['code'], $productCodeData['description']], false);

                //     DB::raw("INSERT INTO `stocks` (`product_code_id`, `quantity`) VALUES (?, ?)", [$productCode, 0], false);


                // }

            // Insert product
$productId = DB::raw("
    INSERT INTO products (name, description, min_quantity)
    VALUES (?, ?, ?)
", [$data['name'], $data['description'], $data['minQuantity']], false);


// loop product codes
foreach ($data['productCodes'] as $productCodeData) {

        // 2. Fail if exists and not deleted
    $exists = DB::raw("
        SELECT 1
        FROM product_codes
        WHERE code = ? AND deleted_at IS NULL
        LIMIT 1
    ", [$productCodeData['code']], true); // true = fetch results
    

    if (!empty($exists)) {
        return ApiResponse::error("Product code '{$productCodeData['code']}' already exists.");

    }

    // 1. Try update if exists but deleted
    DB::raw("
        UPDATE product_codes
        SET description = ?, product_id = ?, deleted_at = NULL
        WHERE code = ? AND deleted_at IS NOT NULL
    ", [$productCodeData['description'], $productId, $productCodeData['code']]);

    $productCodeId = DB::raw("
        SELECT id FROM product_codes WHERE code = ?
    ", [$productCodeData['code']]);



    $productCodeId = $productCodeId[0]['id'] ?? 0;


    //debug($productId);
    // 3. Insert if not updated and not exists
    if ($productCodeId === 0) {
        $productCodeId = DB::raw("
            INSERT INTO product_codes (product_id, code, description)
            VALUES (?, ?, ?)
        ", [$productId, $productCodeData['code'], $productCodeData['description']], false);
    }


    $check = DB::raw("
        SELECT id 
        FROM stocks 
        WHERE product_code_id = $productCodeId
        LIMIT 1
    ");




    // 2. If not found → insert
    if (empty($check)) {
        DB::raw("INSERT INTO stocks (product_code_id, quantity) VALUES (?, ?)", [$productCodeId, 0]);
    }


}


            foreach ($data['roleProducts'] as $key => $roleProduct) {

                    DB::raw("INSERT INTO `role_product` (`product_id`, `role_id`, `period_id`, `quantity`) VALUES (?, ?, ?, ?)", [$productId, $roleProduct['roleId'], $roleProduct['periodId'], $roleProduct['quantity']]);

            }

            // if(empty($data['productCodes']) || $data['roleProducts'][0]['roleId'] == 99) {
            //     $allProductRoles = DB::raw("SELECT * FROM parameter_values WHERE deleted_at IS NULL AND parameter_id = ?", [2]);

            //     foreach ($allProductRoles as $role) {
            //         DB::raw("INSERT INTO `role_product` (`product_id`, `role_id`, `period_id`, `quantity`) VALUES (?, ?, ?, ?)", [$productId, $role['id'], $role['id'], 10000]);
            //     }
            // } else{
            //     foreach ($data['roleProducts'] as $key => $roleProduct) {

            //         DB::raw("INSERT INTO `role_product` (`product_id`, `role_id`, `period_id`, `quantity`) VALUES (?, ?, ?, ?)", [$productId, $roleProduct['roleId'], $roleProduct['periodId'], $roleProduct['quantity']]);

            //     }
            // }

            

            

            DB::commit();

            return ApiResponse::success('Product created successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

    public function show($id){

        $productData = DB::raw("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL", [$id]);

        $productCodesData = DB::raw("SELECT id as productCodeId, code as code, `description` as productCodeDescription FROM product_codes WHERE product_id = ?", [$id]);

        $productResponse = [
            'productId' => $id,
            'name' => $productData[0]['name'],
            'description' => $productData[0]['description'],
            'minQuantity' => $productData[0]['min_quantity'],
            'productCodes' => $productCodesData,
        ];

        return ApiResponse::success($productResponse);

    }


    public function update()
    {

        try {
            $data = request();

            DB::beginTransaction();

            $product = DB::raw("UPDATE `products` SET `name` = ?, `description` = ?, `min_quantity` = ? WHERE id = ?", [$data['name'], $data['description'], $data['minQuantity'], $data['productId']]);

            DB::commit();

            return ApiResponse::success('Product updated successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            DB::raw("UPDATE `products` SET deleted_at = ? WHERE id = ? AND deleted_at IS NULL", [date('Y-m-d H:i:s'), $id]);

            $productCode = DB::raw("UPDATE `product_codes` SET deleted_at = ? WHERE product_id = ? AND deleted_at IS NULL", [date('Y-m-d H:i:s'), $id], false);

            DB::raw("UPDATE `in_stocks` SET deleted_at = ? WHERE product_code_id = ? AND deleted_at IS NULL", [date('Y-m-d H:i:s'), $productCode]);

            DB::raw("UPDATE `stocks` SET deleted_at = ? WHERE product_code_id = ? AND deleted_at IS NULL", [date('Y-m-d H:i:s'), $productCode]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        return ApiResponse::success('Product deleted successfully');
    }

}
