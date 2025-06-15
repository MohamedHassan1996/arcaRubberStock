<?php
namespace App\Controllers;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
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
class OrderItemStatusController extends Controller implements HasMiddleware
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
            new Middleware('permission:update_order_status', ['update'])
        ];
    }

    public function update()
    {

        try {
            $data = request();

            DB::beginTransaction();
            
            DB::raw("UPDATE `order_items` SET `status` = ? WHERE id = ?", [
                OrderItemStatus::PENDING->value,
                $data['orderItemId']
            ]);

            
            DB::commit();

            return ApiResponse::success('Order updated successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

}
