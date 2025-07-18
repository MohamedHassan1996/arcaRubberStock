<?php
namespace App\Controllers;

use App\Helpers\ApiResponse;
use Core\Contracts\HasMiddleware;
use Core\Controller;
use Core\DB;
use Core\Middleware;

/**
 * Home controller
 *
 * PHP version 5.4
 */
class ProductCodeController extends Controller implements HasMiddleware
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
            new Middleware('permission:all_product_codes', ['index']),
            new Middleware('permission:store_product_code', ['store']),
            new Middleware('permission:show_product_code', ['show']),
            new Middleware('permission:update_product_code', ['update']),
            new Middleware('permission:destroy_product_code', ['destroy']),
        ];
    }

    public function index()
    {
        $data = request();
        $sql = "SELECT id AS productCodeId, `code` 
                FROM product_codes
                WHERE product_id = ? AND
                deleted_at IS NULL";

        $productCodes = DB::raw($sql, [$data['productId']]);

        $responseData = [
            'productCodes' => $productCodes,
        ];

        return ApiResponse::success($responseData);
    }
    public function store()
    {

        try {
            $data = request();

            DB::beginTransaction();

            $productCode = DB::raw(
                "INSERT INTO `product_codes` (`code`, `product_id`) VALUES (?, ?)",
                [$data['code'], $data['productId']],
                false
            );

            DB::raw(
                "INSERT INTO `stocks` (`product_code_id`, `quantity`) VALUES (?, ?)",
                [$productCode, 0],
            );

            DB::commit();

            return ApiResponse::success('Product created successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

    public function show($id){

        $productData = DB::raw("SELECT * FROM product_codes WHERE id = ? AND deleted_at IS NULL", [$id]);

        $responseData = [
            'productCodeId' => $id,
            'code' => $productData[0]['code'],
            'productId' =>  $productData[0]['product_id'],
        ];

        return ApiResponse::success($responseData);

    }


    public function update()
    {

        //error_reporting(0);

        try {

            DB::beginTransaction();

            $data = request();

            $productCode = DB::raw("UPDATE `product_codes` SET `code` = ? WHERE id = ?", [$data['code'], $data['productCodeId']]);

            DB::commit();

            return ApiResponse::success('Product Code updated successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            DB::raw("UPDATE `product_codes` SET deleted_at = ? WHERE id = ? AND deleted_at IS NULL", [date('Y-m-d H:i:s'), $id]);
            DB::raw("UPDATE `in_stocks` SET deleted_at = ? WHERE product_code_id = ? AND deleted_at IS NULL", [date('Y-m-d H:i:s'), $id]);
            DB::raw("UPDATE `stocks` SET deleted_at = ? WHERE product_code_id = ? AND deleted_at IS NULL", [date('Y-m-d H:i:s'), $id]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        return ApiResponse::success('Product Code deleted successfully');
    }

}
