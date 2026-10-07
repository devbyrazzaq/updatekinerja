<?php

namespace App\Filament\Actions;

use App\Enums\EnumJenisRealisasi;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Models\Setting;
use App\Models\TahunKerja;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Aksi mencatat ketercapaian sebuah program kerja langsung dari halaman monitoring,
 * tanpa melewati alur pengajuan-pencairan realisasi.
 *
 * Capaiannya disimpan sebagai realisasi program kerja bertanda
 * {@see EnumJenisRealisasi::TanpaAnggaran} yang langsung berstatus Selesai namun tidak
 * menyentuh anggaran sama sekali (`anggaran_digunakan` nol, `dicairkan_at` dan
 * `status_anggaran` kosong), sehingga yang bertambah hanya capaian target — penyerapan
 * anggaran pada monitoring tetap apa adanya. Tanda jenis itu pula yang membuat seluruh
 * tampilan bernuansa anggaran (stepper verifikasi, besaran realisasi, persetujuan
 * nominal) tidak ikut ditampilkan. Laporan pelaksanaannya diunggah ke disk privat dan
 * dipratinjau lewat URL sementara ({@see MediaAction}).
 *
 * Ketercapaian tidak boleh mundur: nilai terkecil yang diterima adalah capaian
 * tertinggi yang sudah tercatat pada pengajuan yang sama, mengikuti aturan yang
 * dipakai {@see KirimLaporanRealisasiAction}.
 */
class CatatCapaianProgramKerjaAction extends AuthorizedAction
{
    protected const SATU_HARI = 'satu_hari';

    protected const RENTANG = 'rentang';

    protected Closure|TahunKerja|null $tahunKerja = null;

    /**
     * @var Closure|array<int, string>
     */
    protected Closure|array $unitKerjaOptions = [];

    protected Closure|int|null $defaultUnitKerjaId = null;

    public static function getDefaultName(): ?string
    {
        return 'catatCapaian';
    }

    /**
     * Tahun kerja tempat capaian dicatat, mengikuti penyaring halaman.
     */
    public function tahunKerja(Closure|TahunKerja|null $tahunKerja): static
    {
        $this->tahunKerja = $tahunKerja;

        return $this;
    }

    /**
     * Unit kerja yang boleh dipilih (id => nama), mengikuti scope data pengguna.
     *
     * @param  Closure|array<int, string>  $options
     */
    public function unitKerjaOptions(Closure|array $options): static
    {
        $this->unitKerjaOptions = $options;

        return $this;
    }

    /**
     * Unit kerja yang terpilih lebih dulu saat modal dibuka.
     */
    public function defaultUnitKerja(Closure|int|null $unitKerjaId): static
    {
        $this->defaultUnitKerjaId = $unitKerjaId;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Catat Capaian')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('primary')
            ->modalHeading('Catat Capaian Program Kerja')
            ->modalDescription('Perbarui ketercapaian target program kerja beserta laporan pelaksanaannya. Capaian ini tercatat sebagai realisasi tanpa anggaran dan langsung berstatus selesai.')
            ->modalSubmitActionLabel('Simpan Capaian')
            ->modalWidth(Width::TwoExtraLarge)
            // Aksi hanya berguna bila tahun kerjanya sudah ditetapkan; tanpa itu tidak
            // ada program kerja yang bisa dipilih.
            ->visible(fn (): bool => $this->isVisibleForCurrentUser() && $this->tahunKerjaTerpilih() !== null)
            ->fillForm(fn (): array => [
                'unit_kerja_id' => $this->unitKerjaBawaan(),
                'jenis_pelaksanaan' => self::SATU_HARI,
            ])
            ->schema(fn (): array => $this->skemaCapaian())
            ->action(function (array $data): void {
                $this->simpanCapaian($data);
            });
    }

