<?php

namespace App\Filament\Pages;

use App\Enums\EnumJenisDokumenRealisasi;
use App\Filament\Pages\Concerns\MembacaRealisasiTerpantau;
use App\Models\RealisasiProgramKerja;
use App\Services\KodeDokumenRealisasi;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;

/**
 * Halaman pratinjau dokumen satu realisasi — inilah tujuan tautan "Lihat Proposal"
 * dan "Lihat Laporan" yang tertanam pada berkas ekspor Monitoring Realisasi.
 *
 * Berkas realisasi tersimpan pada disk privat sehingga hanya bisa dibuka lewat
 * `Storage::temporaryUrl()`. Umur URL itu pendek, jadi berkas ekspor tidak mungkin
 * menyimpannya; yang disimpan adalah kode ({@see KodeDokumenRealisasi}),
 * dan halaman inilah yang menukarnya menjadi URL baru — dibuatkan ulang tiap kali
 * halaman dirender, sehingga tautan lama yang bocor pun sudah kedaluwarsa.
 *
 * Satu realisasi boleh menyimpan lebih dari satu proposal maupun laporan; semuanya
 * didaftar di sini dan bisa dipilih satu per satu. Berkas yang dipratinjau selalu
 * dicocokkan dulu dengan daftar milik realisasi tersebut, sehingga path sembarangan
 * yang dikirim dari peramban tidak pernah ditandatangani.
 *
 * Tidak punya hak akses sendiri: siapa pun yang boleh membuka Monitoring Realisasi
 * boleh membukanya, dibatasi scope data unit kerja yang sama
 * ({@see MembacaRealisasiTerpantau}).
 */
class PratinjauDokumenRealisasi extends Page
{
    use MembacaRealisasiTerpantau;

    protected string $view = 'filament.pages.pratinjau-dokumen-realisasi';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'monitoring-realisasi/dokumen';

    /**
     * Umur URL sementara berkas. Cukup untuk membaca dokumen panjang, tetapi tetap
     * kedaluwarsa jauh sebelum berkas ekspornya berpindah tangan.
     */
    public const MENIT_URL = 30;

    /**
     * Realisasi yang dokumennya dibuka. Bertipe gabungan seperti halaman record
     * bawaan Filament: Livewire mengisinya lebih dulu dengan kunci dari rute, lalu
     * `mount()` menggantinya dengan modelnya.
     */
    #[Locked]
    public RealisasiProgramKerja|int|string|null $record = null;

    /**
     * Jenis dokumen yang sedang dibuka. Dikunci dari peramban; pergantiannya hanya
     * lewat {@see pilihJenis()} yang memvalidasi nilainya.
     */
    #[Locked]
    public string $jenis = '';

    /**
     * Path berkas yang sedang dipratinjau, selalu salah satu berkas milik realisasi
     * ini. Null berarti jenis dokumen tersebut belum punya berkas sama sekali.
     */
    #[Locked]
    public ?string $berkas = null;

    /**
     * Daftar berkas yang sudah dibaca pada render ini, ditahan agar daftar, penanda
     * jumlah, dan pratinjaunya tidak menanyakan tabel dokumen berulang kali.
     *
     * @var array<string, Collection<int, array{path: string, nama: string, ukuran: ?string, diunggah: ?Carbon}>>
     */
    protected array $berkasTerbaca = [];

    /**
     * Rute halaman menyelipkan kunci realisasi di tengah agar pratinjau tetap berada
     * di bawah alamat Monitoring Realisasi, mis. `/monitoring-realisasi/{uuid}/dokumen/proposal`.
     */
    public static function getRoutePath(Panel $panel): string
    {
        return '/monitoring-realisasi/{record}/dokumen/{jenis}';
    }

    /**
     * Menumpang hak akses Monitoring Realisasi, jadi tidak mendaftarkan permission
     * sendiri pada form Role & Hak Akses.
     *
     * @return array<string, string>
     */
    public static function getPermissionDefinitions(): array
    {
        return [];
    }

    public static function canAccess(): bool
    {
        return MonitoringRealisasi::canAccess();
    }

    public function mount(string $record, string $jenis): void
    {
        $this->record = $this->resolveRecord($record);
        $this->jenis = (EnumJenisDokumenRealisasi::tryFrom($jenis) ?? EnumJenisDokumenRealisasi::Proposal)->value;
        $this->berkas = $this->berkasTersedia()->value('path');
    }

