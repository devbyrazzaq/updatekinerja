<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realisasi_program_kerjas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pengajuan_program_kerja_id')->constrained('pengajuan_program_kerjas')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->dateTime('start_datetime')->nullable();
            $table->dateTime('end_datetime')->nullable();
            $table->decimal('anggaran_digunakan', 18, 2)->default(0);
            $table->string('status')->default('draft');
            $table->text('catatan_verifikasi')->nullable();

            $table->decimal('nominal_disetujui_rektor', 18, 2)->nullable();
            $table->dateTime('disetujui_rektor_at')->nullable();
            $table->foreignId('rektor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('nominal_disetujui_wakil', 18, 2)->nullable();
            $table->dateTime('disetujui_wakil_at')->nullable();
            $table->foreignId('wakil_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status_pencairan')->nullable();
            $table->date('jadwal_pencairan')->nullable();
            $table->dateTime('dicairkan_at')->nullable();
            $table->foreignId('keuangan_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('laporan_path')->nullable();
            $table->dateTime('laporan_diserahkan_at')->nullable();
            $table->dateTime('laporan_disetujui_at')->nullable();
            $table->foreignId('verifikator_laporan_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realisasi_program_kerjas');
    }
};
