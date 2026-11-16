<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price_pence');
            $table->unsignedInteger('walk_in_price_pence')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('is_addon_only')->default(false);
            $table->boolean('available_as_addon')->default(false);
            $table->boolean('is_kids')->default(false);
            $table->string('badge', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
