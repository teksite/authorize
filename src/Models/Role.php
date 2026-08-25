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


#[UseFactory(RoleFactory::class)]
#[Fillable(['title', 'description' ,'hierarchy'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $table = 'auth_roles';

    /**
     * Suggested rules for creating a new entry
     *
     * @return string[]
     */

    public static function rules(string $operation = 'create', ?int $ignoreId = null): array
    {

        return match ($operation) {
            'create' => [
                'title'         => 'required|string|max:255|unique:auth_roles,title',
                'description'   => 'nullable|string|max:255',
                'permissions'   => 'array|required',
                'permissions.*' => 'exists:auth_permissions,id',
                'hierarchy'     => 'required|numeric',
            ],
            'update' => [
                'title'         => ['required', 'string', 'max:255', Rule::unique('auth_roles', 'title')->ignore($ignoreId)],
                'description'   => 'nullable|string|max:255',
                'permissions'   => 'array|required',
                'permissions.*' => 'exists:auth_permissions,id',
                'hierarchy'     => 'required|numeric',
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


    public function models(): MorphToMany
    {
        return $this->morphedByMany(Model::class, 'model', 'auth_role_models');
    }


    /**
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        $userClass = config('auth.providers.users.model');

        return $this->belongsToMany($userClass, 'auth_role_models');
    }


}
