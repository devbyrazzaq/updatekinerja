<?php

namespace App\Filament\Forms\Components;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Catatan revisi verifikator, ditampilkan di bagian paling atas modal perbaikan agar
 * unit kerja tahu persis apa yang perlu diperbaiki tanpa membuka riwayat komentar.
 * Kosong (tidak menghasilkan komponen apa pun) bila tidak ada catatan.
 */
class CatatanRevisi
{
    /**
     * Berlaku untuk model apa pun yang menyimpan catatan verifikator pada atribut
     * `catatan_verifikasi` — realisasi program kerja maupun pemasukan unit.
     *
     * @return array<int, Section>
     */
    public static function komponen(Model $record): array
    {
        if (blank($record->catatan_verifikasi)) {
            return [];
        }

        return [
            Section::make('Catatan Revisi')
                ->description('Perbaikan yang diminta verifikator.')
                ->icon('heroicon-o-pencil-square')
                ->schema([
                    Text::make(new HtmlString($record->catatan_verifikasi)),
                ])
                ->columnSpanFull(),
        ];
    }
}
