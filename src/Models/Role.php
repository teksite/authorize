<?php

namespace Teksite\Authorize\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $table = 'auth_roles';

    protected $fillable = ['title', 'description', 'hierarchy'];

    /**
     * Suggested rules for creating a new entry
     *
     * @return string[]
     */
    public static function rules(): array
    {
        return [
            'title'         => 'required|string|max:255|unique:auth_roles,title',
            'description'   => 'nullable|string|max:255',
            'permissions'   => 'array|required',
            'permissions.*' => 'exists:auth_permissions,id',
            'hierarchy'     => 'required', 'numeric',
        ];
    }

    /**
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'auth_permission_role');
    }

    /**
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'auth_permission_role');
    }

}
