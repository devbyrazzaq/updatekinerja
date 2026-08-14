<?php

namespace App\Filament\Resources\RealisasiProgramKerjas\Schemas;

use App\Enums\EnumCaraPenyelesaianAnggaran;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Filament\Actions\MediaAction;
use App\Filament\Resources\VerifikasiBiroKeuangans\VerifikasiBiroKeuanganResource;
use App\Models\RealisasiProgramKerja;
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
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

class RealisasiProgramKerjaInfolist
{
    public static function configure(Schema $schema, ?string $verifikasiResource = null): Schema
    {
        $sembunyikanBesaranRealisasi = $verifikasiResource === VerifikasiBiroKeuanganResource::class;

        return $schema
            ->components([
                View::make('filament.infolists.realisasi-stepper')
                    ->columnSpanFull(),
                Grid::make(2)
                    ->schema([
                        Group::make()
                            ->columnSpan(1)
                            ->schema([
                                // Dokumen proposal diletakkan paling atas karena itulah berkas
                                // pertama yang dibuka verifikator saat meninjau realisasi.
                                static::dokumenSection('Dokumen Proposal', 'proposals', 'proposal_path', 'proposal', 'heroicon-o-document-text'),
                                Section::make('Detail Pengaju')
                                    ->icon('heroicon-o-user-circle')
                                    ->schema([
                                        View::make('filament.infolists.realisasi-pengaju'),
                                    ]),
                                Section::make('Besaran Realisasi')
                                    ->icon('heroicon-o-banknotes')
                                    ->hidden($sembunyikanBesaranRealisasi)
                                    ->schema([
                                        TextEntry::make('anggaran_digunakan')
                                            ->hiddenLabel()
                                            ->money('IDR')
                                            ->size('text-3xl')
                                            ->weight(FontWeight::Bold)
                                            ->color('primary')
                                            ->alignEnd()
                                            ->columnSpanFull(),
                                        TextEntry::make('ringkasan_anggaran')
                                            ->hiddenLabel()
                                            ->state(fn (RealisasiProgramKerja $record): string => static::deskripsiAnggaran($record))
                                            ->color('gray')
                                            ->alignEnd()
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Informasi Realisasi')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('name')->label('Nama Kegiatan')->columnSpanFull(),
                                        TextEntry::make('pengajuanProgramKerja.penawaranProgramKerja.name')->label('Program Kerja'),
                                        TextEntry::make('pengajuanProgramKerja.unitKerja.name')->label('Unit Kerja'),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (RealisasiProgramKerja $record): string => $record->labelStatus())
                                            ->color(fn (RealisasiProgramKerja $record): string => $record->status->getColor()),
                                        TextEntry::make('urgensi')->label('Urgensi')->badge(),
                                        TextEntry::make('start_datetime')->label('Mulai')->dateTime('d F Y H:i')->placeholder('-'),
                                        TextEntry::make('end_datetime')->label('Selesai')->dateTime('d F Y H:i')->placeholder('-'),
                                        TextEntry::make('nominal_diajukan')
                                            ->label('Nominal Diajukan')
                                            ->state(fn (RealisasiProgramKerja $record): float => $record->nominalDiajukan())
                                            ->money('IDR'),
                                        TextEntry::make('nominal_disetujui')
                                            ->label('Nominal Disetujui')
                                            ->money('IDR')
                                            ->placeholder('Belum disetujui'),
                                        TextEntry::make('anggaran_digunakan')->label('Realisasi Akhir')->money('IDR'),
                                        TextEntry::make('persentase_anggaran')
                                            ->label('Persentase Anggaran')
                                            ->state(fn (RealisasiProgramKerja $record): string => static::deskripsiPersentaseAnggaran($record))
                                            ->helperText('Anggaran digunakan terhadap alokasi pengajuan.'),
                                        TextEntry::make('description')->label('Deskripsi')->html()->placeholder('-')->columnSpanFull(),
                                    ]),
                                static::targetSection(),
                                static::pencairanSection(),
                                // Section 'Verifikasi & Pencairan' disembunyikan sementara.
                            ]),
                        Group::make()
                            ->columnSpan(1)
                            ->schema(array_values(array_filter([
                                // Laporan yang sedang dinilai ditaruh paling atas kolom kanan:
                                // berkasnya lebih dulu, lalu ringkasan isinya, baru keputusan
                                // nominal yang menyertainya.
                                static::dokumenSection('Dokumen Laporan', 'laporans', 'laporan_path', 'laporan', 'heroicon-o-document-check')
                                    ->visible(fn (RealisasiProgramKerja $record): bool => $record->sudahAdaLaporan()),
                                static::laporanRealisasiSection(),
                                static::persetujuanAnggaranSection(),
                                Section::make('Komentar')
                                    ->description('Diskusi antara unit kerja dan verifikator.')
                                    ->icon('heroicon-o-chat-bubble-left-right')
                                    ->headerActions([
                                        static::tambahKomentarAction(),
                                    ])
                                    ->schema([
                                        View::make('filament.infolists.realisasi-comments'),
                                    ]),
                                Section::make('Mutasi Anggaran')
                                    ->description('Pergerakan anggaran realisasi ini pada buku anggaran unit kerja.')
                                    ->icon('heroicon-o-book-open')
                                    ->collapsible()
                                    ->schema([
                                        View::make('filament.infolists.buku-anggaran'),
                                    ]),
                                Section::make('Log')
                                    ->description('Riwayat aktivitas realisasi.')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        View::make('filament.infolists.realisasi-logs'),
                                    ]),
                            ]))),
                    ])->columnSpanFull(),
            ]);
    }

    /**
     * Section target program kerja: acuan penilaian ketercapaian yang diambil dari
     * penawaran program kerja induk pengajuan, dilengkapi capaian terakhir dari
     * realisasi lain atas pengajuan yang sama sebagai batas bawah laporan ini.
     */
    protected static function targetSection(): Section
    {
        return Section::make('Target Program Kerja')
            ->description('Acuan penilaian ketercapaian dari pengajuan program kerja.')
            ->icon('heroicon-o-flag')
            ->columns(2)
            ->schema([
                TextEntry::make('target_program')
                    ->label('Target')
                    ->state(fn (RealisasiProgramKerja $record): ?string => $record->targetProgramKerja()['target'])
                    ->placeholder('-'),
                TextEntry::make('nilai_standar_program')
                    ->label('Nilai Standar')
                    ->state(fn (RealisasiProgramKerja $record): ?string => $record->targetProgramKerja()['nilai_standar'])
                    ->placeholder('-'),
                TextEntry::make('indikator_program')
                    ->label('Indikator')
                    ->state(fn (RealisasiProgramKerja $record): ?string => $record->targetProgramKerja()['indikator'])
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('aktifitas_program')
                    ->label('Aktivitas')
                    ->state(fn (RealisasiProgramKerja $record): ?string => $record->targetProgramKerja()['aktifitas'])
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('ketercapaian_sebelumnya')
                    ->label('Ketercapaian Terakhir')
                    ->state(fn (RealisasiProgramKerja $record): string => $record->persentaseKetercapaianMinimum().'%')
                    ->helperText('Capaian tertinggi dari realisasi lain atas pengajuan yang sama; menjadi batas bawah laporan ini.')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Section laporan realisasi: ringkasan isi laporan yang dikirim unit kerja beserta
     * penyerapan anggarannya. Bila ada selisih, cara penyelesaiannya ikut ditampilkan
     * sebagai keputusan verifikator laporan.
     */
    protected static function laporanRealisasiSection(): Section
    {
        return Section::make('Laporan Realisasi')
            ->description('Laporan pelaksanaan yang dikirim unit kerja.')
            ->icon('heroicon-o-clipboard-document-list')
            ->columns(2)
            ->visible(fn (RealisasiProgramKerja $record): bool => $record->sudahAdaLaporan())
            ->schema([
                TextEntry::make('status_anggaran')
                    ->label('Status Anggaran')
                    ->badge()
                    ->placeholder('-')
                    ->helperText(fn (RealisasiProgramKerja $record): string => 'Anggaran diterima Rp '.number_format($record->nominalDiterima(), 0, ',', '.').', realisasi akhir Rp '.number_format((float) $record->anggaran_digunakan, 0, ',', '.').'.'),
                TextEntry::make('persentase_ketercapaian')
                    ->label('Ketercapaian Target')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : "{$state}%")
                    ->placeholder('-'),
                TextEntry::make('nominal_selisih_anggaran')
                    ->label(fn (RealisasiProgramKerja $record): string => $record->status_anggaran?->labelSelisih() ?? 'Selisih Anggaran')
                    ->money('IDR')
                    ->placeholder('-')
                    ->visible(fn (RealisasiProgramKerja $record): bool => $record->status_anggaran?->memerlukanSelisih() ?? false),
                TextEntry::make('status_penyelesaian_anggaran')
                    ->label('Tindak Lanjut Selisih')
                    ->badge()
                    ->placeholder('-')
                    ->helperText(fn (RealisasiProgramKerja $record): ?string => $record->status_penyelesaian_anggaran?->getDescription())
                    ->visible(fn (RealisasiProgramKerja $record): bool => $record->status_anggaran?->memerlukanSelisih() ?? false),
                TextEntry::make('cara_penyelesaian_anggaran')
                    ->label(fn (RealisasiProgramKerja $record): string => EnumCaraPenyelesaianAnggaran::labelUntuk(
                        $record->status_anggaran ?? EnumStatusAnggaran::Habis,
                    ))
                    ->badge()
                    ->placeholder('Belum ditetapkan verifikator')
                    ->helperText(fn (RealisasiProgramKerja $record): ?string => $record->cara_penyelesaian_anggaran?->getDescription())
                    ->visible(fn (RealisasiProgramKerja $record): bool => $record->status_anggaran?->memerlukanSelisih() ?? false)
                    ->columnSpanFull(),
                TextEntry::make('evaluasi_pengerjaan')
                    ->label('Evaluasi Pengerjaan')
                    ->html()
                    ->placeholder('-')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Section pencairan anggaran: gelombang pencairan tempat realisasi dijadwalkan,
     * beserta cara anggaran diserahkan bila sudah cair. Tampil setelah realisasi
     * masuk salah satu jadwal pencairan.
     */
    protected static function pencairanSection(): Section
    {
        return Section::make('Pencairan Anggaran')
            ->icon('heroicon-o-calendar-date-range')
            ->columns(2)
            ->visible(fn (RealisasiProgramKerja $record): bool => $record->jadwal_pencairan_id !== null)
            ->schema([
                TextEntry::make('jadwalPencairan.name')->label('Jadwal Pencairan'),
                TextEntry::make('jadwalPencairan.tanggal_pencairan')->label('Tanggal Pencairan')->date('d F Y'),
                TextEntry::make('status_pencairan')->label('Status Pencairan')->badge()->placeholder('-'),
                TextEntry::make('dicairkan_at')->label('Dicairkan')->dateTime('d F Y H:i')->placeholder('Belum dicairkan'),
                TextEntry::make('metode_pembayaran')
                    ->label('Tipe Pembayaran')
                    ->badge()
                    ->placeholder('-')
                    ->visible(fn (RealisasiProgramKerja $record): bool => $record->metode_pembayaran !== null),
                TextEntry::make('rekening_tujuan')
                    ->label('Rekening Tujuan')
                    ->state(fn (RealisasiProgramKerja $record): ?string => $record->rekeningBank?->label())
                    ->placeholder('Belum ditentukan')
                    ->visible(fn (RealisasiProgramKerja $record): bool => $record->metode_pembayaran?->isTransfer() ?? false),
            ]);
    }

    /**
     * Section daftar berkas (proposal/laporan) satu realisasi. Daftarnya dirender
     * oleh Livewire component `realisasi-program-kerja-documents` agar tiap berkas
     * tampil sebagai "nama (ukuran)" dengan tanggal unggah di bawahnya, dan tombol
     * pratinjau di header membuka modal preview berkas.
     */
    protected static function dokumenSection(string $heading, string $relationship, string $attribute, string $emptyLabel, string $icon): Section
    {
        return Section::make($heading)
            ->icon($icon)
            ->collapsible()
            ->headerActions([
                MediaAction::make('pratinjau_'.$relationship)
                    ->label('Pratinjau')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->path($attribute),
            ])
            ->schema([
                View::make('filament.infolists.realisasi-documents')
                    ->viewData([
                        'relationship' => $relationship,
                        'emptyLabel' => "Belum ada {$emptyLabel}.",
                    ]),
            ]);
    }

    /**
     * Kalimat ringkas anggaran: alokasi pengajuan dan sisa terhadap pemakaian ini.
     */
    protected static function deskripsiAnggaran(RealisasiProgramKerja $record): string
    {
        $alokasi = (float) $record->alokasiAnggaran();
        $selisih = $record->selisihAnggaran();
        $keterangan = 'Alokasi pengajuan Rp '.number_format($alokasi, 0, ',', '.').'.';

        if ($selisih >= 0) {
            return $keterangan.' Sisa Rp '.number_format($selisih, 0, ',', '.').'.';
        }

        return $keterangan.' Melebihi alokasi Rp '.number_format(abs($selisih), 0, ',', '.').'.';
    }

    /**
     * Komentar boleh ditambahkan pengguna kapan pun selama realisasi belum ditolak,
     * lalu memicu penyegaran daftar komentar dan log.
     */
    protected static function tambahKomentarAction(): Action
    {
        return Action::make('tambahKomentar')
            ->label('Tambah Komentar')
            ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
            ->visible(fn (RealisasiProgramKerja $record): bool => $record->status !== EnumStatusRealisasi::Ditolak)
            ->modalHeading('Tambah Komentar')
            ->modalSubmitActionLabel('Kirim')
            ->schema([
                RichEditor::make('catatan')
                    ->label('Komentar')
                    ->required(),
            ])
            ->action(function (array $data, RealisasiProgramKerja $record, Component $livewire): void {
                $record->catatKomentar($data['catatan'], auth()->id());

                $livewire->dispatch('komentar-realisasi-ditambahkan');

                Notification::make()->title('Komentar berhasil ditambahkan')->success()->send();
            });
    }

    /**
     * Persentase anggaran yang digunakan terhadap alokasi pengajuan induk.
     */
    protected static function deskripsiPersentaseAnggaran(RealisasiProgramKerja $record): string
    {
        $alokasi = (float) $record->alokasiAnggaran();

        if ($alokasi <= 0) {
            return '-';
        }

        $persentase = round((float) $record->anggaran_digunakan / $alokasi * 100);

        return "{$persentase}%";
    }

    /**
     * Section persetujuan anggaran (bergaya seperti 'Besaran Realisasi'): menonjolkan
     * nominal yang disetujui verifikator beserta persentasenya terhadap yang diajukan.
     */
    protected static function persetujuanAnggaranSection(): Section
    {
        return Section::make('Persetujuan Anggaran')
            ->icon('heroicon-o-check-badge')
            ->schema([
                TextEntry::make('nominal_disetujui')
                    ->hiddenLabel()
                    ->money('IDR')
                    ->size('text-3xl')
                    ->weight(FontWeight::Bold)
                    ->color('primary')
                    ->placeholder('Belum disetujui')
                    ->alignEnd()
                    ->columnSpanFull(),
                TextEntry::make('ringkasan_persetujuan')
                    ->hiddenLabel()
                    ->state(fn (RealisasiProgramKerja $record): string => static::deskripsiPersetujuan($record))
                    ->color('gray')
                    ->alignEnd()
                    ->columnSpanFull(),
                TextEntry::make('penentu_nominal')
                    ->label('Ditetapkan oleh')
                    ->icon('heroicon-o-user')
                    ->state(fn (RealisasiProgramKerja $record): ?string => static::deskripsiPenentuNominal($record))
                    ->visible(fn (RealisasiProgramKerja $record): bool => $record->penentu_nominal_id !== null)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Kalimat ringkas persetujuan: persentase nominal disetujui terhadap yang diajukan.
     */
    protected static function deskripsiPersetujuan(RealisasiProgramKerja $record): string
    {
        $diajukan = $record->nominalDiajukan();
        $persentase = $record->persentasePersetujuan();

        if ($persentase === null) {
            return 'Belum ada nominal yang disetujui. Diajukan Rp '.number_format($diajukan, 0, ',', '.').'.';
        }

        return "{$persentase}% dari yang diajukan (Rp ".number_format($diajukan, 0, ',', '.').').';
    }

    /**
     * Verifikator penentu nominal beserta perannya, mis. "Rektor — Budi". Null bila
     * belum ada penetapan nominal.
     */
    protected static function deskripsiPenentuNominal(RealisasiProgramKerja $record): ?string
    {
        $nama = $record->penentuNominal?->name;

        if ($nama === null) {
            return null;
        }

        $peranan = $record->perananPenentuNominal();

        return $peranan !== null ? "{$peranan} — {$nama}" : $nama;
    }
}
