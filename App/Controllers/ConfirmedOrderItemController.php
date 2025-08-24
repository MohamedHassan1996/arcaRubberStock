<?php
namespace App\Controllers;

use App\Enums\OrderItemStatus;
use App\Helpers\ApiResponse;
use Core\Auth;
use Core\Contracts\HasMiddleware;
use Core\Controller;
use Core\DB;
use Core\Middleware;
use DateTime;

/**
 * Home controller
 *
 * PHP version 5.4
 */
class ConfirmedOrderItemController extends Controller implements HasMiddleware
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
            new Middleware('permission:confirmed-order-items', ['index']),
        ];
    }

    public function index(){
        $data = request();
        $pageSize = (int) $data['pageSize'];
        $page = (int) ($data['page'] ?? 1);
        $offset = ($page - 1) * $pageSize;
        $filters = $data['filter'] ?? [];

        $auth = Auth::user();

        $statuses = [OrderItemStatus::CONFIRMED->value];
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = $statuses;

        if(isset($filters['status']) && $filters['status'] != null){
            $statuses = [$filters['status']];
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $params = $statuses;
        }

        $whereSql = "WHERE order_items.deleted_at IS NULL 
                    AND orders.deleted_at IS NULL 
                    AND order_items.status IN ($placeholders)";

        // Apply filters
        // if (!empty($filters['productId'])) {
        //     $whereSql .= " AND orders.id IN (
        //         SELECT order_id 
        //         FROM order_items 
        //         WHERE product_id = ?
        //     )";
        //     $params[] = $filters['productId'];
        // }

        if (!empty($filters['productId'])) {
            $whereSql .= " AND order_items.product_id = ?";
            $params[] = $filters['productId'];
        }


        if (!empty($filters['operatorId'])) {
            $whereSql .= " AND orders.user_id = ?";
            $params[] = $filters['operatorId'];
        }

        if (!empty($filters['startAt']) && !empty($filters['endAt'])) {
            $whereSql .= " AND order_items.created_at BETWEEN ? AND ?";
            $params[] = $filters['startAt'] . ' 00:00:00';
            $params[] = $filters['endAt'] . ' 23:59:59';
        } elseif (!empty($filters['startAt'])) {
            $whereSql .= " AND order_items.created_at >= ?";
            $params[] = $filters['startAt'] . ' 00:00:00';
        } elseif (!empty($filters['endAt'])) {
            $whereSql .= " AND order_items.created_at <= ?";
            $params[] = $filters['endAt'] . ' 23:59:59';
        }

        // Main data query
        $sql = "SELECT 
                    order_items.id AS orderItemId,
                    order_items.quantity,
                    order_items.note AS orderItemNote,
                    order_items.created_at AS orderItemCreatedAt,
                    order_items.status AS orderItemStatus,
                    orders.id AS orderId,
                    order_items.product_id AS productId,
                    orders.number AS orderNumber,
                    users.name AS username,
                    users.id AS userId,
                    users.product_role_id AS productRoleId,
                    products.name AS productName,
                    products.description AS productDescription,
                    order_items.delivered_quantity
                FROM order_items
                JOIN orders ON order_items.order_id = orders.id
                JOIN users ON orders.user_id = users.id
                JOIN products ON order_items.product_id = products.id
                $whereSql
                order by order_items.created_at ASC, products.name ASC LIMIT $pageSize OFFSET $offset";


        $orderItems = DB::select($sql, $params);
        foreach ($orderItems as &$item) {
            $item = (array) $item; // convert stdClass to array
        }
        unset($item);

        // Count query
        $countSql = "SELECT COUNT(*) as total 
                    FROM order_items 
                    JOIN orders ON order_items.order_id = orders.id
                    $whereSql";

        $countResult = DB::select($countSql, $params);
        $orderItemsCount = isset($countResult[0]) ? (array) $countResult[0] : ['total' => 0];

        $orderItemsData = [];

        foreach ($orderItems as $orderItem) {
            $roleProductResult = DB::select(
                'SELECT * FROM role_product WHERE product_id = ? AND role_id = ? AND deleted_at IS NULL',
                [$orderItem['productId'], $orderItem['productRoleId']]
            );
            $maxTimesToOrderInPeriod = isset($roleProductResult[0]) ? (array) $roleProductResult[0] : [];

            $previousOrderQuantity = 0;

            if (!empty($maxTimesToOrderInPeriod)) {
                $periodResult = DB::select(
                    'SELECT * FROM parameter_values WHERE id = ?',
                    [$maxTimesToOrderInPeriod['period_id']]
                );
                $periodData = isset($periodResult[0]) ? (array) $periodResult[0] : [];
                $periodDays = (int) ($periodData['description'] ?? 0);

                $previous = DB::select("
                    SELECT SUM(order_items.quantity) as totalQuantity
                    FROM order_items
                    LEFT JOIN orders ON order_items.order_id = orders.id
                    WHERE order_items.product_id = ?
                    AND orders.user_id = ?
                    AND order_items.created_at >= NOW() - INTERVAL ? DAY
                    AND order_items.deleted_at IS NULL
                    AND orders.deleted_at IS NULL
                    AND order_items.status = ?
                ", [
                    $orderItem['productId'],
                    $orderItem['userId'],
                    $periodDays,
                    OrderItemStatus::CONFIRMED->value
                ]);

                $previous = isset($previous[0]) ? (array) $previous[0] : [];
                $previousOrderQuantity = $previous['totalQuantity'] ?? 0;
            }

            $orderDate = new DateTime($orderItem['orderItemCreatedAt']);

            $orderItemsData[] = [
                'orderItemId' => $orderItem['orderItemId'],
                'productId' => $orderItem['productId'],
                'productName' => $orderItem['productName'],
                'orderItemNote' => $orderItem['orderItemNote'] ?? '',
                'orderItemStatus' => $orderItem['orderItemStatus'],
                'quantity' => $orderItem['quantity'],
                'orderId' => $orderItem['orderId'],
                'orderDate' => $orderDate->format('d/m/Y'),
                'orderNumber' => $orderItem['orderNumber'],
                'username' => $orderItem['username'],
                'maxQuantity' => $maxTimesToOrderInPeriod['quantity'] ?? '-',
                'previousQuantity' => (int) $previousOrderQuantity,
                'remainingQuantity' => $orderItem['quantity'] - $orderItem['delivered_quantity'],
            ];
        }

        $responseData = [
            'orderItems' => $orderItemsData,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalInPage' => count($orderItems),
                'total' => $orderItemsCount['total'],
            ]
        ];

        return ApiResponse::success($responseData);

    }

}
