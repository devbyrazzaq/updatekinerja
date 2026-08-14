<?php

namespace App\Providers;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Table::configureUsing(fn (Table $table) => $table
            ->defaultCurrency('IDR')
            ->defaultNumberLocale('id'));

        // Seluruh kolom teks tabel membungkus barisnya secara bawaan supaya teks
        // panjang — nama program kerja, indikator, keterangan — tampil utuh dalam
        // beberapa baris alih-alih memaksa tabel melebar. Kolom yang perlu tetap satu
        // baris bisa keluar dari aturan ini dengan ->wrap(false).
        TextColumn::configureUsing(fn (TextColumn $column) => $column->wrap());

        Schema::configureUsing(fn (Schema $schema) => $schema
            ->defaultCurrency('IDR')
            ->defaultNumberLocale('id'));
    }
}
