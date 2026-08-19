<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Jadwal pencairan dialamatkan lewat uuid, bukan id yang berurutan, sehingga
 * tautan detailnya tidak membocorkan jumlah jadwal yang pernah dibuat. Kolom uuid
 * diisikan dulu untuk seluruh jadwal yang sudah ada sebelum dijadikan unik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_pencairans', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        $this->isiUuidJadwalLama();

        Schema::table('jadwal_pencairans', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_pencairans', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });

        Schema::table('jadwal_pencairans', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }

    /**
     * Jadwal yang dibuat sebelum migrasi ini belum punya uuid; masing-masing
     * diberi satu nilai baru agar kolomnya bisa dijadikan kunci rute.
     */
    private function isiUuidJadwalLama(): void
    {
        DB::table('jadwal_pencairans')
            ->whereNull('uuid')
            ->orderBy('id')
            ->each(fn (object $jadwal) => DB::table('jadwal_pencairans')
                ->where('id', $jadwal->id)
                ->update(['uuid' => (string) Str::orderedUuid()]));
    }
};
