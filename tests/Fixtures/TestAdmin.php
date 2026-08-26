<?php

namespace Teksite\Authorize\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Teksite\Authorize\Traits\HasAuthorization;

/**
 * A second, unrelated Eloquent model used to prove the package is
 * model-independent and that cache keys don't collide between
 * different model types sharing the same primary key.
 */
class TestAdmin extends Model
{
    use HasAuthorization;

    protected $table = 'admins';

    protected $guarded = [];
}
