<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Boot Gates
    |--------------------------------------------------------------------------
    */

    'boot_gates' => true, //boot permission based on the package logic

    'boot_gates_in_console' => false,


    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache_enabled' => true,

    'cache_ttl'        => 2592000,

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    */
    'super_admin_role' => 'administrator',


    /*
    |--------------------------------------------------------------------------
    | Observers and Events
    |--------------------------------------------------------------------------
    */
    'observers'        => [
        'permission' => \Teksite\Authorize\Observers\PermissionObserver::class,
        'role'       => \Teksite\Authorize\Observers\RoleObserver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    | Detach roles/permissions when a model is (force) deleted, so a re-used
    | primary key can never inherit stale authorization rows.
    */
    'detach_on_delete' => true,
];
