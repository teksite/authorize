<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auth_roles', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->string('description')->nullable();
            $table->float('hierarchy', 2)->default(15);
            $table->timestamps();
        });

        Schema::create('auth_permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('auth_roles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('auth_permissions')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('auth_role_models', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('auth_roles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->uuidMorphs('model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_role_models');
        Schema::dropIfExists('auth_roles');
    }
};
