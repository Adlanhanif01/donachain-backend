<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('donor')->index();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('organization')->nullable();
            $table->string('id_card_path')->nullable();
            $table->string('verification_status')->default('not_required')->index();
            $table->string('wallet_address')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['wallet_address']);
            $table->dropIndex(['role']);
            $table->dropIndex(['verification_status']);
            $table->dropColumn([
                'role',
                'phone',
                'address',
                'organization',
                'id_card_path',
                'verification_status',
                'wallet_address',
            ]);
        });
    }
};
