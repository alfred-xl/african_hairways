<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('customer_name');
            $table->string('status', 20)->default('reserved');
            $table->unsignedInteger('total_pence');
            $table->date('collect_by');
            $table->dateTime('collected_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reservations');
    }
};
