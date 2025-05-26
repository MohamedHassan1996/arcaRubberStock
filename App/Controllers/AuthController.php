<?php
namespace App\Controllers;

use App\Helpers\ApiResponse;
use Core\JWT;
use App\Models\User;
use Core\Contracts\HasMiddleware;
use Core\Hash;
use Core\Controller;
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

        $user = User::where('username', $data['username'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('Invalid username or password');
        }

        $token = JWT::make([
            'sub' => $user->id,
            'email' => $user->username
        ]);
        return ApiResponse::success([
            'tokenDetails' => [
                'token' => $token,
                'expiresAt' => 3600 * 48
            ], 
            'profile' => [
                'username' => $user->username,
            ],
            'role' => $user->role['name'],
            'permissions' => $user->permissions
        ]);
    }

    public function logout()
    {   
        
        JWT::destroy();

        return ApiResponse::success([], 'Logout successful');
    }

}
