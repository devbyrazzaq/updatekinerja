<?php

namespace App\Filament\Resources\RealisasiProgramKerjas\Schemas;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Enums\EnumUrgensiRealisasi;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Models\Setting;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RealisasiProgramKerjaForm
{
    /**
     * Ikon heroicon (outline) untuk tiap kartu ringkasan anggaran.
     */
    private const IKON = [
        'pengajuan' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m0-9v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 15v.75a.75.75 0 0 0 .75.75h.75m-1.5-1.5h1.5m14.25-9v9m0-9v-.375c0-.621-.504-1.125-1.125-1.125H3.375m17.25 10.5v.375c0 .621-.504 1.125-1.125 1.125H3.375m0 0h-.75m18-1.5v.375c0 .621-.504 1.125-1.125 1.125h-.75M12 10.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z" />',
        'digunakan' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.28m5.94 2.28-2.28 5.941" />',
        'sisa' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3" />',
    ];

    /**
     * Pengajuan yang boleh direalisasikan: sudah diterima dan tahun kerjanya benar-
     * benar berjalan. Pembatasan tahun di sini yang menutup celah terbesar — tanpa
     * itu, pengajuan tahun perencanaan yang baru saja disetujui langsung muncul
     * sebagai pilihan realisasi padahal anggarannya belum berlaku.
     *
     * @param  Builder<PengajuanProgramKerja>  $query
     * @return Builder<PengajuanProgramKerja>
     */
    protected static function pengajuanSiapDirealisasikan(Builder $query): Builder
    {
        return $query
            ->where('status', EnumStatusPengajuan::Diterima->value)
            ->whereHas(
                'penawaranProgramKerja.tahunKerja',
                fn (Builder $tahunKerja): Builder => $tahunKerja->where('status', EnumStatusTahunKerja::Berjalan),
            );
    }

    /**
     * Alokasi anggaran sebuah pengajuan, 0 bila pengajuan tidak ditemukan.
     */
    protected static function alokasiPengajuan(?int $pengajuanId): float
    {
        if ($pengajuanId === null) {
            return 0.0;
        }

        return (float) (PengajuanProgramKerja::find($pengajuanId)?->alokasi_anggaran ?? 0);
    }

    /**
     * Total anggaran yang sudah dipakai realisasi lain atas pengajuan ini. Acuannya
     * hanya realisasi yang sudah diajukan/dalam proses hingga selesai; draf dan yang
     * ditolak tidak dihitung karena belum/tidak lagi membebani anggaran. Realisasi
     * yang sedang disunting dikecualikan agar tidak menghitung dirinya sendiri.
     */
    protected static function anggaranDigunakan(?int $pengajuanId, ?int $kecualiRealisasiId = null): float
    {
        if ($pengajuanId === null) {
            return 0.0;
        }

        return (float) RealisasiProgramKerja::query()
            ->where('pengajuan_program_kerja_id', $pengajuanId)
            ->whereNotIn('status', [
                EnumStatusRealisasi::Draft->value,
                EnumStatusRealisasi::Ditolak->value,
                EnumStatusRealisasi::Dibatalkan->value,
            ])
            ->when($kecualiRealisasiId !== null, fn ($query) => $query->whereKeyNot($kecualiRealisasiId))
            ->sum('anggaran_digunakan');
    }

    /**
     * Sisa anggaran pengajuan yang masih boleh dipakai realisasi ini: alokasi dikurangi
     * pemakaian realisasi lain. Null bila pengajuan belum dipilih (tanpa batas).
     */
    protected static function sisaAnggaran(?int $pengajuanId, ?int $kecualiRealisasiId = null): ?float
    {
        if ($pengajuanId === null) {
            return null;
        }

        return static::alokasiPengajuan($pengajuanId) - static::anggaranDigunakan($pengajuanId, $kecualiRealisasiId);
    }

    protected static function formatRupiah(float $nilai): string
    {
        return 'Rp '.number_format($nilai, 0, ',', '.');
    }

    /**
     * Tiga kartu ringkasan anggaran (pengajuan, digunakan, sisa) dengan ikon, warna,
     * dan nominal berukuran besar. Warna sisa menjadi merah bila anggaran terlampaui.
     */
    protected static function ringkasanAnggaran(?int $pengajuanId, ?int $kecualiRealisasiId): HtmlString
    {
        $alokasi = static::alokasiPengajuan($pengajuanId);
        $digunakan = static::anggaranDigunakan($pengajuanId, $kecualiRealisasiId);
        $sisa = $alokasi - $digunakan;

        $kartu = static::kartuAnggaran('Anggaran Pengajuan', $alokasi, 'biru', self::IKON['pengajuan'])
            .static::kartuAnggaran('Sudah Digunakan', $digunakan, 'kuning', self::IKON['digunakan'])
            .static::kartuAnggaran('Sisa Anggaran', $sisa, $sisa < 0 ? 'merah' : 'hijau', self::IKON['sisa']);

        return new HtmlString(
            '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:1rem;">'.$kartu.'</div>'
        );
    }

