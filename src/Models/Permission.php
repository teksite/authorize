<?php

namespace Teksite\Authorize\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Permission extends Model
{
    protected $table = 'auth_permissions';

    protected $fillable = ['title', 'description'];

    /**
     * @return string[]
     */
    public static function rules(): array
    {
        return
            [
                'title' => 'required|string|max:255|unique:auth_permissions,title',
                'description' => 'nullable|string|max:255',
            ];
    }

    /**
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(role::class, 'auth_permission_role');
    }

}
