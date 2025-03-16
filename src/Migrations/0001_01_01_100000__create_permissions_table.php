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
        Schema::create('auth_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });
        Schema::create('auth_permission_models', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('auth_permissions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->uuidMorphs('model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_permission_models');
        Schema::dropIfExists('auth_permissions');
    }
};