    /**
     * @return array<int, Select|ToggleButtons|Grid|RichEditor|FileUpload|TextInput>
     */
    protected function skemaCapaian(): array
    {
        return [
            Select::make('unit_kerja_id')
                ->label('Unit Kerja')
                ->options($this->opsiUnitKerja())
                ->required()
                ->searchable()
                ->native(false)
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    // Program kerja dan capaian minimalnya terikat unit kerja, jadi
                    // pilihan sebelumnya dikosongkan agar tidak tertinggal.
                    $set('pengajuan_program_kerja_id', null);
                    $set('persentase_ketercapaian', null);
                })
                ->helperText('Hanya unit kerja yang boleh Anda akses.')
                ->columnSpanFull(),
            Select::make('pengajuan_program_kerja_id')
                ->label('Program Kerja')
                ->options(fn (Get $get): array => $this->programKerjaOptions($this->keId($get('unit_kerja_id'))))
                ->required()
                ->searchable()
                ->native(false)
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    $set('persentase_ketercapaian', $this->capaianTerakhir($this->keId($state)) ?: null);
                })
                ->helperText(fn (Get $get): string => $this->keteranganProgramKerja($this->keId($get('unit_kerja_id')), $this->keId($get('pengajuan_program_kerja_id'))))
                ->columnSpanFull(),
            ToggleButtons::make('jenis_pelaksanaan')
                ->label('Waktu Pelaksanaan')
                ->options([
                    self::SATU_HARI => 'Satu Tanggal',
                    self::RENTANG => 'Rentang Waktu',
                ])
                ->default(self::SATU_HARI)
                ->required()
                ->inline()
                ->live()
                ->afterStateUpdated(fn (Set $set): mixed => $set('tanggal_selesai', null))
                ->helperText('Kegiatan yang berlangsung lebih dari sehari dicatat sebagai rentang waktu.')
                ->columnSpanFull(),
            Grid::make(2)->schema([
                DatePicker::make('tanggal_mulai')
                    ->label(fn (Get $get): string => $this->adalahRentang($get) ? 'Tanggal Mulai' : 'Tanggal Pelaksanaan')
                    ->required()
                    ->native(false)
                    ->displayFormat('d F Y')
                    ->columnSpan(fn (Get $get): int => $this->adalahRentang($get) ? 1 : 2),
                DatePicker::make('tanggal_selesai')
                    ->label('Tanggal Selesai')
                    ->required()
                    ->native(false)
                    ->displayFormat('d F Y')
                    ->afterOrEqual('tanggal_mulai')
                    ->validationMessages(['after_or_equal' => 'Tanggal selesai tidak boleh mendahului tanggal mulai.'])
                    ->visible(fn (Get $get): bool => $this->adalahRentang($get))
                    ->columnSpan(1),
            ]),
            RichEditor::make('deskripsi_kegiatan')
                ->label('Deskripsi Kegiatan')
                ->helperText('Uraikan kegiatan yang dilaksanakan beserta hasilnya sebagai dasar penilaian ketercapaian.')
                ->required()
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
                ->downloadable()
                ->openable()
                ->columnSpanFull(),
            TextInput::make('persentase_ketercapaian')
                ->label('Persentase Ketercapaian Target')
                ->helperText(fn (Get $get): string => $this->keteranganKetercapaian($this->keId($get('pengajuan_program_kerja_id'))))
                ->numeric()
                ->required()
                ->minValue(fn (Get $get): int => $this->capaianTerakhir($this->keId($get('pengajuan_program_kerja_id'))))
                ->maxValue(100)
                ->suffix('%')
                ->validationMessages([
                    'min' => 'Ketercapaian tidak boleh mundur dari capaian terakhir program kerja ini.',
                    'max' => 'Ketercapaian maksimal 100%.',
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Menyimpan capaian sebagai realisasi tanpa anggaran yang langsung selesai.
     *
     * @param  array<string, mixed>  $data
     */
    protected function simpanCapaian(array $data): void
    {
        $pengajuan = $this->pengajuanQuery($this->keId($data['unit_kerja_id'] ?? null))
            ->whereKey($this->keId($data['pengajuan_program_kerja_id'] ?? null))
            ->first();

        if ($pengajuan === null) {
            Notification::make()
                ->title('Program kerja tidak dapat dicatat')
                ->body('Program kerja yang dipilih tidak lagi tersedia pada tahun kerja ini, atau berada di luar unit kerja yang boleh Anda akses.')
                ->danger()
                ->send();

            return;
        }

        $minimum = RealisasiProgramKerja::persentaseKetercapaianTertinggi($pengajuan->getKey());
        $persentase = (int) $data['persentase_ketercapaian'];

        // Capaian terakhir bisa berubah sejak modal dibuka, jadi batas bawahnya diuji
        // ulang saat menyimpan.
        if ($persentase < $minimum) {
            Notification::make()
                ->title('Ketercapaian mundur dari capaian terakhir')
                ->body("Program kerja ini sudah tercatat {$minimum}%, sehingga capaian barunya tidak boleh lebih kecil dari itu.")
                ->danger()
                ->send();

            return;
        }

        $mulai = Carbon::parse($data['tanggal_mulai'])->startOfDay();
        $selesai = ($data['jenis_pelaksanaan'] ?? self::SATU_HARI) === self::RENTANG && filled($data['tanggal_selesai'] ?? null)
            ? Carbon::parse($data['tanggal_selesai'])->endOfDay()
            : $mulai->copy()->endOfDay();

        $program = $pengajuan->penawaranProgramKerja?->name ?? 'Program Kerja';

        $realisasi = RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->getKey(),
            'name' => $program,
            'jenis_realisasi' => EnumJenisRealisasi::TanpaAnggaran,
            // Deskripsi kegiatan sekaligus menjadi evaluasi pengerjaannya: capaian ini
            // dicatat tanpa alur laporan terpisah, sehingga keduanya satu cerita.
            'description' => $data['deskripsi_kegiatan'],
            'evaluasi_pengerjaan' => $data['deskripsi_kegiatan'],
            'start_datetime' => $mulai,
            'end_datetime' => $selesai,
            'anggaran_digunakan' => 0,
            'status' => EnumStatusRealisasi::Selesai,
            // Status anggaran sengaja dibiarkan kosong: tidak ada anggaran yang diserap,
            // sehingga "tergunakan semua" maupun "bersisa" sama-sama tidak berlaku.
            'laporan_path' => $data['laporan_path'],
            'laporan_original_names' => $data['laporan_original_names'] ?? null,
            'persentase_ketercapaian' => $persentase,
            'laporan_diserahkan_at' => now(),
            'laporan_disetujui_at' => now(),
            'verifikator_laporan_id' => auth()->id(),
            'dicatat_oleh_id' => auth()->id(),
        ]);

        $realisasi->catatLog(
            EnumStatusRealisasi::Selesai,
            auth()->id(),
            "Capaian dicatat langsung dari Monitoring Program Kerja: ketercapaian {$persentase}% tanpa penggunaan anggaran.",
        );

        Notification::make()
            ->title('Capaian program kerja tersimpan')
            ->body("Ketercapaian \"{$program}\" kini {$persentase}%. Capaian ini tercatat tanpa anggaran, jadi penyerapan anggaran tidak berubah.")
            ->success()
            ->send();
    }

    /**
     * Program kerja yang boleh dicatat capaiannya: pengajuan yang sudah diterima pada
     * tahun kerja terpilih dan berada dalam cakupan unit kerja pengguna. Label yang
     * kembar dibedakan alokasi anggarannya agar pengajuan yang berbeda tidak tertukar.
     *
     * @return array<int, string>
     */
    protected function programKerjaOptions(?int $unitKerjaId): array
    {
        if ($unitKerjaId === null) {
            return [];
        }

        $pengajuans = $this->pengajuanQuery($unitKerjaId)->get();

        $namaKembar = $pengajuans
            ->groupBy(fn (PengajuanProgramKerja $pengajuan): string => $this->namaProgram($pengajuan))
            ->filter(fn ($kelompok): bool => $kelompok->count() > 1)
            ->keys();

        return $pengajuans
            ->mapWithKeys(function (PengajuanProgramKerja $pengajuan) use ($namaKembar): array {
                $nama = $this->namaProgram($pengajuan);

                if ($namaKembar->contains($nama)) {
                    $nama .= ' — alokasi '.$this->rupiah((float) $pengajuan->alokasi_anggaran);
                }

                return [$pengajuan->getKey() => $nama];
            })
            ->sort()
            ->all();
    }

    /**
     * @return Builder<PengajuanProgramKerja>
     */
    protected function pengajuanQuery(?int $unitKerjaId): Builder
    {
        $tahunKerjaId = $this->tahunKerjaTerpilih()?->getKey();
        $unitKerjaIds = array_keys($this->opsiUnitKerja());

        if ($unitKerjaId !== null) {
            $unitKerjaIds = array_values(array_intersect($unitKerjaIds, [$unitKerjaId]));
        }

        return PengajuanProgramKerja::query()
            ->with('penawaranProgramKerja')
            ->where('status', EnumStatusPengajuan::Diterima->value)
            ->whereIn('unit_kerja_id', $unitKerjaIds)
            ->whereHas('penawaranProgramKerja', fn (Builder $query): Builder => $query
                ->where('tahun_kerja_id', $tahunKerjaId));
    }

    /**
     * Capaian tertinggi yang sudah tercatat pada sebuah pengajuan, menjadi batas bawah
     * capaian baru.
     */
    protected function capaianTerakhir(?int $pengajuanId): int
    {
        return $pengajuanId === null
            ? 0
            : RealisasiProgramKerja::persentaseKetercapaianTertinggi($pengajuanId);
    }

    protected function keteranganProgramKerja(?int $unitKerjaId, ?int $pengajuanId): string
    {
        if ($unitKerjaId === null) {
            return 'Pilih unit kerja lebih dulu untuk melihat program kerjanya.';
        }

        if ($pengajuanId === null) {
            return 'Hanya program kerja yang pengajuannya sudah diterima pada tahun kerja ini.';
        }

        $capaian = $this->capaianTerakhir($pengajuanId);

        return $capaian > 0
            ? "Capaian terakhir program kerja ini {$capaian}%."
            : 'Program kerja ini belum pernah melaporkan capaian.';
    }

    protected function keteranganKetercapaian(?int $pengajuanId): string
    {
        $minimal = $this->capaianTerakhir($pengajuanId);

        return $minimal > 0
            ? "Capaian tidak boleh mundur, jadi nilainya minimal {$minimal}% dan maksimal 100%."
            : 'Seberapa besar target program kerja tercapai, dari 0 sampai 100 persen.';
    }

    protected function namaProgram(PengajuanProgramKerja $pengajuan): string
    {
        return $pengajuan->penawaranProgramKerja?->name ?? 'Program Kerja';
    }

    protected function adalahRentang(Get $get): bool
    {
        return $get('jenis_pelaksanaan') === self::RENTANG;
    }

    protected function tahunKerjaTerpilih(): ?TahunKerja
    {
        $tahunKerja = $this->evaluate($this->tahunKerja);

        return $tahunKerja instanceof TahunKerja ? $tahunKerja : null;
    }

    /**
     * @return array<int, string>
     */
    protected function opsiUnitKerja(): array
    {
        /** @var array<int, string> $options */
        $options = $this->evaluate($this->unitKerjaOptions) ?? [];

        return $options;
    }

    protected function unitKerjaBawaan(): ?int
    {
        return $this->keId($this->evaluate($this->defaultUnitKerjaId));
    }

    protected function keId(mixed $state): ?int
    {
        return filled($state) ? (int) $state : null;
    }

    protected function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
