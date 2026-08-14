<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemasukans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerjas')->cascadeOnDelete();
            $table->string('sumber');
            $table->foreignId('pengajuan_program_kerja_id')->nullable()->constrained('pengajuan_program_kerjas')->nullOnDelete();
            $table->foreignId('realisasi_program_kerja_id')->nullable()->constrained('realisasi_program_kerjas')->nullOnDelete();
            $table->string('rincian_kegiatan');
            $table->date('tanggal_pelaksanaan');
            $table->decimal('nominal_pendapatan', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemasukans');
    }
};
