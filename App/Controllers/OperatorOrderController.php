<?php
namespace App\Controllers;

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
            //new Middleware('permission:store_operatotr_order', ['index'])
        ];
    }

    public function index()
    {
        $data = request();
        $pageSize = (int) $data['pageSize'];
        $page = (int) $data['page'] ?? 1;
        $offset = ($page - 1) * $pageSize;

        $sql = "SELECT id AS orderId, `number` AS orderNumber, `status` 
                FROM orders 
                WHERE deleted_at IS NULL 
                LIMIT $pageSize OFFSET $offset";

        $orders = DB::raw($sql);

        $ordersCount = DB::raw("SELECT count(*) as total FROM orders WHERE deleted_at IS NULL");

        $responseData = [
            'orders' => $orders,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalInPage' => count($orders),
                'total' => $ordersCount[0]['total'] ?? 0
            ]
        ];

        return ApiResponse::success($responseData);
    }
    public function store()
    {

        try {
            $data = request();

            $user = Auth::user();

            DB::beginTransaction();

            $orderNumber = "ORD-" . strval(date('Y') . date('m') . date('d'));

            $order = DB::raw("INSERT INTO `orders` (`user_id`, `number`,`status`) VALUES (?, ?, ?)", [$user->id, $orderNumber, $data['status']], false);

            foreach ($data['orderItems'] as $key => $orderItemData) {

                $orderItem = DB::raw("INSERT INTO `order_items` (`order_id`, `product_code_id`, `quantity`) VALUES (?, ?, ?)", [$order, $orderItemData['productCodeId'], $orderItemData['quantity']]);
            }

            DB::commit();

            return ApiResponse::success('Order created successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

    public function show($id){

        $orderData = DB::raw("SELECT * FROM orders WHERE id = ?", [$id]);

        $orderItemsData = DB::raw("SELECT id as orderItemId, product_code_id as productCodeId, quantity FROM order_items WHERE order_id = ?", [$id]);

        $orderResponse = [
            'orderId' => $id,
            'orderNumber' => $orderData[0]['number'],
            'status' => $orderData[0]['status'],
            'orderItems' => $orderItemsData,
        ];

        return ApiResponse::success($orderResponse);

    }


    public function update()
    {

        try {
            $data = request();

            DB::beginTransaction();

            $order = DB::raw("UPDATE `orders` SET `status` = ? WHERE id = ?", [$data['status'], $data['orderId']]);

            foreach ($data['orderItems'] as $key => $orderItemData) {
                $orderItem = null;
                if($orderItemData['actionStatus'] == "CREATE"){
                    $orderItem = DB::raw("INSERT INTO `order_items` (`order_id`, `product_code_id`, `quantity`) VALUES (?, ?, ?)", [$data['orderId'], $orderItemData['productCodeId'], $orderItemData['quantity']]);
                }elseif($orderItemData['actionStatus'] == "UPDATE"){
                    $orderItem = DB::raw("UPDATE `order_items` SET product_code_id = ?, `quantity` = ? WHERE id = ?", [$orderItemData['productCodeId'], $orderItemData['quantity'], $orderItemData['orderItemId']]);
                }elseif($orderItemData['actionStatus'] == "DELETE"){
                    DB::raw("DELETE FROM `order_items` WHERE id = ?", [$orderItemData['orderItemId']]);
                }elseif($orderItemData['actionStatus'] == ""){
                    $orderItem = $orderItemData['orderItemId'];
                }

                if($data['status'] == OrderStatus::CONFIRMED->value && in_array($orderItemData['actionStatus'], ['CREATED', 'UPDATED', ''])){
                        $outStock = DB::raw("INSERT INTO out_stocks (`order_id`, `product_code_id`, `quantity`) VALUES (?, ?, ?)", [$data['orderId'], $orderItemData['productCodeId'], $orderItemData['quantity']]);

                        $Stock = DB::raw("UPDATE `stocks` SET quantity = quantity - ? WHERE product_code_id = ?", [
                            $orderItemData['quantity'],
                            $orderItem
                        ]);
                }
            }

            DB::commit();

            return ApiResponse::success('Order updated successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            DB::raw("UPDATE `orders` SET deleted_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $id]);
            DB::raw("UPDATE `order_items` SET deleted_at = ? WHERE order_id = ?", [date('Y-m-d H:i:s'), $id]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        return ApiResponse::success('Order deleted successfully');
    }

}
