<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_slots', function (Blueprint $table) {
            $table->string('module_name')->nullable()->after('period');
            $table->string('instructor_name')->nullable()->after('module_name');
        });
    }

    public function down(): void
    {
        Schema::table('training_slots', function (Blueprint $table) {
            $table->dropColumn(['module_name', 'instructor_name']);
        });
    }
};
