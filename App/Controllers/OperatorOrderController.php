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

                $orderItem = DB::raw("INSERT INTO `order_items` (`order_id`, `product_code_id`, `quantity`, `created_at`) VALUES (?, ?, ?, ?)", [$order, $orderItemData['productCodeId'], $orderItemData['quantity'], date('Y-m-d H:i:s')], false);
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

        $orderItemsData = DB::select(
            "SELECT 
                order_items.id AS orderItemId,
                order_items.product_code_id AS productCodeId, 
                order_items.quantity, 
                product_codes.code AS productCode
            FROM order_items
            JOIN product_codes ON order_items.product_code_id = product_codes.id
            WHERE order_items.order_id = ?",
            [$id]
        );

        $orderResponse = [
            'orderId' => $id,
            'orderNumber' => $orderData[0]['number'],
            'status' => $orderData[0]['status'],
            'orderItems' => $orderItemsData,
        ];

        foreach ($orderItemsData as $key => $orderItemData) {
            $role = DB::raw("SELECT * FROM model_has_role WHERE model_id = ?", [$orderData[0]['user_id']]);
            $productCode = DB::raw("SELECT * FROM product_codes WHERE id = ?", [$orderItemData['productCodeId']]);
            $roleProduct = DB::raw("SELECT * FROM role_product WHERE product_id = ? AND role_id = ?", [$productCode[0]['product_id'], $role[0]['id']]);
            $parameterValue = DB::raw("SELECT `description` FROM parameter_values WHERE id = ?", [$roleProduct[0]['period_id']]);
            debug($parameterValue);
            $days = isset($parameterValue[0]) ? (int) $parameterValue[0]['description'] : 0;
$sql = "
    SELECT SUM(quantity) AS totalQuantity
    FROM order_items
    WHERE product_code_id = ?
      AND created_at >= NOW() - INTERVAL $days DAY
";


$usedQuantity = DB::raw($sql, [$orderItemData['productCodeId']]);
$orderItemData['usedQuantity'] = $usedQuantity[0]->totalQuantity ?? 0;      
        }

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
                    $orderItem = DB::raw("INSERT INTO `order_items` (`order_id`, `product_code_id`, `quantity`, `created_at`) VALUES (?, ?, ?, ?)", [$data['orderId'], $orderItemData['productCodeId'], $orderItemData['quantity'], date('Y-m-d H:i:s')]);
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
