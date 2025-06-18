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
class OrderItemController extends Controller implements HasMiddleware
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
            new Middleware('permission:all_order_items', ['index']),
            //new Middleware('permission:create_order_item', ['store']),
            new Middleware('permission:show_order_item', ['show']),
            new Middleware('permission:update_order_item', ['update']),
            new Middleware('permission:delete_order_item', ['destroy']),
        ];
    }

    public function index()
    {
        $data = request();
        $pageSize = (int) $data['pageSize'];
        $page = (int) ($data['page'] ?? 1);
        $offset = ($page - 1) * $pageSize;

        $auth = Auth::user();

        $statuses = $auth->role['name'] != 'admin'
            ? [OrderItemStatus::DRAFT->value]
            : [OrderItemStatus::DRAFT->value, OrderItemStatus::PENDING->value, OrderItemStatus::PARTIALLY_CONFIRMED->value];

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));

        $sql = "SELECT order_items.id AS orderItemId, order_items.quantity, order_items.status AS orderItemStatus, orders.id AS orderId, order_items.product_id AS productId,
                    orders.number AS orderNumber, users.name as username, users.id AS userId, users.product_role_id AS productRoleId, products.name AS productName, order_items.delivered_quantity
                FROM order_items 
                JOIN orders ON order_items.order_id = orders.id
                JOIN users ON orders.user_id = users.id
                JOIN products ON order_items.product_id = products.id
                WHERE order_items.deleted_at IS NULL AND orders.deleted_at IS NULL
                AND order_items.status IN ($placeholders)
                LIMIT $pageSize OFFSET $offset";

        $orderItems = DB::raw($sql, [...$statuses]);

        $countSql = "SELECT COUNT(*) as total FROM order_items 
                    WHERE deleted_at IS NULL AND status IN ($placeholders)";
        $orderItemsCount = DB::raw($countSql, [...$statuses], false);

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
                    AND order_items.status IN ('" . OrderItemStatus::CONFIRMED->value . "')
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
                'orderItemStatus' => $orderItem['orderItemStatus'],
                'quantity' => $orderItem['quantity'],
                'orderId' => $orderItem['orderId'],
                'orderNumber' => $orderItem['orderNumber'],
                'username' => $orderItem['username'],
                'maxQuantity' => $maxTimesToOrderInPeriod[0]['quantity'] ?? '-',
                'previousQuantity' => (int)$previousOrderQuantity ?? 0,
                'remainingQuantity' => $orderItem['quantity'] - $orderItem['delivered_quantity']
            ];
        }

        $responseData = [
            'orderItems' => $orderItemsData,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalInPage' => count($orderItems),
                'total' => $orderItemsCount[0]['total'] ?? 0,
            ]
        ];

        return ApiResponse::success($responseData);
    }

    /*public function store()
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
        
    }*/

    public function show($id){

        $auth = Auth::user();

        $sql = "SELECT order_items.id AS orderItemId, order_items.quantity, order_items.status AS orderItemStatus, orders.id AS orderId, order_items.product_id AS productId, order_items.delivered_quantity,
                    orders.number AS orderNumber, users.name AS username, users.id AS userId, users.product_role_id AS productRoleId, products.name AS productName
                FROM order_items 
                JOIN orders ON order_items.order_id = orders.id
                JOIN users ON orders.user_id = users.id
                JOIN products ON order_items.product_id = products.id
                WHERE order_items.deleted_at IS NULL AND orders.deleted_at IS NULL
                AND order_items.id = ?";

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


            $orderItemsData = [
                'orderItemId' => $orderItem['orderItemId'],
                'productId' => $orderItem['productId'],
                'productName' => $orderItem['productName'],
                'quantity' => $orderItem['quantity'],
                'orderId' => $orderItem['orderId'],
                'orderNumber' => $orderItem['orderNumber'],
                'username' => $orderItem['username'],
                'orderItemStatus' => $orderItem['orderItemStatus'],
                'maxQuantity' => $maxTimesToOrderInPeriod[0]['quantity'] ?? '-',
                'previousQuantity' => $previousOrderQuantity[0]['totalQuantity'] ?? 0,
                'remainingQuantity' => $orderItem['quantity'] - $orderItem['delivered_quantity']
            ];
        }


        return ApiResponse::success($orderItemsData);

    }


    public function update()
    {

        try {
            $data = request();

            DB::beginTransaction();
            
            $orderItem = DB::raw("UPDATE `order_items` SET `quantity` = ? WHERE id = ?", [
                $data['quantity'],
                $data['orderItemId']
            ]);

            
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
            DB::raw("UPDATE `order_items` SET deleted_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $id]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        return ApiResponse::success('Order deleted successfully');
    }

}
