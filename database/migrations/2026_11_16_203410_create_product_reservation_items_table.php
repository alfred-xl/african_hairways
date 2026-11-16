<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('unit_price_pence');
            $table->boolean('is_free_gift')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reservation_items');
    }
};
