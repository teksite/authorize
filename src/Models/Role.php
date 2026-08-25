<?php

namespace Teksite\Authorize\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Validation\Rule;
use Teksite\Authorize\factories\RoleFactory;
use Teksite\Authorize\Support\AuthorizationCache;


#[UseFactory(RoleFactory::class)]
#[Fillable(['title', 'description' ,'hierarchy'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $table = 'auth_roles';

    protected static function booted(): void
    {
        parent::booted();

        static::saved(function (Role $role) {
            AuthorizationCache::forgetRole($role);
        });

        static::deleted(function (Role $role) {
            AuthorizationCache::forgetRole($role);
        });
    }

    public static function rules(string $operation = 'create', ?int $ignoreId = null): array
    {

        return match ($operation) {
            'create' => [
                'title'         => 'required|string|max:255|unique:auth_roles,title',
                'description'   => 'nullable|string|max:255',
                'permissions'   => 'array|required',
                'permissions.*' => 'exists:auth_permissions,id',
                'hierarchy'     => 'required|integer',
            ],
            'update' => [
                'title'         => ['required', 'string', 'max:255', Rule::unique('auth_roles', 'title')->ignore($ignoreId)],
                'description'   => 'nullable|string|max:255',
                'permissions'   => 'array|required',
                'permissions.*' => 'exists:auth_permissions,id',
                'hierarchy'     => 'required|integer',
            ],
            default  => [],
        };

    }

    /**
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'auth_permission_role');
    }

    public function users(): MorphToMany
    {
        $userClass = config('auth.providers.users.model');

        return $this->morphedByMany($userClass, 'model', 'auth_role_models');
    }


}
