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
        //$auth->role['name']
        $data = request();
        $pageSize = (int) $data['pageSize'];
        $page = (int) $data['page'] ?? 1;
        $offset = ($page - 1) * $pageSize;

        $sql = "SELECT id AS productId, `name`, `description`
                FROM products 
                WHERE deleted_at IS NULL 
                LIMIT $pageSize OFFSET $offset";

        $products = DB::raw($sql);

        $prductsData = [];

        foreach ($products as $product) {

            $productRole = DB::raw(
                "SELECT role_id, period_id, quantity
                FROM role_product
                WHERE deleted_at IS NULL AND product_id = ? AND role_id = ?", [
                    $product['productId'],
                    $auth->role['id']
                ]
            );

            if(empty($productRole)) {
                continue;
            }

            // Step 1: Get product codes
            $productCodes = DB::select(
                "SELECT id AS productCodeId
                FROM product_codes 
                WHERE deleted_at IS NULL AND product_id = ?",
                [$product['productId']]
            );

            // Step 2: Extract IDs as array
            $productCodeIds = array_map(fn($row) => is_array($row) ? $row['productCodeId'] : $row->productCodeId, $productCodes);

            if (empty($productCodeIds)) {
                $productStockSum = 0;
            } else {
                // Step 3: Build placeholders
                $placeholders = implode(',', array_fill(0, count($productCodeIds), '?'));

                // Step 4: Use DB::select() directly (not DB::raw())
                $sql = "SELECT SUM(quantity) AS total
                        FROM stocks
                        WHERE deleted_at IS NULL
                        AND product_code_id IN ($placeholders)";
                $result = DB::select($sql, $productCodeIds);
                $productStockSum = $result[0]['total'] ?? 0;
            }

            $prductsData[] = [
                'productId' => $product['productId'],
                'name' => $product['name'],
                'description' => $product['description'] ?? '',
                'totalStock' => (int)$productStockSum
            ];
        }

        $productsCount = DB::raw("SELECT count(*) as total FROM products WHERE deleted_at IS NULL");

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
    }
    public function store()
    {

        try {
            $data = request();

            DB::beginTransaction();

            $product = DB::raw("INSERT INTO `products` (`name`, `description`, `min_quantity`) VALUES (?, ?, ?)", [$data['name'], $data['description'], $data['minQuantity']], false);

            foreach ($data['productCodes'] as $key => $productCodeData) {

                DB::raw("INSERT INTO `product_codes` (`product_id`, `code`) VALUES (?, ?)", [$product, $productCodeData['code']], false);
            }

            foreach ($data['roleProducts'] as $key => $roleProduct) {

                DB::raw("INSERT INTO `role_product` (`product_id`, `role_id`, `period_id`, `quantity`) VALUES (?, ?, ?, ?)", [$product, $roleProduct['roleId'], $roleProduct['periodId'], $roleProduct['quantity']]);

            }


            DB::commit();

            return ApiResponse::success('Product created successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

    public function show($id){

        $productData = DB::raw("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL", [$id]);

        $productCodesData = DB::raw("SELECT id as productCodeId, code as code FROM product_codes WHERE product_id = ?", [$id]);

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
        return ApiResponse::success('Order deleted successfully');
    }

}
