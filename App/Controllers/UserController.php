<?php
namespace App\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Core\Auth;
use Core\Contracts\HasMiddleware;
use Core\Controller;
use Core\DB;
use Core\Middleware;
use Core\Hash;

/**
 * Home controller
 *
 * PHP version 5.4
 */
class UserController extends Controller implements HasMiddleware
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
            new Middleware('permission:all_users', ['index']),
            new Middleware('permission:store_user', ['store']),
            new Middleware('permission:show_user', ['show']),
            new Middleware('permission:update_user', ['update']),
            new Middleware('permission:destroy_user', ['destroy']),
        ];
    }

    public function index()
    {
        $auth = Auth::user();
        //$auth->role['name']
        $data = request();
        $pageSize = (int) $data['pageSize'];
        $page = (int) $data['page'] ?? 1;
        $offset = ($page - 1) * $pageSize;

        $sql = "SELECT users.id AS userId, users.username, roles.name AS roleName
                FROM users 
                LEFT JOIN model_has_role ON users.id = model_has_role.model_id
                LEFT JOIN roles ON model_has_role.role_id = roles.id
                WHERE users.deleted_at IS NULL 
                LIMIT $pageSize OFFSET $offset";

        $users = DB::raw($sql);

        $usersCount = DB::raw("SELECT count(*) as total FROM users WHERE deleted_at IS NULL");

        $responseData = [
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalInPage' => count($users),
                'total' => $usersCount[0]['total'] ?? 0
            ]
        ];

        return ApiResponse::success($responseData);
    }
    public function store()
    {

        try {
            $data = request();

            DB::beginTransaction();

            $user = DB::raw("SELECT * FROM users WHERE username = ? AND deleted_at IS NULL", [$data['username']]);

            if ($user) {
                return ApiResponse::error('User already exists');
            }

            $params = [$data['username'], Hash::make($data['password'])];

            $sql = "INSERT INTO `users` (`username`, `password`";

            if (!empty($data['productRoleId'])) {
                $sql .= ", `product_role_id`";
                $params[] = $data['productRoleId'];
            }

            $sql .= ") VALUES (?, ?";

            if (!empty($data['productRoleId'])) {
                $sql .= ", ?";
            }

            $sql .= ")";

            $user = DB::raw($sql, $params, false);

            $userRole = DB::raw("INSERT INTO `model_has_role` (`role_id`, `model_id`) VALUES (?, ?)", [$data['roleId'], $user], false);

            DB::commit();

            return ApiResponse::success('User created successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

    public function show($id){

        $userData = DB::raw("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$id]);

        $userRole = DB::raw("SELECT role_id FROM model_has_role WHERE model_id = ?", [$userData[0]['id']]);

        $userResponse = [
            'userId' => $userData[0]['id'],
            'username' => $userData[0]['username'],
            'productRoleId' => $userData[0]['product_role_id'] ?? "",
            'roleId' => $userRole[0]['role_id'] ?? "",
        ];

        return ApiResponse::success($userResponse);

    }


    public function update()
    {

        try {
            $data = request();

            DB::beginTransaction();

            // Check if username exists
            $existingUser = DB::raw("SELECT * FROM users WHERE username = ? AND id != ? AND deleted_at IS NULL", [$data['username'], $data['userId']]);
            if ($existingUser) {
                return ApiResponse::error('Username already exists');
            }
            // $user = DB::raw("UPDATE `users` SET username = ?, password = ?, product_role_id = ? WHERE id = ? AND deleted_at IS NULL", [$data['name'], $data['password'], $data['productRoleId'], $data['userId']], false);

            $query = "UPDATE `users` SET username = ?";
            $bindings = [$data['username']];

            // Only include password if not empty
            if ($data['password'] !== '') {
                $query .= ", password = ?";
                $bindings[] = Hash::make($data['password']);
            }

            // Only include product_role_id if not empty
            if ($data['productRoleId'] !== '') {
                $query .= ", product_role_id = ?";
                $bindings[] = $data['productRoleId'];
            }

            $query .= " WHERE id = ? AND deleted_at IS NULL";
            $bindings[] = $data['userId'];

            // Execute the raw query
            $user = DB::raw($query, $bindings, false);

            $userRole = DB::raw("UPDATE `model_has_role` SET role_id = ? WHERE model_id = ?", [$data['roleId'], $data['userId']], false);
            

            DB::commit();

            return ApiResponse::success('User updated successfully');

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            $user = DB::raw("UPDATE `users` SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL", [$id], false);
            $userRole = DB::raw("DELETE FROM `model_has_role` WHERE model_id = ?", [$id], false);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        return ApiResponse::success('User deleted successfully');
    }

}
