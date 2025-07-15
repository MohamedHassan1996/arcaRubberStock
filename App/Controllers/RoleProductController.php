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
class RoleProductController extends Controller implements HasMiddleware
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
            new Middleware('permission:all_role_poducts', ['index']),
            new Middleware('permission:store_role_product', ['store']),
            new Middleware('permission:show_role_product', ['show']),
            new Middleware('permission:update_role_product', ['update']),
            new Middleware('permission:destroy_role_product', ['destroy']),
        ];
    }

    public function index()
{
    $sql = "SELECT 
                role_product.id AS roleProductId, 
                role_param.id AS roleId, 
                role_param.parameter_value AS roleName, 
                period_param.id AS periodId, 
                period_param.parameter_value AS periodName, 
                role_product.quantity
            FROM role_product 
            LEFT JOIN products ON role_product.product_id = products.id
            LEFT JOIN parameter_values AS role_param ON role_product.role_id = role_param.id
            LEFT JOIN parameter_values AS period_param ON role_product.period_id = period_param.id
            WHERE role_product.product_id = ? 
              AND role_product.deleted_at IS NULL";

    $roleProduct = DB::raw($sql, [request()['productId']]);

    return ApiResponse::success($roleProduct);
}

    public function store()
    {

        try {
            $data = request();

            DB::beginTransaction();

            DB::raw("INSERT INTO `role_product` (`product_id`, `role_id`, `period_id`, `quantity`) VALUES (?, ?, ?, ?)", [$data['productId'], $data['roleId'], $data['periodId'], $data['quantity']]);

            DB::commit();

            return ApiResponse::success('Product created successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

    public function show($id){

        $roleProduct = DB::raw("SELECT id AS roleProductId, product_id AS productId, role_id AS roleId, period_id AS periodId, quantity FROM role_product WHERE id = ? AND deleted_at IS NULL", [$id]);

        return ApiResponse::success($roleProduct);

    }


    public function update()
    {

        try {
            $data = request();

            DB::beginTransaction();

            $roleProduct = DB::raw("UPDATE `role_product` SET `quantity` = ?, `role_id` = ?, `period_id` = ? WHERE id = ?", [$data['quantity'], $data['roleId'], $data['periodId'], $data['roleProductId']]);

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
            
            DB::raw("UPDATE `role_product` SET deleted_at = ? WHERE id = ? AND deleted_at IS NULL", [date('Y-m-d H:i:s'), $id]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        return ApiResponse::success('Order deleted successfully');
    }

}
