<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu realisasi dapat memiliki banyak dokumen proposal maupun laporan. Berkas
 * lama pada kolom proposal_path/laporan_path disalin sebagai dokumen pertama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realisasi_dokumens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('realisasi_program_kerja_id')->constrained('realisasi_program_kerjas')->cascadeOnDelete();
            $table->string('type');
            $table->string('path');
            $table->string('name')->nullable();
            $table->dateTime('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['realisasi_program_kerja_id', 'type']);
        });

        foreach (['proposal_path' => 'proposal', 'laporan_path' => 'laporan'] as $column => $type) {
            DB::table('realisasi_program_kerjas')
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->select('id', $column, 'created_at')
                ->orderBy('id')
                ->each(function (object $realisasi) use ($column, $type): void {
                    DB::table('realisasi_dokumens')->insert([
                        'realisasi_program_kerja_id' => $realisasi->id,
                        'type' => $type,
                        'path' => $realisasi->{$column},
                        'name' => basename((string) $realisasi->{$column}),
                        'uploaded_at' => $realisasi->created_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('realisasi_dokumens');
    }
};
