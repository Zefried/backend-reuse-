<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact');
            $table->date('visit_date')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new'); // new, contacted, resolved, archived
            $table->text('admin_notes')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_enquiries');
    }
};
