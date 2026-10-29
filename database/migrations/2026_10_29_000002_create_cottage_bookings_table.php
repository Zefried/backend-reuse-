<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cottage_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('cottage_id');
            $table->string('cottage_name');
            $table->string('guest_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->date('arrival_date')->nullable();
            $table->string('nights')->nullable();
            $table->string('guests_count')->nullable();
            $table->text('special_requests')->nullable();
            $table->string('status')->default('pending'); // pending, confirmed, cancelled, completed
            $table->decimal('price_quoted', 10, 2)->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cottage_bookings');
    }
};
