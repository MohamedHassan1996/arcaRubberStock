<?php
namespace App\Controllers;

use App\Helpers\ApiResponse;
use App\Services\Select\SelectService;
use Core\Contracts\HasMiddleware;
use Core\Controller;
use Core\DB;
use Core\Middleware;

/**
 * Home controller
 *
 * PHP version 5.4
 */
class SelectController extends Controller implements HasMiddleware
{

    private $selectService;

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

        $this->selectService = new SelectService();

        $selectData = $this->selectService->getSelects($data['allSelects']);

        return ApiResponse::success($selectData);
    }

}
