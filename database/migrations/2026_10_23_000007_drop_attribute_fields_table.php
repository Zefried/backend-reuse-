<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('attribute_fields');
    }

    public function down(): void
    {
        Schema::create('attribute_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attribute_definition_id');
            $table->string('attribute_definition_name');
            $table->string('name');
            $table->string('slug');
            $table->string('data_type')->default('string');
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['attribute_definition_id', 'slug']);
            $table->foreign('attribute_definition_id')
                  ->references('id')
                  ->on('attribute_definitions')
                  ->onDelete('cascade');
        });
    }
};
