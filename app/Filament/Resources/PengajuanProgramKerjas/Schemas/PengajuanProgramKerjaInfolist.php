<?php

namespace App\Filament\Resources\PengajuanProgramKerjas\Schemas;

use App\Enums\EnumStatusPengajuan;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Number;
use Livewire\Component;

class PengajuanProgramKerjaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.infolists.pengajuan-stepper')
                    ->columnSpanFull(),
                Grid::make(2)
                    ->schema([
                        Group::make()
                            ->columnSpan(1)
                            ->schema([
                                Section::make('Detail Pengaju')
                                    ->icon('heroicon-o-user-circle')
                                    ->schema([
                                        View::make('filament.infolists.pengajuan-pengaju'),
                                    ]),
                                Section::make('Besaran Pengajuan')
                                    ->icon('heroicon-o-banknotes')
                                    ->schema([
                                        TextEntry::make('alokasi_anggaran')
                                            ->hiddenLabel()
                                            ->money('IDR')
                                            ->size('text-3xl')
                                            ->weight(FontWeight::Bold)
                                            ->color('primary')
                                            ->alignEnd()
                                            ->columnSpanFull(),
                                        TextEntry::make('persentase_pagu')
                                            ->hiddenLabel()
                                            ->state(fn (PengajuanProgramKerja $record): string => static::deskripsiPersentasePagu($record))
                                            ->color('gray')
                                            ->alignEnd()
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Informasi Pengajuan')
                                    ->columns(2)
                                    ->headerActions([
                                        static::detailProgramKerjaAction(),
                                    ])
                                    ->schema([
                                        TextEntry::make('penawaranProgramKerja.name')->label('Program Kerja'),
                                        TextEntry::make('status')->label('Status')->badge(),
                                        TextEntry::make('unitKerja.name')->label('Unit Kerja'),
                                        TextEntry::make('alokasi_anggaran')->label('Pengajuan Anggaran')->money('IDR'),
                                        TextEntry::make('estimasi_mulai')->label('Estimasi Mulai')->dateTime('d F Y H:i')->placeholder('-'),
                                        TextEntry::make('estimasi_selesai')->label('Estimasi Selesai')->dateTime('d F Y H:i')->placeholder('-'),
                                        TextEntry::make('deskripsi_kegiatan')->label('Deskripsi Kegiatan')->html()->placeholder('-')->columnSpanFull(),
                                    ]),
                            ]),
                        Group::make()
                            ->columnSpan(1)
                            ->schema([
                                Section::make('Komentar')
                                    ->description('Catatan dari verifikator.')
                                    ->icon('heroicon-o-chat-bubble-left-right')
                                    ->headerActions([
                                        static::tambahKomentarAction(),
                                    ])
                                    ->schema([
                                        View::make('filament.infolists.pengajuan-comments'),
                                    ]),
                                Section::make('Mutasi Anggaran')
                                    ->description('Pergerakan anggaran realisasi pengajuan ini pada buku anggaran unit kerja.')
                                    ->icon('heroicon-o-book-open')
                                    ->collapsible()
                                    ->schema([
                                        View::make('filament.infolists.buku-anggaran'),
                                    ]),
                                Section::make('Log')
                                    ->description('Riwayat aktivitas pengajuan.')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        View::make('filament.infolists.pengajuan-logs'),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    /**
     * Kalimat yang menjelaskan porsi alokasi pengajuan ini terhadap total pagu
     * anggaran unit kerja pada tahun kerja yang bersangkutan.
     */
    protected static function deskripsiPersentasePagu(PengajuanProgramKerja $record): string
    {
        $totalPagu = static::totalPaguAnggaran($record);
        $nominalPagu = Number::currency($totalPagu, 'IDR', 'id');

        if ($totalPagu <= 0.0) {
            return 'Total pagu anggaran belum ditetapkan untuk unit kerja ini.';
        }

        $persentase = (float) $record->alokasi_anggaran / $totalPagu * 100;

        return Number::format($persentase, precision: 2, locale: 'id').'% dari total pagu anggaran '.$nominalPagu.'.';
    }

    /**
     * Total pagu anggaran unit kerja pengajuan pada tahun kerja program yang diajukan.
     */
    protected static function totalPaguAnggaran(PengajuanProgramKerja $record): float
    {
        $tahunKerjaId = $record->penawaranProgramKerja?->tahun_kerja_id;

        if ($record->unit_kerja_id === null || $tahunKerjaId === null) {
            return 0.0;
        }

        return (float) (PaguAnggaran::query()
            ->where('tahun_kerja_id', $tahunKerjaId)
            ->where('unit_kerja_id', $record->unit_kerja_id)
            ->value('amount') ?? 0);
    }

    /**
     * Menampilkan rincian program kerja yang diajukan di dalam panel slide-over.
     */
    protected static function detailProgramKerjaAction(): Action
    {
        return Action::make('detailProgramKerja')
            ->label('Detail Program Kerja')
            ->icon('heroicon-o-clipboard-document-list')
            ->color('gray')
            ->outlined()
            ->slideOver()
            ->modalHeading('Detail Program Kerja')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->visible(fn (PengajuanProgramKerja $record): bool => $record->penawaranProgramKerja !== null)
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('detail_name')
                            ->label('Nama Program Kerja')
                            ->state(fn (PengajuanProgramKerja $record): ?string => $record->penawaranProgramKerja?->name)
                            ->columnSpanFull(),
                        TextEntry::make('detail_tahun_kerja')
                            ->label('Tahun Kerja')
                            ->state(fn (PengajuanProgramKerja $record): ?string => $record->penawaranProgramKerja?->tahunKerja?->name)
                            ->placeholder('-'),
                        TextEntry::make('detail_unit_kerja')
                            ->label('Unit Kerja')
                            ->state(fn (PengajuanProgramKerja $record): ?string => $record->penawaranProgramKerja?->unitKerja?->name)
                            ->placeholder('-'),
                        TextEntry::make('detail_bidang')
                            ->label('Bidang')
                            ->state(fn (PengajuanProgramKerja $record): ?string => $record->penawaranProgramKerja?->bidang?->name)
                            ->placeholder('-'),
                        TextEntry::make('detail_kategori')
                            ->label('Kategori')
                            ->state(fn (PengajuanProgramKerja $record): ?string => $record->penawaranProgramKerja?->kategori?->name)
                            ->placeholder('-'),
                        TextEntry::make('detail_program')
                            ->label('Program Induk')
                            ->state(fn (PengajuanProgramKerja $record): ?string => $record->penawaranProgramKerja?->program?->name)
                            ->placeholder('-'),
                        TextEntry::make('detail_rekening')
                            ->label('Kode Akun')
                            ->state(fn (PengajuanProgramKerja $record): ?string => $record->penawaranProgramKerja?->rekening?->code)
                            ->placeholder('-'),
                        TextEntry::make('detail_target')
                            ->label('Target')
                            ->state(fn (PengajuanProgramKerja $record): ?string => $record->penawaranProgramKerja?->target)
                            ->placeholder('-'),
                        TextEntry::make('detail_nilai_standar')
                            ->label('Nilai Standar')
                            ->state(fn (PengajuanProgramKerja $record): ?string => static::nilaiStandar($record->penawaranProgramKerja))
                            ->placeholder('-'),
                        TextEntry::make('detail_aktifitas')
                            ->label('Aktivitas')
                            ->state(fn (PengajuanProgramKerja $record): ?string => $record->penawaranProgramKerja?->aktifitas)
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('detail_indikator')
                            ->label('Indikator')
                            ->state(fn (PengajuanProgramKerja $record): ?string => $record->penawaranProgramKerja?->indikator)
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Menggabungkan nilai standar dengan satuannya bila tersedia.
     */
    protected static function nilaiStandar(?PenawaranProgramKerja $penawaran): ?string
    {
        if ($penawaran === null || blank($penawaran->nilai_standar)) {
            return null;
        }

        return trim($penawaran->nilai_standar.' '.($penawaran->satuan_nilai_standar ?? ''));
    }

    /**
     * Komentar boleh ditambahkan pengguna kapan pun selama pengajuan belum
     * ditolak, lalu memicu penyegaran daftar komentar dan log.
     */
    protected static function tambahKomentarAction(): Action
    {
        return Action::make('tambahKomentar')
            ->label('Tambah Komentar')
            ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
            ->visible(fn (PengajuanProgramKerja $record): bool => $record->status !== EnumStatusPengajuan::Ditolak)
            ->modalHeading('Tambah Komentar')
            ->modalSubmitActionLabel('Kirim')
            ->schema([
                RichEditor::make('catatan')
                    ->label('Komentar')
                    ->required(),
            ])
            ->action(function (array $data, PengajuanProgramKerja $record, Component $livewire): void {
                $record->catatKomentar($data['catatan'], auth()->id());

                $livewire->dispatch('komentar-ditambahkan');

                Notification::make()->title('Komentar berhasil ditambahkan')->success()->send();
            });
    }
}
