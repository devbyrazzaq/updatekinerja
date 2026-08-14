<?php

namespace App\Filament\Resources\JadwalPencairans\Schemas;

use App\Models\JadwalPencairan;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class JadwalPencairanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Total Akan Dicairkan')
                            ->icon('heroicon-o-banknotes')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('total_nominal')
                                    ->hiddenLabel()
                                    ->state(fn (JadwalPencairan $record): float => $record->totalNominal())
                                    ->money('IDR')
                                    ->size('text-3xl')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary')
                                    ->columnSpanFull(),
                                TextEntry::make('ringkasan_realisasi')
                                    ->hiddenLabel()
                                    ->state(fn (JadwalPencairan $record): string => static::deskripsiRealisasi($record))
                                    ->color('gray')
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Detail Jadwal Pencairan')
                            ->icon('heroicon-o-calendar-date-range')
                            ->columnSpan(2)
                            ->columns(2)
                            ->schema([
                                TextEntry::make('name')->label('Nama Jadwal')->columnSpanFull(),
                                TextEntry::make('tanggal_pencairan')->label('Tanggal Pencairan')->date('d F Y'),
                                TextEntry::make('status')->label('Status')->badge(),
                                TextEntry::make('tahunKerja.name')->label('Tahun Kerja')->placeholder('-'),
                                TextEntry::make('dicairkan_at')->label('Dicairkan')->dateTime('d F Y H:i')->placeholder('-'),
                                TextEntry::make('keuangan.name')->label('Diproses Oleh')->placeholder('-'),
                                TextEntry::make('catatan')->label('Catatan')->html()->placeholder('-')->columnSpanFull(),
                                TextEntry::make('created_at')->label('Dibuat')->dateTime('d F Y H:i'),
                                TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d F Y H:i'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Ringkasan anggota jadwal: jumlah realisasi beserta yang anggarannya belum cair.
     */
    protected static function deskripsiRealisasi(JadwalPencairan $record): string
    {
        $jumlah = $record->jumlahRealisasi();

        if ($jumlah === 0) {
            return 'Belum ada realisasi yang dijadwalkan pada tanggal ini.';
        }

        $belumCair = $record->realisasiBelumDicairkan()->count();

        return $belumCair === 0
            ? "{$jumlah} realisasi, seluruh anggarannya sudah diserahkan."
            : "{$jumlah} realisasi, {$belumCair} di antaranya belum menerima anggaran.";
    }
}
