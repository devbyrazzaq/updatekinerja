<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penawaran_program_kerjas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acuan_program_kerja_id')->nullable()->constrained('acuan_program_kerjas')->nullOnDelete();
            $table->foreignId('tahun_kerja_id')->constrained('tahun_kerjas')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerjas')->cascadeOnDelete();
            $table->foreignId('bidang_id')->constrained('bidangs')->cascadeOnDelete();
            $table->foreignId('kategori_id')->constrained('kategoris')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignId('rekening_id')->nullable()->constrained('rekenings')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('aktifitas')->nullable();
            $table->text('indikator')->nullable();
            $table->string('nilai_standar')->nullable();
            $table->string('satuan_nilai_standar')->nullable();
            $table->string('target')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penawaran_program_kerjas');
    }
};
