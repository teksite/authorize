<?php

namespace Teksite\Authorize\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Validation\Rule;
use Teksite\Authorize\factories\PermissionFactory;

#[UseFactory(PermissionFactory::class)]
#[Fillable(['title', 'description'])]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    protected $table = 'auth_permissions';

    /**
     * Suggested rules for creating a new entry
     *
     * @return string[]
     */
    public static function rules(string $operation = 'create' , ?int $ignoreId = null): array
    {

        return match ($operation) {
            'create' => ['title' => 'required|string|max:255|unique:auth_permissions,title', 'description' => 'nullable|string|max:255',],
            'update' => ['title' => ['required','string' ,'max:255' ,Rule::unique('auth_permissions' , 'title')->ignore($ignoreId)], 'description' => 'nullable|string|max:255',],
            default => [],
        };

    }

    /**
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'auth_permission_role');
    }


    /**
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {

        $userClass = config('auth.providers.users.model',
            class_exists(\Lareon\Steward\App\Models\User::class) ? \Lareon\Steward\App\Models\User::class : \App\Models\User::class
        );
        return $this->belongsToMany($userClass, 'auth_permission_models');
    }

}
