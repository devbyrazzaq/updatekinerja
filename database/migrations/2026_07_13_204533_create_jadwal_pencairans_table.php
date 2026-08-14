<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_pencairans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('realisasi_program_kerja_id')->constrained('realisasi_program_kerjas')->cascadeOnDelete();
            $table->date('tanggal_pencairan');
            $table->decimal('nominal', 18, 2)->nullable();
            $table->string('status')->default('dijadwalkan');
            $table->text('catatan')->nullable();
            $table->dateTime('dicairkan_at')->nullable();
            $table->foreignId('keuangan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_pencairans');
    }
};
