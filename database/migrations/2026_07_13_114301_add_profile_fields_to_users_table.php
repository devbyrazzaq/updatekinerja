<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->date('birth_date')->nullable()->after('phone');
            $table->foreignId('unit_kerja_id')->nullable()->after('birth_date')->constrained('unit_kerjas')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('unit_kerja_id');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_kerja_id');
            $table->dropColumn(['username', 'phone', 'birth_date', 'is_active']);
        });
    }
};
