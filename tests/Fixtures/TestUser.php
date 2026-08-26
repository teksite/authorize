<?php

namespace Teksite\Authorize\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Teksite\Authorize\Traits\HasAuthorization;

/**
 * Stand-in for the application's User model, used only in tests.
 */
class TestUser extends Authenticatable
{
    use HasAuthorization;

    protected $table = 'users';

    protected $guarded = [];
}
