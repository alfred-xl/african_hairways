<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('label');
            $table->string('note')->nullable();
            $table->string('description')->nullable();
            $table->string('type', 10);
            // A whole percentage for percent codes, pence for fixed codes.
            $table->unsignedInteger('value');
            $table->boolean('first_visit_only')->default(false);
            // Weekdays the appointment may fall on (0 = Sunday ... 6 = Saturday). Null means any day.
            $table->json('valid_weekdays')->nullable();
            // The appointment must start before this time of day. Null means any time.
            $table->time('valid_before_time')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('max_redemptions_per_customer')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_offers_page')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
