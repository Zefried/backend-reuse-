<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_id')->constrained('designs')->cascadeOnDelete();
            $table->string('attribute_name');
            $table->text('attribute_value');
            $table->timestamps();

            $table->index(['design_id', 'attribute_name']);
            $table->index('attribute_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_specifications');
    }
};
