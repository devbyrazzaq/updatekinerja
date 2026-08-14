<?php

namespace App\Filament\Resources\Pemasukans\Schemas;

use App\Enums\EnumSumberPemasukan;
use App\Filament\Actions\MediaAction;
use App\Models\Pemasukan;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PemasukanInfolist
{
    /**
     * Dipakai bersama oleh halaman detail milik unit kerja dan ketiga Resource
     * verifikasi. Parameter `$verifikasiResource` mengikuti pola infolist realisasi
     * sebagai penanda dari mana infolist ini dibuka.
     */
    public static function configure(Schema $schema, ?string $verifikasiResource = null): Schema
    {
        return $schema
            ->components([
                View::make('filament.infolists.pemasukan-stepper')
                    ->columnSpanFull(),
                Section::make('Informasi Pemasukan')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('unitKerja.name')->label('Unit Kerja'),
                        TextEntry::make('sumber')->label('Jenis Sumber')->badge(),
                        TextEntry::make('referensi')
                            ->label('Program Kerja')
                            ->state(fn (Pemasukan $record): string => match ($record->sumber) {
                                EnumSumberPemasukan::Pengajuan => $record->pengajuanProgramKerja?->penawaranProgramKerja?->name ?? '-',
                                EnumSumberPemasukan::Realisasi => $record->realisasiProgramKerja?->name ?? '-',
                                default => '-',
                            })
                            ->columnSpanFull(),
                        TextEntry::make('rincian_kegiatan')->label('Rincian Kegiatan')->columnSpanFull(),
                        TextEntry::make('jenis_waktu')->label('Jenis Waktu')->badge(),
                        TextEntry::make('periode')
                            ->label('Periode Pelaksanaan')
                            ->state(fn (Pemasukan $record): string => $record->labelPeriode()),
                        TextEntry::make('nominal_pendapatan')->label('Nominal Pendapatan')->money('IDR'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (Pemasukan $record): string => $record->labelStatus())
                            ->color(fn (Pemasukan $record): string => $record->status->getColor()),
                        TextEntry::make('catatan_verifikasi')
                            ->label('Catatan Verifikator')
                            ->html()
                            ->placeholder('-')
                            ->visible(fn (Pemasukan $record): bool => filled($record->catatan_verifikasi))
                            ->columnSpanFull(),
                        TextEntry::make('keterangan')->label('Keterangan')->html()->placeholder('-')->columnSpanFull(),
                        TextEntry::make('pengaju.name')->label('Dicatat Oleh')->placeholder('-'),
                        TextEntry::make('created_at')->label('Dicatat')->dateTime('d F Y H:i'),
                        TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d F Y H:i'),
                    ]),
                Section::make('Riwayat Verifikasi')
                    ->description('Persetujuan tiap tahap beserta waktunya.')
                    ->icon('heroicon-o-shield-check')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('rektor.name')->label('Rektor')->placeholder('Belum diverifikasi'),
                        TextEntry::make('disetujui_rektor_at')->label('Waktu')->dateTime('d F Y H:i')->placeholder('-'),
                        TextEntry::make('wakil.name')->label('Wakil Rektor')->placeholder('Belum diverifikasi'),
                        TextEntry::make('disetujui_wakil_at')->label('Waktu')->dateTime('d F Y H:i')->placeholder('-'),
                        TextEntry::make('keuangan.name')->label('Biro Keuangan')->placeholder('Belum diverifikasi'),
                        TextEntry::make('disetujui_keuangan_at')->label('Waktu')->dateTime('d F Y H:i')->placeholder('-'),
                    ]),
                Section::make('Bukti Tanda Terima')
                    ->description('Berkas yang mengesahkan pemasukan ini.')
                    ->icon('heroicon-o-paper-clip')
                    ->columnSpanFull()
                    ->columns(2)
                    ->visible(fn (Pemasukan $record): bool => filled($record->bukti_path))
                    ->headerActions([
                        MediaAction::make('pratinjauBukti')
                            ->label('Pratinjau')
                            ->icon(Heroicon::OutlinedEye)
                            ->color('gray')
                            ->path('bukti_path'),
                    ])
                    ->schema([
                        TextEntry::make('bukti_original_names')
                            ->label('Berkas')
                            ->state(fn (Pemasukan $record): string => static::daftarBerkas($record))
                            ->columnSpanFull(),
                        TextEntry::make('bukti_diserahkan_at')->label('Diserahkan')->dateTime('d F Y H:i')->placeholder('-'),
                        TextEntry::make('divalidasi_at')->label('Dinyatakan Valid')->dateTime('d F Y H:i')->placeholder('-'),
                    ]),
            ]);
    }

    /**
     * Nama berkas bukti sebagaimana diunggah unit kerja; jatuh kembali ke nama file
     * pada disk bila nama aslinya tidak tersimpan.
     */
    protected static function daftarBerkas(Pemasukan $record): string
    {
        $nama = array_values($record->bukti_original_names ?? []);

        if ($nama === []) {
            $nama = array_map(fn (string $path): string => basename($path), array_values($record->bukti_path ?? []));
        }

        return $nama === [] ? '-' : implode(', ', $nama);
    }
}
