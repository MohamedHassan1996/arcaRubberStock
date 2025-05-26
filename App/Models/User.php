<?php 

//require_once __DIR__ . '/../../vendor/autoload.php';

namespace App\Models;

use Core\DB;

class User extends Model {
    protected static $fillable = [
        'username',
        'password',
    ];

    public function getRoleAttribute(){
        $roleName = DB::select("
            SELECT roles.name
            FROM model_has_role
            JOIN roles ON model_has_role.role_id = roles.id
            WHERE model_has_role.model_id = ? LIMIT 1
        ", [$this->id]);

        return $roleName[0];
    }

    public function getpermissionsAttribute(){
                // Step 1: Get all permissions from the `permissions` table
        $allPermissions = DB::select("SELECT name FROM permissions");


        // Step 2: Get user’s permission names via their roles
        $userPermissions = DB::select("
            SELECT DISTINCT p.name
            FROM model_has_role AS mr
            JOIN role_has_permissions AS rp ON rp.role_id = mr.role_id
            JOIN permissions AS p ON p.id = rp.permission_id
            WHERE mr.model_id = ?
        ", [$this->id]);

        // Extract permission names into a flat array
        $userPermissionNames = array_column($userPermissions, 'name');

        // Step 3: Merge both to build final permission list
        $permissions = array_map(function ($permission) use ($userPermissionNames) {
            return [
                'permissionName' => $permission['name'],
                'access' => in_array($permission['name'], $userPermissionNames)
            ];
        }, $allPermissions);

        return $permissions;

    }
}