    public function getRecord(): RealisasiProgramKerja
    {
        abort_unless($this->record instanceof RealisasiProgramKerja, 404);

        return $this->record;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Dokumen '.$this->jenisDokumen()->getLabel();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $realisasi = $this->getRecord();

        return trim(implode(' · ', array_filter([
            $realisasi->name,
            $realisasi->pengajuanProgramKerja?->unitKerja?->name,
            $realisasi->tahunKerja()?->name,
        ])));
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            MonitoringRealisasi::getUrl() => 'Monitoring Realisasi',
            DetailMonitoringRealisasi::getUrl(['record' => $this->getRecord()->getRouteKey()]) => $this->getRecord()->name ?? 'Detail Realisasi',
            $this->getTitle(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('detail')
                ->label('Lihat Detail Realisasi')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(DetailMonitoringRealisasi::getUrl(['record' => $this->getRecord()->getRouteKey()])),
        ];
    }

    public function jenisDokumen(): EnumJenisDokumenRealisasi
    {
        return EnumJenisDokumenRealisasi::tryFrom($this->jenis) ?? EnumJenisDokumenRealisasi::Proposal;
    }

    /**
     * Berkas pada jenis dokumen yang sedang dibuka, unggahan terbaru lebih dahulu.
     *
     * @return Collection<int, array{path: string, nama: string, ukuran: ?string, diunggah: ?Carbon}>
     */
    public function berkasTersedia(): Collection
    {
        return $this->berkasUntuk($this->jenisDokumen());
    }

    /**
     * Jumlah berkas per jenis dokumen, untuk menandai tab yang kosong.
     *
     * @return array<string, int>
     */
    public function jumlahBerkas(): array
    {
        return collect(EnumJenisDokumenRealisasi::cases())
            ->mapWithKeys(fn (EnumJenisDokumenRealisasi $jenis): array => [
                $jenis->value => $this->berkasUntuk($jenis)->count(),
            ])
            ->all();
    }

    /**
     * @return Collection<int, array{path: string, nama: string, ukuran: ?string, diunggah: ?Carbon}>
     */
    protected function berkasUntuk(EnumJenisDokumenRealisasi $jenis): Collection
    {
        return $this->berkasTerbaca[$jenis->value] ??= $this->getRecord()->berkasDokumen($jenis);
    }

    /**
     * Berkas yang sedang dipratinjau beserta URL sementaranya. URL-nya dibuat ulang
     * pada tiap render — itulah inti halaman ini.
     *
     * @return array{url: ?string, extension: string, name: string}|null
     */
    public function pratinjau(): ?array
    {
        $berkas = $this->berkasTersedia()->firstWhere('path', $this->berkas);

        if ($berkas === null) {
            return null;
        }

        return [
            'url' => $this->urlSementara($berkas['path']),
            'extension' => strtolower(pathinfo($berkas['path'], PATHINFO_EXTENSION)),
            'name' => $berkas['nama'],
        ];
    }

    /**
     * Ganti berkas yang dipratinjau. Path yang bukan milik realisasi ini diabaikan.
     */
    public function pilihBerkas(string $path): void
    {
        if ($this->berkasTersedia()->contains('path', $path)) {
            $this->berkas = $path;
        }
    }

    /**
     * Pindah ke jenis dokumen lain tanpa meninggalkan halaman, lalu langsung
     * menampilkan berkas terbarunya.
     */
    public function pilihJenis(string $jenis): void
    {
        $dipilih = EnumJenisDokumenRealisasi::tryFrom($jenis);

        if ($dipilih === null) {
            return;
        }

        $this->jenis = $dipilih->value;
        $this->berkas = $this->berkasTersedia()->value('path');
    }

    /**
     * URL berumur pendek untuk berkas privat; null bila disk-nya tidak sanggup
     * membuatkannya sehingga halaman tetap tampil dengan keterangan yang jujur.
     */
    protected function urlSementara(string $path): ?string
    {
        try {
            return Storage::disk(config('filament.default_filesystem_disk'))
                ->temporaryUrl($path, now()->addMinutes(static::MENIT_URL));
        } catch (\Throwable) {
            return null;
        }
    }
}
