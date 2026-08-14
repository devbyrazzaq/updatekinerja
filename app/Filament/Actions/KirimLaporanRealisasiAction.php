<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Filament\Forms\Components\CatatanRevisi;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\RealisasiProgramKerja;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Livewire\Component;

/**
 * Aksi "Kirim Laporan Realisasi" untuk unit kerja pada tahap Pelaksanaan Kegiatan.
 * Dipakai bersama oleh tabel realisasi dan header halaman detail agar keduanya
 * memakai isian serta perhitungan yang sama.
 *
 * Unit kerja memilih status penyerapan anggaran; bila anggaran tergunakan semua,
 * cukup mengisi ketercapaian target, sedangkan bila bersisa atau kurang, besaran
 * selisih beserta tindak lanjutnya (sudah dikembalikan/dilunasi atau menunggu Biro
 * Keuangan) ikut dicatat. Anggaran yang digunakan diturunkan dari selisih itu.
 */
class KirimLaporanRealisasiAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'unggahLaporan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (RealisasiProgramKerja $record): string => $record->adalahRevisiLaporan() ? 'Perbaiki Laporan' : 'Kirim Laporan')
            ->icon(fn (RealisasiProgramKerja $record): string => $record->adalahRevisiLaporan() ? 'heroicon-o-pencil-square' : 'heroicon-o-arrow-up-tray')
            ->color('warning')
            ->modalHeading(fn (RealisasiProgramKerja $record): string => $record->adalahRevisiLaporan()
                ? 'Perbaiki Laporan Realisasi'
                : 'Laporan Realisasi Program Kerja')
            ->modalDescription(fn (RealisasiProgramKerja $record): string => $record->adalahRevisiLaporan()
                ? 'Perbaiki laporan sesuai catatan revisi verifikator, lalu kirim ulang untuk diverifikasi.'
                : 'Laporkan hasil pelaksanaan kegiatan: evaluasi pengerjaan, penyerapan anggaran, ketercapaian target, dan dokumen laporannya.')
            ->modalSubmitActionLabel(fn (RealisasiProgramKerja $record): string => $record->adalahRevisiLaporan() ? 'Kirim Ulang Laporan' : 'Kirim Laporan')
            ->modalWidth(Width::TwoExtraLarge)
            ->visible(fn (RealisasiProgramKerja $record): bool => $record->status === EnumStatusRealisasi::MenungguLaporan
                || $record->adalahRevisiLaporan())
            ->fillForm(fn (RealisasiProgramKerja $record): array => [
                'evaluasi_pengerjaan' => $record->evaluasi_pengerjaan,
                'status_anggaran' => $record->status_anggaran?->value,
                'nominal_selisih_anggaran' => $record->nominal_selisih_anggaran,
                'status_penyelesaian_anggaran' => $record->status_penyelesaian_anggaran?->value,
                'persentase_ketercapaian' => $record->persentase_ketercapaian ?? $record->persentaseKetercapaianMinimum(),
                'laporan_path' => $record->laporan_path,
                'laporan_original_names' => $record->laporan_original_names,
            ])
            ->schema(fn (RealisasiProgramKerja $record): array => static::skemaLaporan($record))
            ->action(function (array $data, RealisasiProgramKerja $record, Component $livewire): void {
                $perbaikan = $record->adalahRevisiLaporan();

                static::simpanLaporan($data, $record, $perbaikan);

                $livewire->dispatch('komentar-realisasi-ditambahkan');

                Notification::make()
                    ->title($perbaikan ? 'Laporan berhasil dikirim ulang' : 'Laporan berhasil dikirim')
                    ->body('Laporan menunggu verifikasi. '.$record->keteranganPenyelesaianAnggaran())
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, Section|RichEditor|Select|MoneyInput|TextInput|FileUpload>
     */
    protected static function skemaLaporan(RealisasiProgramKerja $record): array
    {
        $minimalKetercapaian = $record->persentaseKetercapaianMinimum();

        return [
            ...CatatanRevisi::komponen($record),
            static::targetSection($record),
            RichEditor::make('evaluasi_pengerjaan')
                ->label('Evaluasi Pengerjaan')
                ->helperText('Uraikan pelaksanaan kegiatan, kendala yang dihadapi, dan tindak lanjutnya.')
                ->required()
                ->columnSpanFull(),
            Select::make('status_anggaran')
                ->label('Status Penyerapan Anggaran')
                ->options(EnumStatusAnggaran::class)
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    // Status anggaran menentukan pilihan tindak lanjut, jadi isian selisih
                    // dan tindak lanjut sebelumnya dikosongkan agar tidak tertinggal.
                    $set('nominal_selisih_anggaran', null);
                    $set('status_penyelesaian_anggaran', null);
                })
                ->helperText(fn (Get $get): string => static::keteranganStatusAnggaran($record, static::statusAnggaran($get)))
                ->columnSpanFull(),
            MoneyInput::make('nominal_selisih_anggaran')
                ->label(fn (Get $get): string => static::statusAnggaran($get)?->labelSelisih() ?? 'Selisih Anggaran')
                ->required()
                ->visible(fn (Get $get): bool => static::statusAnggaran($get)?->memerlukanSelisih() ?? false)
                ->maxValue(fn (Get $get): ?float => static::statusAnggaran($get) === EnumStatusAnggaran::Sisa
                    ? $record->nominalDiterima()
                    : null)
                ->validationMessages(['max' => 'Sisa anggaran tidak boleh melebihi anggaran yang diterima.'])
                ->live(onBlur: true)
                ->helperText(fn (Get $get): string => static::keteranganSelisih($record, $get))
                ->columnSpanFull(),
            Select::make('status_penyelesaian_anggaran')
                ->label('Tindak Lanjut Selisih Anggaran')
                ->options(fn (Get $get): array => EnumStatusPenyelesaianAnggaran::opsiUntuk(
                    static::statusAnggaran($get) ?? EnumStatusAnggaran::Habis,
                ))
                ->required()
                ->live()
                ->visible(fn (Get $get): bool => static::statusAnggaran($get)?->memerlukanSelisih() ?? false)
                ->helperText(fn (Get $get): string => static::keteranganPenyelesaian($get))
                ->columnSpanFull(),
            TextInput::make('persentase_ketercapaian')
                ->label('Persentase Ketercapaian Target')
                ->helperText($minimalKetercapaian > 0
                    ? "Ketercapaian pengajuan ini sudah {$minimalKetercapaian}% dari realisasi sebelumnya, jadi nilainya minimal {$minimalKetercapaian}% dan maksimal 100%."
                    : 'Seberapa besar target program kerja tercapai, dari 0 sampai 100 persen.')
                ->numeric()
                ->required()
                ->minValue($minimalKetercapaian)
                ->maxValue(100)
                ->suffix('%')
                ->validationMessages([
                    'min' => "Ketercapaian tidak boleh mundur dari capaian sebelumnya ({$minimalKetercapaian}%).",
                    'max' => 'Ketercapaian maksimal 100%.',
                ])
                ->columnSpanFull(),
            FileUpload::make('laporan_path')
                ->label('Dokumen Laporan')
                ->helperText('Berkas PDF, maksimal '.Setting::maksUkuranLaporanKb() / 1024 .' MB per berkas. Minimal 1, maksimal '.Setting::maksLaporanRealisasi().' berkas.')
                ->required()
                ->multiple()
                ->minFiles(1)
                ->maxFiles(Setting::maksLaporanRealisasi())
                ->reorderable()
                ->appendFiles()
                ->directory('laporan-realisasi')
                ->acceptedFileTypes(['application/pdf'])
                ->maxSize(Setting::maksUkuranLaporanKb())
                ->storeFileNamesIn('laporan_original_names')
                ->columnSpanFull(),
        ];
    }

    /**
     * Ringkasan target program kerja yang menjadi acuan penilaian ketercapaian,
     * ditampilkan tepat di atas isian laporan agar pelapor tidak perlu membuka
     * halaman pengajuan.
     */
    protected static function targetSection(RealisasiProgramKerja $record): Section
    {
        $target = $record->targetProgramKerja();

        return Section::make('Target Program Kerja')
            ->description('Acuan penilaian ketercapaian dari pengajuan program kerja.')
            ->icon('heroicon-o-flag')
            ->columns(2)
            ->collapsible()
            ->schema([
                Placeholder::make('target_program')
                    ->label('Target')
                    ->content($target['target'] ?? '-'),
                Placeholder::make('nilai_standar_program')
                    ->label('Nilai Standar')
                    ->content($target['nilai_standar'] ?? '-'),
                Placeholder::make('indikator_program')
                    ->label('Indikator')
                    ->content($target['indikator'] ?? '-')
                    ->columnSpanFull(),
                Placeholder::make('anggaran_diterima')
                    ->label('Anggaran Diterima')
                    ->content(static::rupiah($record->nominalDiterima()))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Status anggaran yang sedang dipilih pada form, null bila belum dipilih.
     * State select enum dapat berupa instance enum maupun nilai mentahnya.
     */
    protected static function statusAnggaran(Get $get): ?EnumStatusAnggaran
    {
        return static::keStatusAnggaran($get('status_anggaran'));
    }

    protected static function keStatusAnggaran(mixed $state): ?EnumStatusAnggaran
    {
        return match (true) {
            $state instanceof EnumStatusAnggaran => $state,
            blank($state) => null,
            default => EnumStatusAnggaran::tryFrom((string) $state),
        };
    }

    protected static function kePenyelesaian(mixed $state): ?EnumStatusPenyelesaianAnggaran
    {
        return match (true) {
            $state instanceof EnumStatusPenyelesaianAnggaran => $state,
            blank($state) => null,
            default => EnumStatusPenyelesaianAnggaran::tryFrom((string) $state),
        };
    }

    /**
     * Keterangan status anggaran terpilih beserta anggaran yang diterima sebagai
     * pembandingnya.
     */
    protected static function keteranganStatusAnggaran(RealisasiProgramKerja $record, ?EnumStatusAnggaran $status): string
    {
        $dasar = 'Anggaran yang diterima untuk realisasi ini '.static::rupiah($record->nominalDiterima()).'.';

        return $status !== null
            ? $dasar.' '.$status->getDescription()
            : $dasar.' Pilih status yang sesuai dengan pemakaian anggaran kegiatan.';
    }

    /**
     * Perkiraan anggaran yang akan tercatat sebagai realisasi akhir sesuai selisih
     * yang sedang diisi.
     */
    protected static function keteranganSelisih(RealisasiProgramKerja $record, Get $get): string
    {
        $status = static::statusAnggaran($get);

        if ($status === null) {
            return '';
        }

        $selisih = (float) ($get('nominal_selisih_anggaran') ?? 0);
        $digunakan = $record->anggaranDigunakanDariSelisih($status, $selisih);

        return $status === EnumStatusAnggaran::Sisa
            ? 'Anggaran yang tidak terpakai. Realisasi akhir menjadi '.static::rupiah($digunakan).'.'
            : 'Biaya yang melebihi anggaran diterima. Realisasi akhir menjadi '.static::rupiah($digunakan).'.';
    }

    /**
     * Konsekuensi tindak lanjut yang dipilih terhadap anggaran yang bisa digunakan
     * unit kerja.
     */
    protected static function keteranganPenyelesaian(Get $get): string
    {
        return static::kePenyelesaian($get('status_penyelesaian_anggaran'))?->getDescription()
            ?? 'Pilih "belum" bila selisih anggaran masih menunggu tindak lanjut Biro Keuangan.';
    }

    /**
     * Menyimpan laporan realisasi beserta perhitungan anggarannya, lalu meneruskan
     * realisasi ke tahap verifikasi laporan. Pada perbaikan, catatan revisi verifikator
     * dibersihkan dan riwayat mencatatnya sebagai pengiriman ulang.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function simpanLaporan(array $data, RealisasiProgramKerja $record, bool $perbaikan = false): void
    {
        $statusAnggaran = static::keStatusAnggaran($data['status_anggaran']);
        $memerlukanSelisih = $statusAnggaran->memerlukanSelisih();
        $selisih = $memerlukanSelisih ? (float) $data['nominal_selisih_anggaran'] : 0.0;

        $penyelesaian = $memerlukanSelisih
            ? static::kePenyelesaian($data['status_penyelesaian_anggaran'])
            : null;

        $record->update([
            'laporan_path' => $data['laporan_path'],
            'laporan_original_names' => $data['laporan_original_names'] ?? null,
            'evaluasi_pengerjaan' => $data['evaluasi_pengerjaan'],
            'anggaran_digunakan' => $record->anggaranDigunakanDariSelisih($statusAnggaran, $selisih),
            'status_anggaran' => $statusAnggaran,
            'nominal_selisih_anggaran' => $memerlukanSelisih ? $selisih : null,
            'status_penyelesaian_anggaran' => $penyelesaian,
            'penyelesaian_anggaran_at' => ($penyelesaian?->sudahSelesai() ?? false) ? now() : null,
            'persentase_ketercapaian' => (int) $data['persentase_ketercapaian'],
            'laporan_diserahkan_at' => now(),
            'status' => EnumStatusRealisasi::VerifikasiLaporan,
            ...($perbaikan ? ['catatan_verifikasi' => null] : []),
        ]);

        $record->catatLog(
            EnumStatusRealisasi::VerifikasiLaporan,
            auth()->id(),
            $record->keteranganPenyelesaianAnggaran(),
            diajukanKembali: $perbaikan,
        );
    }

    protected static function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
