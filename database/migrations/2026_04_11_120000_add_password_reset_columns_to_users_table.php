<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // No ->after(): PostgreSQL (e.g. Render) does not support column positioning in migrations.
            $table->string('password_reset_code', 255)->nullable();
            $table->timestamp('password_reset_expires_at')->nullable();
            $table->unsignedTinyInteger('password_reset_attempts')->default(0);
            $table->timestamp('password_reset_locked_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password_reset_code',
                'password_reset_expires_at',
                'password_reset_attempts',
                'password_reset_locked_until',
            ]);
        });
    }
};
