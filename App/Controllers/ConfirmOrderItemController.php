<?php
namespace App\Controllers;

use App\Enums\OrderItemStatus;
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
class ConfirmOrderItemController extends Controller implements HasMiddleware
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

    public function update()
    {

        try {
            $data = request();

            DB::beginTransaction();

            $orderItem = DB::raw("SELECT * FROM `order_items` WHERE id = ?", [$data['orderItemId']]);

            $quantity = $orderItem[0]['quantity'];
            $deliveredQuantity = $orderItem[0]['delivered_quantity'];

            $deliveredQuantity += $data['quantity'];

            if($deliveredQuantity == $quantity){
                $status = OrderItemStatus::CONFIRMED->value;
            }else {
                $status = $orderItem[0]['status'];
            }
            
            DB::raw("UPDATE `order_items` SET `status` = ?, `delivered_quantity` = ? WHERE id = ?", [
                $status,
                $data['orderItemId']
            ]);

            DB::raw("INERT INTO out_stocks (order_id, order_item_id, quantity) VALUES (?, ?, ?)", [
                $orderItem[0]['order_id'],
                $data['orderItemId'],
                $data['quantity']
            ]);

            
            DB::commit();

            return ApiResponse::success('Order updated successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

}
