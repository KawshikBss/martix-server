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
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->unsignedBigInteger('source_inventory_id');
            $table->foreign('source_inventory_id')->references('id')->on('inventories')->onDelete('cascade');
            $table->unsignedBigInteger('destination_inventory_id');
            $table->foreign('destination_inventory_id')->references('id')->on('inventories')->onDelete('cascade');
            $table->string('status')->nullable();
            $table->unsignedBigInteger('created_by_id');
            $table->foreign('created_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
