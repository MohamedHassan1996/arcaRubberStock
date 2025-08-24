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
class OperatorOrderController extends Controller implements HasMiddleware
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
            new Middleware('permission:store_operator_order', ['index'])
        ];
    }

    public function store()
    {

        try {
            $data = request();

            $user = Auth::user();

            DB::beginTransaction();

            $orderNumber = "ORD-" . date('dmyHis') . $user->id;

            $order = DB::raw("INSERT INTO `orders` (`user_id`, `number`,`status`) VALUES (?, ?, ?)", [$user->id, $orderNumber, $data['status']], false);

            foreach ($data['orderItems'] as $key => $orderItemData) {

                $orderItem = DB::raw("INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, 'note') VALUES (?, ?, ?, ?)", [$order, $orderItemData['productId'], $orderItemData['quantity'], $orderItemData['note']], false);
            }

            DB::commit();

            return ApiResponse::success('Order created successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }


}
