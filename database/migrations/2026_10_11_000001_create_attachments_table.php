<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attachable_id')->index();
            $table->string('attachable_type')->index();
            $table->string('type')->index(); // 'pdf', 'youtube', 'document', 'link'
            $table->string('title')->nullable();
            $table->text('url'); // storage relative path or full web URL
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // size in bytes
            $table->string('file_hash')->nullable()->index(); // sha256
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
