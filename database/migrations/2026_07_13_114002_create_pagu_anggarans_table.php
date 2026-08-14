<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagu_anggarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_kerja_id')->constrained('tahun_kerjas')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerjas')->cascadeOnDelete();
            $table->decimal('amount', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tahun_kerja_id', 'unit_kerja_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagu_anggarans');
    }
};
