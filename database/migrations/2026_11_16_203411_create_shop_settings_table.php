<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->string('shop_name');
            $table->string('tagline')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->unsignedSmallInteger('booking_window_days')->default(14);
            $table->unsignedSmallInteger('slot_step_minutes')->default(15);
            $table->unsignedSmallInteger('min_lead_minutes')->default(15);
            // 0 switches the every-second-visit discount off.
            $table->unsignedTinyInteger('second_visit_percent')->default(20);
            $table->unsignedInteger('kids_midweek_discount_pence')->default(300);
            $table->unsignedInteger('free_gift_threshold_pence')->default(2000);
            $table->foreignId('free_gift_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedSmallInteger('reservation_hold_days')->default(7);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_settings');
    }
};
