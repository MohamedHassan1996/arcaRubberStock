<?php
namespace App\Controllers;

use App\Helpers\ApiResponse;
use Core\JWT;
use App\Models\User;
use Core\Contracts\HasMiddleware;
use Core\Hash;
use Core\Controller;
use Core\DB;
use Core\Middleware;

/**
 * Home controller
 *
 * PHP version 5.4
 */
class AuthController extends Controller implements HasMiddleware
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
            new Middleware('auth', ['logout']),
        ];
    }
    public function login(): string
    {
        $data = request();

        $user = DB::raw("SELECT * FROM users WHERE username = ? AND deleted_at IS NULL", [$data['username']]);

        debug($user);

        if (!$user || !Hash::check($data['password'], $user[0]->password)) {
            return ApiResponse::error('Invalid username or password');
        }

        $token = JWT::make([
            'sub' => $user[0]->id,
            'email' => $user[0]->email,
        ]);

        $userRole = User::find($user[0]->id);

        return ApiResponse::success([
            'tokenDetails' => [
                'token' => $token,
                'expiresAt' => 3600 * 48
            ], 
            'profile' => [
                'username' => $user[0]->username,
                'name' => $user[0]->name,
            ],
            'role' => $userRole->role['name'],
            'permissions' => $userRole->permissions
        ]);
    }

    public function logout()
    {   
        
        JWT::destroy();

        return ApiResponse::success([], 'Logout successful');
    }

}
