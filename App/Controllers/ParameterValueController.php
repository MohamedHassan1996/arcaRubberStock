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
class ParameterValueController extends Controller implements HasMiddleware
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

        $sql = "SELECT id AS parameterValueId, parameter_value AS parameterValue, `description` FROM parameter_values
                WHERE parameter_values.deleted_at IS NULL AND parameter_values.parameter_id = ?
                LIMIT $pageSize OFFSET $offset";

        $productCodes = DB::raw($sql, [$data['parameterId']]);

        $productCodesCount = DB::raw("SELECT count(*) as total FROM products WHERE deleted_at IS NULL");

        $responseData = [
            'parameterValues' => $productCodes,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalInPage' => count($productCodes),
                'total' => $productCodesCount[0]['total'] ?? 0
            ]
        ];

        return ApiResponse::success($responseData);
    }

    public function store(){

        try {
            $data = request();

            DB::beginTransaction();

            DB::raw("INSERT INTO `parameter_values` (`parameter_id`, `parameter_value`, `parameter_order`, `description`) VALUES (?, ?, ?, ?)", [$data['parameterId'], $data['parameterValue'], $data['parameterOrder'], $data['description']], false);

            DB::commit();

            return ApiResponse::success('parameter created successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }
   
    public function show($id){

        $sql = "SELECT id AS parameterValueId, parameter_value AS parameterValue, parameter_id AS parameterId, parameter_order as parameterOrder, `description` FROM parameter_values
                WHERE parameter_values.deleted_at IS NULL AND parameter_values.id = ? LIMIT 1";

        $parameterValuesData = DB::raw($sql, [$id]);

        return ApiResponse::success($parameterValuesData[0]);

    }


    public function update()
    {

        try {
            $data = request();

            DB::beginTransaction();

           DB::raw("UPDATE `parameter_values` SET `parameter_value` = ?, `description` = ? WHERE id = ?", [$data['parameterValue'], $data['description'], $data['parameterValueId']]);

            DB::commit();

            return ApiResponse::success('parameter updated successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            
            DB::raw("UPDATE `parameter_values` SET deleted_at = ? WHERE id = ? AND deleted_at IS NULL", [date('Y-m-d H:i:s'), $id]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        return ApiResponse::success('parameter deleted successfully');
    }

}