    /**
     * Satu kartu ringkasan: kotak ikon berwarna + label + nominal besar.
     */
    protected static function kartuAnggaran(string $label, float $nilai, string $warna, string $pathIkon): string
    {
        [$fg, $bg] = match ($warna) {
            'biru' => ['#2563eb', 'rgba(37,99,235,0.12)'],
            'kuning' => ['#d97706', 'rgba(217,119,6,0.12)'],
            'merah' => ['#dc2626', 'rgba(220,38,38,0.12)'],
            default => ['#16a34a', 'rgba(22,163,74,0.12)'],
        };

        $nominal = e(static::formatRupiah($nilai));
        $label = e($label);

        $ikon = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" style="width:1.5rem;height:1.5rem;">'.$pathIkon.'</svg>';

        return <<<HTML
        <div style="display:flex;align-items:center;gap:0.85rem;">
            <div style="display:flex;align-items:center;justify-content:center;width:2.9rem;height:2.9rem;border-radius:0.9rem;background:{$bg};color:{$fg};flex:none;">{$ikon}</div>
            <div style="min-width:0;">
                <div style="font-size:0.8125rem;color:#6b7280;font-weight:500;">{$label}</div>
                <div style="font-size:1.6rem;line-height:2rem;font-weight:700;color:{$fg};letter-spacing:-0.01em;">{$nominal}</div>
            </div>
        </div>
        HTML;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ringkasan Anggaran')
                    ->description('Sisa dihitung dari anggaran pengajuan dikurangi realisasi yang sudah diajukan dan dalam proses (di luar draf).')
                    ->icon('heroicon-o-banknotes')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => filled($get('pengajuan_program_kerja_id')))
                    ->schema([
                        Placeholder::make('ringkasan_anggaran')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->content(fn (Get $get, ?RealisasiProgramKerja $record): HtmlString => static::ringkasanAnggaran(
                                $get('pengajuan_program_kerja_id'),
                                $record?->id,
                            )),
                    ]),
                Section::make('Informasi Realisasi')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('pengajuan_program_kerja_id')
                                ->label('Pengajuan Program Kerja')
                                ->relationship(
                                    'pengajuanProgramKerja',
                                    'id',
                                    fn (Builder $query): Builder => static::pengajuanSiapDirealisasikan($query),
                                )
                                ->getOptionLabelFromRecordUsing(fn (PengajuanProgramKerja $record): string => $record->penawaranProgramKerja?->name.' — '.$record->unitKerja?->name)
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->default(fn (): ?int => static::pengajuanSiapDirealisasikan(PengajuanProgramKerja::query())
                                    ->where('uuid', request()->query('pengajuan'))
                                    ->value('id'))
                                ->helperText('Hanya pengajuan yang sudah diterima pada tahun kerja berjalan. Pengajuan tahun yang masih direncanakan baru bisa direalisasikan setelah tahunnya dijalankan.')
                                ->disabledOn('edit')
                                ->columnSpanFull(),
                            TextInput::make('name')
                                ->label('Nama Kegiatan')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            ToggleButtons::make('urgensi')
                                ->label('Tingkat Urgensi')
                                ->helperText('Menandai seberapa mendesak realisasi ini agar diperhatikan verifikator.')
                                ->options(EnumUrgensiRealisasi::class)
                                ->default(EnumUrgensiRealisasi::Rendah)
                                ->required()
                                ->inline()
                                ->columnSpanFull(),
                            DateTimePicker::make('start_datetime')
                                ->label('Mulai')
                                ->columnSpan(1),
                            DateTimePicker::make('end_datetime')
                                ->label('Selesai')
                                ->after('start_datetime')
                                ->columnSpan(1),
                            MoneyInput::make('anggaran_digunakan')
                                ->label('Nominal Diajukan')
                                ->required()
                                ->maxValue(fn (Get $get, ?RealisasiProgramKerja $record): ?float => static::sisaAnggaran($get('pengajuan_program_kerja_id'), $record?->id))
                                ->helperText(fn (Get $get, ?RealisasiProgramKerja $record): string => 'Tidak boleh melebihi sisa anggaran pengajuan: '.static::formatRupiah(
                                    static::sisaAnggaran($get('pengajuan_program_kerja_id'), $record?->id) ?? 0,
                                ).'.')
                                ->validationMessages(['max' => 'Anggaran digunakan melebihi sisa anggaran pengajuan.'])
                                ->columnSpanFull(),
                            RichEditor::make('description')
                                ->label('Deskripsi')
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Dokumen Proposal')
                    ->description('Proposal kegiatan wajib dilampirkan. Realisasi tidak dapat diajukan tanpa dokumen ini.')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('proposal_path')
                            ->label('Berkas Proposal')
                            ->helperText('Berkas PDF, maksimal '.Setting::maksUkuranProposalKb() / 1024 .' MB per berkas. Minimal 1, maksimal '.Setting::maksProposalRealisasi().' berkas.')
                            ->required()
                            ->multiple()
                            ->minFiles(1)
                            ->maxFiles(Setting::maksProposalRealisasi())
                            ->reorderable()
                            ->appendFiles()
                            ->directory('proposal-realisasi')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(Setting::maksUkuranProposalKb())
                            ->storeFileNamesIn('proposal_original_names')
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
