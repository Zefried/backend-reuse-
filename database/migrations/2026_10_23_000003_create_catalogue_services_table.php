<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('users')->cascadeOnDelete();
            $table->string('service_name');
            $table->timestamps();

            $table->unique(['business_id', 'service_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_services');
    }
};
