<?php

namespace Teksite\Authorize\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Validation\Rule;

class Permission extends Model
{
    protected $table = 'auth_permissions';

    protected $fillable = ['title', 'description'];

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

}
