<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Boot Gates
    |--------------------------------------------------------------------------
    */

    'boot_gates' => true, // boot permission based on the package logic

    'boot_gates_in_console' => false,


    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache_enabled' => true,

    'cache_ttl' => 86400, // 24 hours

    // Clear a model's authorization cache whenever the model is saved.
    // Set to false for models that are saved very often (e.g. last_login_at).
    'flush_on_save' => true,


    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    */

    // Detach roles/permissions when a model is (force) deleted.
    'detach_on_delete' => true,


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

    'observers' => [
        'permission' => \Teksite\Authorize\Observers\PermissionObserver::class,
        'role'       => \Teksite\Authorize\Observers\RoleObserver::class,
    ],
];
