<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_mfa_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('secret')->nullable();
            $table->text('pending_secret')->nullable();
            $table->timestamp('pending_expires_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->unsignedBigInteger('last_used_counter')->nullable();
            $table->timestamps();
        });

        Schema::create('user_mfa_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_mfa_setting_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_mfa_setting_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mfa_recovery_codes');
        Schema::dropIfExists('user_mfa_settings');
    }
};
