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
        Schema::table('users', function (Blueprint $table) {
            $table->string('image')->after('name')->nullable();
            $table->string('phone')->unique()->after('email')->nullable();
            $table->string('address')->after('phone')->nullable();
            $table->string('nid')->after('address')->nullable();
            $table->string('unique_id')->after('nid')->nullable();
            $table->enum('status', ['active', 'disabled', 'banned'])->after('unique_id')->default('active');
            $table->boolean('tfa_enabled')->after('status')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
