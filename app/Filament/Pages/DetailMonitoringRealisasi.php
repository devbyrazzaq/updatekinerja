<?php

namespace App\Filament\Pages;

use App\Enums\EnumJenisDokumenRealisasi;
use App\Filament\Pages\Concerns\MembacaRealisasiTerpantau;
use App\Filament\Resources\RealisasiProgramKerjas\Schemas\RealisasiProgramKerjaInfolist;
use App\Models\RealisasiProgramKerja;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;

/**
 * Rincian satu realisasi program kerja yang dibuka dari {@see MonitoringRealisasi}:
 * tahapan yang sedang dijalani, besaran anggarannya, dokumen proposal dan laporannya,
 * mutasi anggarannya pada buku anggaran, sampai riwayat aktivitasnya.
 *
 * Halaman ini memakai infolist yang sama dengan menu Realisasi Program Kerja, tetapi
 * tanpa terikat konteks tahun kerja berjalan — sehingga realisasi tahun-tahun
 * sebelumnya tetap terbaca utuh. Sifatnya murni membaca: tidak ada satu pun aksi yang
 * mengubah data, sehingga aman dibuka pimpinan unit maupun pemantau lain.
 *
 * Tidak punya hak akses sendiri; siapa pun yang boleh membuka Monitoring Realisasi
 * boleh membuka rinciannya, dibatasi scope data unit kerja yang sama.
 *
 * @property-read Schema $infolist
 */
class DetailMonitoringRealisasi extends Page
{
    use MembacaRealisasiTerpantau;

    protected string $view = 'filament.pages.detail-monitoring-realisasi';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'monitoring-realisasi/detail';

    /**
     * Realisasi yang sedang dibaca. Bertipe gabungan seperti halaman record bawaan
     * Filament: Livewire mengisinya lebih dulu dengan kunci dari rute, lalu `mount()`
     * menggantinya dengan modelnya.
     */
    #[Locked]
    public RealisasiProgramKerja|int|string|null $record = null;

    /**
     * Rute halaman menyelipkan kunci realisasi di tengah agar rincian tetap berada di
     * bawah alamat Monitoring Realisasi, mis. `/monitoring-realisasi/{uuid}/detail`.
     */
    public static function getRoutePath(Panel $panel): string
    {
        return '/monitoring-realisasi/{record}/detail';
    }

    /**
     * Halaman ini menumpang hak akses Monitoring Realisasi, jadi tidak mendaftarkan
     * permission sendiri pada form Role & Hak Akses.
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

    public function mount(string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getRecord(): RealisasiProgramKerja
    {
        abort_unless($this->record instanceof RealisasiProgramKerja, 404);

        return $this->record;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->name ?? 'Detail Realisasi';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $realisasi = $this->getRecord();

        return trim(implode(' · ', array_filter([
            $realisasi->pengajuanProgramKerja?->penawaranProgramKerja?->name,
            $realisasi->pengajuanProgramKerja?->unitKerja?->name,
            $realisasi->tahunKerja()?->name,
        ])));
    }

    /**
     * @return array<int, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            MonitoringRealisasi::getUrl() => 'Monitoring Realisasi',
            $this->getTitle(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        $dokumen = array_map(
            fn (EnumJenisDokumenRealisasi $jenis): Action => Action::make('lihat'.$jenis->value)
                ->label('Lihat '.$jenis->getLabel())
                ->icon($jenis->getIcon())
                ->color($jenis->getColor())
                ->visible(fn (): bool => $this->getRecord()->punyaDokumen($jenis))
                ->url(PratinjauDokumenRealisasi::getUrl([
                    'record' => $this->getRecord()->getRouteKey(),
                    'jenis' => $jenis->value,
                ])),
            EnumJenisDokumenRealisasi::cases(),
        );

        return [
            ...$dokumen,
            Action::make('kembali')
                ->label('Kembali ke Monitoring')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(MonitoringRealisasi::getUrl()),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return RealisasiProgramKerjaInfolist::configure($schema->record($this->getRecord()));
    }
}
