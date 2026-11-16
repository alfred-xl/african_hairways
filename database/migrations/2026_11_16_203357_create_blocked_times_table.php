<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_times', function (Blueprint $table) {
            $table->id();
            // A null staff member blocks the whole shop (a one-off closure).
            $table->foreignId('staff_member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('reason')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['staff_member_id', 'ends_at']);
            $table->index(['ends_at', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_times');
    }
};
