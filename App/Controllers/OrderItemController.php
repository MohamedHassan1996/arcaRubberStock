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
use DateTime;

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
            new Middleware('permission:destroy_order_item', ['destroy']),
        ];
    }

    // public function index()
    // {
    //     $data = request();
    //     $pageSize = (int) $data['pageSize'];
    //     $page = (int) ($data['page'] ?? 1);
    //     $offset = ($page - 1) * $pageSize;

    //     $auth = Auth::user();

    //     $statuses = $auth->role['name'] == 'operator' 
    //         ? [OrderItemStatus::DRAFT->value]
    //         : [OrderItemStatus::DRAFT->value, OrderItemStatus::PENDING->value, OrderItemStatus::PARTIALLY_CONFIRMED->value];

    //     $placeholders = implode(',', array_fill(0, count($statuses), '?'));

    //     $sql = "SELECT order_items.id AS orderItemId, order_items.quantity, order_items.created_at as orderItemCreatedAt, order_items.status AS orderItemStatus, orders.id AS orderId, order_items.product_id AS productId,
    //                 orders.number AS orderNumber, users.name as username, users.id AS userId, users.product_role_id AS productRoleId, products.name AS productName, order_items.delivered_quantity
    //             FROM order_items 
    //             JOIN orders ON order_items.order_id = orders.id
    //             JOIN users ON orders.user_id = users.id
    //             JOIN products ON order_items.product_id = products.id
    //             WHERE order_items.deleted_at IS NULL AND orders.deleted_at IS NULL
    //             AND order_items.status IN ($placeholders)
    //             LIMIT $pageSize OFFSET $offset";

    //     $orderItems = DB::raw($sql, [...$statuses]);

    //     $countSql = "SELECT COUNT(*) as total FROM order_items 
    //                 WHERE deleted_at IS NULL AND status IN ($placeholders)";
    //     $orderItemsCount = DB::raw($countSql, [...$statuses], false);

    //     $orderItemsData = [];

    //     foreach($orderItems as $orderItem) {
    //         $maxTimesToOrderInPeriod = DB::raw('SELECT * FROM role_product WHERE product_id = ? AND role_id = ? AND deleted_at IS NULL', [$orderItem['productId'], $orderItem['productRoleId']]);

    //         $previousOrderQuantity = 0;
    //         if(!empty($maxTimesToOrderInPeriod)) {
    //             $periodData = DB::raw("SELECT * FROM parameter_values WHERE id = ?", [$maxTimesToOrderInPeriod[0]['period_id']]);

    //             $periodDays = (int) ($periodData[0]['description'] ?? 0);

    //             $previousOrderQuantity = DB::raw("
    //                 SELECT SUM(order_items.quantity) as totalQuantity
    //                 FROM order_items 
    //                 LEFT JOIN orders ON order_items.order_id = orders.id
    //                 WHERE order_items.product_id = ?
    //                 AND orders.user_id = ?
    //                 AND order_items.created_at >= NOW() - INTERVAL ? DAY
    //                 AND order_items.deleted_at IS NULL
    //                 AND orders.deleted_at IS NULL
    //                 AND order_items.status IN ('" . OrderItemStatus::CONFIRMED->value . "')
    //             ", [
    //                 $orderItem['productId'],
    //                 $orderItem['userId'],
    //                 $periodDays
    //             ]);

    //             $previousOrderQuantity = $previousOrderQuantity[0]['totalQuantity'] ?? 0;   

    //         }

    //         $orderDate = new DateTime($orderItem['orderItemCreatedAt']);

    //         $orderItemsData[] = [
    //             'orderItemId' => $orderItem['orderItemId'],
    //             'productId' => $orderItem['productId'],
    //             'productName' => $orderItem['productName'],
    //             'orderItemStatus' => $orderItem['orderItemStatus'],
    //             'quantity' => $orderItem['quantity'],
    //             'orderId' => $orderItem['orderId'],
    //             'orderDate' => $orderDate->format('d/m/Y'),
    //             'orderNumber' => $orderItem['orderNumber'],
    //             'username' => $orderItem['username'],
    //             'maxQuantity' => $maxTimesToOrderInPeriod[0]['quantity'] ?? '-',
    //             'previousQuantity' => (int)$previousOrderQuantity ?? 0,
    //             'remainingQuantity' => $orderItem['quantity'] - $orderItem['delivered_quantity']
    //         ];
    //     }

    //     $responseData = [
    //         'orderItems' => $orderItemsData,
    //         'pagination' => [
    //             'page' => $page,
    //             'pageSize' => $pageSize,
    //             'totalInPage' => count($orderItems),
    //             'total' => $orderItemsCount[0]['total'] ?? 0,
    //         ]
    //     ];

    //     return ApiResponse::success($responseData);
    // }

    public function index(){
        $data = request();
        $pageSize = (int) $data['pageSize'];
        $page = (int) ($data['page'] ?? 1);
        $offset = ($page - 1) * $pageSize;
        $filters = $data['filter'] ?? [];

        $auth = Auth::user();

        $statuses = $auth->role['name'] === 'operator'
            ? [OrderItemStatus::DRAFT->value]
            : [
                OrderItemStatus::DRAFT->value,
                OrderItemStatus::PENDING->value,
                OrderItemStatus::PARTIALLY_CONFIRMED->value
            ];

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = $statuses;

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
                    order_items.delivered_quantity
                FROM order_items
                JOIN orders ON order_items.order_id = orders.id
                JOIN users ON orders.user_id = users.id
                JOIN products ON order_items.product_id = products.id
                $whereSql
                ORDER BY order_items.created_at DESC
                LIMIT $pageSize OFFSET $offset";

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

        // $previous = DB::select("
        //     SELECT SUM(order_items.quantity) as totalQuantity
        //     FROM order_items
        //     LEFT JOIN orders ON order_items.order_id = orders.id
        //     WHERE order_items.product_id = ?
        //     AND orders.user_id = ?
        //     AND order_items.created_at >= NOW() - INTERVAL ? DAY
        //     AND order_items.deleted_at IS NULL
        //     AND orders.deleted_at IS NULL
        //     AND order_items.status IN ('" . OrderItemStatus::CONFIRMED->value . "')
        // ", [
        //     $orderItem['productId'],
        //     $orderItem['userId'],
        //     $periodDays,
        // ]);

        $prevStatuses = [
            OrderItemStatus::CONFIRMED->value,
            OrderItemStatus::PENDING->value,
            OrderItemStatus::PARTIALLY_CONFIRMED->value,
            OrderItemStatus::DELIVERED->value,
        ];

        // Prepare the placeholders (?, ?, ?, ...) for the IN clause
        $prevPlaceholders = implode(',', array_fill(0, count($prevStatuses), '?'));

        $prevBindings = [
            $orderItem['productId'],
            $orderItem['userId'],
            $periodDays,
            ...$prevStatuses,
        ];

        $previous = DB::select("
            SELECT SUM(order_items.quantity) as totalQuantity
            FROM order_items
            LEFT JOIN orders ON order_items.order_id = orders.id
            WHERE order_items.product_id = ?
            AND orders.user_id = ?
            AND order_items.created_at >= NOW() - INTERVAL ? DAY
            AND order_items.deleted_at IS NULL
            AND orders.deleted_at IS NULL
            AND order_items.status IN ($prevPlaceholders)
        ", $prevBindings);

        $previous = isset($previous[0]) ? (array) $previous[0] : [];
        $previousOrderQuantity = $previous['totalQuantity'] ?? 0;
    }

    $orderDate = new DateTime($orderItem['orderItemCreatedAt']);

    $orderItemsData[] = [
        'orderItemId' => $orderItem['orderItemId'],
        'productId' => $orderItem['productId'],
        'productName' => $orderItem['productName'],
        'orderItemNote' => $orderItem['orderItemNote']??"",
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
