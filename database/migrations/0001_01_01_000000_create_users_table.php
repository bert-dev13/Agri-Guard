<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Final users schema (farm profile, email OTP verification, crop timeline, GPS).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_code', 255)->nullable();
            $table->timestamp('email_verification_expires_at')->nullable();
            $table->unsignedTinyInteger('verification_attempts')->default(0);
            $table->timestamp('verification_locked_until')->nullable();

            $table->string('password');
            $table->string('role', 32)->default('farmer');

            $table->string('farm_municipality')->nullable();
            $table->string('farm_barangay')->nullable();
            $table->string('farm_barangay_code', 20)->nullable();
            $table->string('crop_type')->nullable();
            $table->string('farming_stage')->nullable();
            $table->date('planting_date')->nullable();
            $table->unsignedSmallInteger('crop_timeline_offset_days')->default(0);
            $table->string('crop_stage_reality_check', 24)->nullable();
            $table->boolean('reality_check_answered')->default(false);
            $table->string('reality_check_status', 16)->nullable();
            $table->timestamp('stage_confirmed_at')->nullable();
            $table->decimal('farm_area', 12, 2)->nullable();
            $table->decimal('farm_lat', 10, 7)->nullable();
            $table->decimal('farm_lng', 10, 7)->nullable();
            $table->timestamp('gps_captured_at')->nullable();
            $table->string('location_source', 32)->nullable();
            $table->string('field_condition')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
