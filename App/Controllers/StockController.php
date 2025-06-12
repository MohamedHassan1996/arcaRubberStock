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
class StockController extends Controller implements HasMiddleware
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

        $flter = $data['filter']['productId'] ?? null;

        $sql = "SELECT stocks.id AS stockId, product_codes.code AS productCode, stocks.quantity, products.name as productName
                FROM stocks 
                LEFT JOIN product_codes ON stocks.product_code_id = product_codes.id
                LEFT JOIN products ON product_codes.product_id = products.id
                WHERE stocks.deleted_at IS NULL 
                LIMIT $pageSize OFFSET $offset";

        $productCodes = DB::raw($sql);

        $productCodesCount = DB::raw("SELECT count(*) as total FROM products WHERE deleted_at IS NULL");

        $responseData = [
            'products' => $productCodes,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalInPage' => count($productCodes),
                'total' => $productCodesCount[0]['total'] ?? 0
            ]
        ];

        return ApiResponse::success($responseData);
    }
   
    public function show($id){

        $sql = "SELECT stocks.id AS stockId, product_codes.code AS productCode, stocks.quantity, products.name as productName
        FROM stocks 
        LEFT JOIN product_codes ON stocks.product_code_id = product_codes.id
        LEFT JOIN products ON product_codes.product_id = products.id
        WHERE stocks.deleted_at IS NULL AND stocks.id = ?";

        $productCodesData = DB::raw($sql, [$id]);

        return ApiResponse::success($productCodesData);

    }


    public function update()
    {

        try {
            $data = request();

            DB::beginTransaction();

            $stock = DB::raw("UPDATE `stocks` SET `quantity` = ? WHERE id = ?", [$data['quantity'], $data['stockId']]);

            DB::commit();

            return ApiResponse::success('َQuantity updated successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

}
