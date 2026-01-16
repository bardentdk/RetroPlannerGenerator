<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_slots', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('period'); // 'morning' ou 'afternoon'
            $table->boolean('is_present')->default(false);
            $table->string('student_name')->nullable();
            $table->string('source_file')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_slots');
    }
};