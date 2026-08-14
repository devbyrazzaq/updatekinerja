<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acuan_program_kerjas', function (Blueprint $table) {
            $table->foreignId('kelompok_acuan_id')
                ->nullable()
                ->after('id')
                ->constrained('kelompok_acuans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('acuan_program_kerjas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kelompok_acuan_id');
        });
    }
};
