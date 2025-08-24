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
class DeliverOrderItemController extends Controller implements HasMiddleware
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
            new Middleware('permission:deliver_order_item', ['update']),
        ];
    }

public function update()
    {

        try {
            $data = request();

            DB::beginTransaction();

            $orderItem = DB::raw("SELECT * FROM `order_items` WHERE id = ?", [$data['orderItemId']]);

            DB::raw("UPDATE `out_stocks` SET `status` = ? WHERE id = ?", [
                OrderItemStatus::DELIVERED->value,
                $data['outStockId']
            ]);

            $allOutStocks = DB::raw("SELECT * FROM `out_stocks` WHERE order_item_id = ?", [$data['orderItemId']]);

            $allDelivered = true;
            
            foreach ($allOutStocks as $outStock) {
                if ($outStock['status'] != OrderItemStatus::DELIVERED->value) {
                    $allDelivered = false;
                }
            }

            if ($allDelivered) {
                DB::raw("UPDATE `order_items` SET `status` = ? WHERE id = ?", [
                    OrderItemStatus::DELIVERED->value,
                    $data['orderItemId']
                ]);
            }

            DB::commit();

            return ApiResponse::success('Order updated successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }
}
