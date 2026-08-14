<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acuan_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acuan_program_kerja_id')->constrained('acuan_program_kerjas')->cascadeOnDelete();
            $table->foreignId('tahun_kerja_id')->constrained('tahun_kerjas')->cascadeOnDelete();
            $table->string('target');
            $table->timestamps();

            $table->unique(['acuan_program_kerja_id', 'tahun_kerja_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acuan_targets');
    }
};
