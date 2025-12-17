<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email', 100);
            $table->string('otp_code', 6);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('username', 50);
            $table->string('phone', 20)->nullable();
            $table->string('password', 255);
            $table->string('profile_pic', 255)->nullable();
            $table->enum('role', ['customer', 'supplier'])->default('customer');
            $table->timestamp('expires_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['email', 'otp_code'], 'idx_email_otp');
            $table->index('expires_at', 'idx_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};

