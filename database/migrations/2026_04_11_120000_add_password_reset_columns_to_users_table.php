<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password_reset_code', 255)->nullable()->after('verification_locked_until');
            $table->timestamp('password_reset_expires_at')->nullable()->after('password_reset_code');
            $table->unsignedTinyInteger('password_reset_attempts')->default(0)->after('password_reset_expires_at');
            $table->timestamp('password_reset_locked_until')->nullable()->after('password_reset_attempts');
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
