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
class OrderController extends Controller implements HasMiddleware
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
            // new Middleware('permission:all_orders', ['index']),
            // new Middleware('permission:create_order', ['store']),
            // new Middleware('permission:show_order', ['show']),
            // new Middleware('permission:update_order', ['update']),
            // new Middleware('permission:delete_order', ['destroy']),
        ];
    }

public function index()
{
    $data = request();
    $pageSize = (int) ($data['pageSize'] ?? 10);
    $page = (int) ($data['page'] ?? 1);
    $offset = ($page - 1) * $pageSize;

    $filters = $data['filter'] ?? [];

    // Base SQL query
    $sql = "SELECT 
                orders.id AS orderId, 
                orders.number AS orderNumber, 
                orders.status, 
                DATE_FORMAT(orders.created_at, '%d/%m/%Y') AS createdAt, 
                users.name AS username
            FROM orders
            LEFT JOIN users ON orders.user_id = users.id
            WHERE orders.deleted_at IS NULL
              AND orders.status != ?";

    $params = [OrderStatus::DRAFT->value];

    // Optional product filter
    if (!empty($filters['productId'])) {
        $sql .= " AND orders.id IN (
                    SELECT order_id 
                    FROM order_items 
                    WHERE product_id = ?
                 )";
        $params[] = $filters['productId'];
    }

    // Optional operator filter
    if (!empty($filters['operatorId'])) {
        $sql .= " AND orders.user_id = ?";
        $params[] = $filters['operatorId'];
    }

    // Finalize with ORDER and LIMIT (directly inserted as integers)
    $sql .= " ORDER BY orders.created_at DESC LIMIT $pageSize OFFSET $offset";

    // Execute main query
    $orders = DB::raw($sql, $params);

    // Total count query (ignores filters, you can apply them here too if needed)
    $countSql = "SELECT COUNT(*) AS total 
                 FROM orders 
                 WHERE deleted_at IS NULL 
                 AND status != ?";
    $countParams = [OrderStatus::DRAFT->value];
    $ordersCount = DB::raw($countSql, $countParams);

    // Final response
    return ApiResponse::success([
        'orders' => $orders,
        'pagination' => [
            'page' => $page,
            'pageSize' => $pageSize,
            'totalInPage' => count($orders),
            'total' => $ordersCount[0]['total'] ?? 0,
        ]
    ]);
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

                $orderItem = DB::raw("INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`) VALUES (?, ?, ?)", [$order, $orderItemData['productId'], $orderItemData['quantity']], false);
            }

            DB::commit();

            return ApiResponse::success('Order created successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

    public function show($id){

    $auth = Auth::user();

        $sql = "SELECT order_items.id AS orderItemId, order_items.quantity, order_items.status AS orderItemStatus, orders.id AS orderId, order_items.product_id AS productId, order_items.delivered_quantity,
                    orders.number AS orderNumber, users.name AS username, users.id AS userId, users.product_role_id AS productRoleId, products.name AS productName, DATE_FORMAT(orders.created_at, '%d/%m/%Y') AS createdAt
                FROM order_items 
                JOIN orders ON order_items.order_id = orders.id
                JOIN users ON orders.user_id = users.id
                JOIN products ON order_items.product_id = products.id
                WHERE order_items.deleted_at IS NULL AND orders.deleted_at IS NULL
                AND orders.id = ?";

        $orderItems = DB::raw($sql, [$id]);

        $orderItemsData = [];

        foreach($orderItems as $orderItem) {
            $maxTimesToOrderInPeriod = DB::raw('SELECT * FROM role_product WHERE product_id = ? AND role_id = ? AND deleted_at IS NULL', [$orderItem['productId'], $orderItem['productRoleId']]);
            $previousOrderQuantity = 0;


            if(!empty($maxTimesToOrderInPeriod)) {
                $periodData = DB::raw("SELECT * FROM parameter_values WHERE id = ?", [$maxTimesToOrderInPeriod[0]['period_id']]);


                $periodDays = (int) ($periodData[0]['description'] ?? 0);


                $previousOrderQuantity = DB::raw("
                    SELECT SUM(order_items.quantity) as totalQuantity
                    FROM order_items 
                    LEFT JOIN orders ON order_items.order_id = orders.id
                    WHERE order_items.product_id = ?
                    AND orders.user_id = ?
                    AND order_items.created_at >= NOW() - INTERVAL ? DAY
                    AND order_items.deleted_at IS NULL
                    AND orders.deleted_at IS NULL
                ", [
                    $orderItem['productId'],
                    $orderItem['userId'],
                    $periodDays
                ]);

                $previousOrderQuantity = $previousOrderQuantity[0]['totalQuantity'] ?? 0;   
            }


            $orderItemsData[] = [
                'orderItemId' => $orderItem['orderItemId'],
                'productId' => $orderItem['productId'],
                'productName' => $orderItem['productName'],
                'quantity' => $orderItem['quantity'],
                'orderId' => $orderItem['orderId'],
                'orderNumber' => $orderItem['orderNumber'],
                'username' => $orderItem['username'],
                'createdAt' => $orderItem['createdAt'],
                'orderItemStatus' => $orderItem['orderItemStatus'],
                'remainingQuantity' => $orderItem['quantity'] - $orderItem['delivered_quantity'],
            ];
        }


        $orderResponse = [
            'orderId' => $orderItemsData[0]['orderId'],
            'orderNumber' => $orderItemsData[0]['orderNumber'],
            'username' => $orderItemsData[0]['username'],
            'status' => $orderItems[0]['orderItemStatus'],
            'createdAt' => $orderItems[0]['createdAt'],
            'orderItems' => $orderItemsData
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
                    $orderItem = DB::raw("INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`) VALUES (?, ?, ?)", [$data['orderId'], $orderItemData['productCodeId'], $orderItemData['quantity']]);
                }elseif($orderItemData['actionStatus'] == "UPDATE"){
                    $orderItem = DB::raw("UPDATE `order_items` SET product_id = ?, `quantity` = ? WHERE id = ?", [$orderItemData['productId'], $orderItemData['quantity'], $orderItemData['orderItemId']]);
                }elseif($orderItemData['actionStatus'] == "DELETE"){
                    DB::raw("DELETE FROM `order_items` WHERE id = ?", [$orderItemData['orderItemId']]);
                }elseif($orderItemData['actionStatus'] == ""){
                    $orderItem = $orderItemData['orderItemId'];
                }

                if($data['status'] == OrderStatus::CONFIRMED->value && in_array($orderItemData['actionStatus'], ['CREATED', 'UPDATED', ''])){
                        $outStock = DB::raw("INSERT INTO out_stocks (`order_id`, `product_id`, `quantity`) VALUES (?, ?, ?)", [$data['orderId'], $orderItemData['productId'], $orderItemData['quantity']]);

                        $Stock = DB::raw("UPDATE `stocks` SET quantity = quantity - ? WHERE product_id = ?", [
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
