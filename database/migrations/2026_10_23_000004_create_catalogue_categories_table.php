<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('catalogue_services')->cascadeOnDelete();
            $table->string('category_name');
            $table->foreignId('parent_id')->nullable()->constrained('catalogue_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'service_id']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_categories');
    }
};
