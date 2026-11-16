<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->string('access_token', 64)->unique();
            $table->foreignId('staff_member_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('customer_name');
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('status', 20)->default('booked');
            $table->string('source', 20)->default('online');
            $table->unsignedInteger('subtotal_pence');
            $table->unsignedInteger('discount_pence')->default(0);
            $table->unsignedInteger('total_pence');
            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            // Chair renters are paid directly, so these bookings stay out of shop takings.
            $table->boolean('pay_staff_directly')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancelled_by', 20)->nullable();
            $table->timestamps();

            $table->index(['staff_member_id', 'starts_at', 'ends_at']);
            $table->index(['customer_id', 'status']);
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
