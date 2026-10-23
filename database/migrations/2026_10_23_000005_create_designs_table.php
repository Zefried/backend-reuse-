<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('catalogue_services')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('catalogue_categories')->cascadeOnDelete();
            $table->string('design_name');
            $table->timestamps();

            $table->index(['business_id', 'service_id', 'category_id']);
            $table->index('design_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designs');
    }
};
