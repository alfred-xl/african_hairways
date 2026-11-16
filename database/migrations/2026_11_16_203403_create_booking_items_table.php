<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            // Name, price and length are copied so later price changes never rewrite history.
            $table->string('name');
            $table->unsignedInteger('price_pence');
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('is_addon')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_items');
    }
};
