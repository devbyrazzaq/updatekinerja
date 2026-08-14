<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_program_kerjas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('penawaran_program_kerja_id')->constrained('penawaran_program_kerjas')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerjas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('alokasi_anggaran', 18, 2)->default(0);
            $table->text('deskripsi_kegiatan')->nullable();
            $table->string('status')->default('draft');
            $table->text('catatan_verifikasi')->nullable();
            $table->dateTime('diverifikasi_at')->nullable();
            $table->foreignId('verifikator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_program_kerjas');
    }
};
