<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pengajuan_program_kerjas', function (Blueprint $table) {
            $table->dateTime('estimasi_mulai')->nullable()->after('deskripsi_kegiatan');
            $table->dateTime('estimasi_selesai')->nullable()->after('estimasi_mulai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengajuan_program_kerjas', function (Blueprint $table) {
            $table->dropColumn(['estimasi_mulai', 'estimasi_selesai']);
        });
    }
};
