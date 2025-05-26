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
            //new Middleware('permission:store_operatotr_order', ['index'])
        ];
    }

    public function index()
    {
        $data = request();
        $pageSize = (int) $data['pageSize'];
        $page = (int) $data['page'] ?? 1;
        $offset = ($page - 1) * $pageSize;

        $sql = "SELECT id AS productId, `name` 
                FROM products 
                WHERE deleted_at IS NULL 
                LIMIT $pageSize OFFSET $offset";

        $products = DB::raw($sql);

        $productsCount = DB::raw("SELECT count(*) as total FROM products WHERE deleted_at IS NULL");

        $responseData = [
            'products' => $products,
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

            $product = DB::raw("INSERT INTO `products` (`name`, `description`,`min_quantity`) VALUES (?, ?, ?)", [$data['name'], $data['description'], $data['minQuantity']], false);

            foreach ($data['productCodes'] as $key => $productCodesData) {

                $productCode = DB::raw("INSERT INTO `product_codes` (`product_id`, `code`) VALUES (?, ?)", [$product, $productCodesData['code']], false);

                $inStock = DB::raw("INSERT INTO `in_stocks` ('invoice_number', `product_code_id`, `quantity`) VALUES (?, ?, ?)", [$productCodesData['invoiceNumber'], $productCode, $productCodesData['quantity']]);

                $stock = DB::raw("INSERT INTO `stocks` (`product_code_id`, `quantity`) VALUES (?, ?)", [$productCode, $productCodesData['quantity']]);
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

            /*foreach ($data['productCodes'] as $key => $productCodesData) {
                if($productCodesData['actionStatus'] == "CREATE"){
                    $productCode = DB::raw("INSERT INTO `product_codes` (`product_id`, `code`) VALUES (?, ?)", [$product, $productCodesData['code']], false);

                    $inStock = DB::raw("INSERT INTO `in_stocks` ('invoice_number', `product_code_id`, `quantity`) VALUES (?, ?, ?)", [$productCodesData['invoiceNumber'], $productCode, $productCodesData['quantity']]);

                    $stock = DB::raw("INSERT INTO `stocks` (`product_code_id`, `quantity`) VALUES (?, ?)", [$productCode, $productCodesData['quantity']]);

                }elseif($productCodesData['actionStatus'] == "UPDATE"){

                    $productCode = DB::raw("UPDATE `product_codes` SET code = ? WHERE id = ?", [$productCodesData['code'], $productCodesData['productCodeId']]);

                    $inStockOldQuantity = DB::raw("SELECT quantity FROM `in_stocks` WHERE id = ?", [$productCodesData['inStockId']])[0]['quantity'];
                    
                    $inStock = DB::raw("UPDATE `in_stocks` SET invoice_number = ?, product_code_id = ?, `quantity` = ? WHERE id = ?", [$productCodesData['invoiceNumber'], $productCodesData['productCodeId'], $productCodesData['quantity'],$productCodesData['inStockId']]);

                    $Stock = DB::raw("UPDATE `stocks` SET quantity = quantity - ? + ? WHERE product_code_id = ?", [
                        $inStockOldQuantity,
                        $productCodesData['quantity'],
                        $productCodesData['productCodeId']
                    ]);


                }elseif($productCodesData['actionStatus'] == "DELETE"){
                    $inStock = DB::raw("DELETE FROM `in_stocks` WHERE product_code_id = ?", [$productCodesData['productCodeId']]);

                    $stock = DB::raw("DELETE FROM `stocks` WHERE product_code_id = ?", [$productCodesData['productCodeId']]);

                    $productCode = DB::raw("DELETE FROM `product_codes` WHERE id = ?", [$productCodesData['productCodeId']]);
                }
            }*/

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
