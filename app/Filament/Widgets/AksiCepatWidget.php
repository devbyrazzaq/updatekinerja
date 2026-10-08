<?php

namespace App\Filament\Widgets;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Filament\Actions\CatatCapaianProgramKerjaAction;
use App\Filament\Pages\MonitoringProgramKerja;
use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Filament\Resources\Pemasukans\Schemas\PemasukanForm;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use App\Filament\Resources\PengajuanProgramKerjas\Schemas\PengajuanProgramKerjaForm;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Filament\Resources\RealisasiProgramKerjas\Schemas\RealisasiProgramKerjaForm;
use App\Filament\Widgets\Concerns\HasWidgetAuthorization;
use App\Models\Pemasukan;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Services\KonteksProgramKerja;
use App\Services\PermissionRegistrar;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * Pintasan aksi cepat dashboard: pekerjaan yang paling sering dimulai — mengajukan
 * program kerja, membuat realisasi, mencatat pemasukan, dan mencatat capaian — dibuka
 * sebagai slide-over tanpa perlu berpindah menu lebih dulu.
 *
 * Isian modalnya memakai form Resource yang sama persis dengan halaman "Tambah"
 * masing-masing, jadi validasi maupun batasan anggarannya tidak pernah berbeda. Setelah
 * tersimpan, pengguna diantar ke menu yang bersangkutan supaya berkas barunya langsung
 * terlihat pada daftarnya.
 *
 * Tombol hanya muncul untuk menu yang benar-benar boleh dibuat pengguna, mengikuti
 * `canCreate()` tiap Resource — sama seperti tombol "Tambah" di halaman daftarnya.
 */
class AksiCepatWidget extends Widget implements HasActions, HasSchemas
{
    use HasWidgetAuthorization;
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.aksi-cepat';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    /**
     * Mengajukan program kerja tahun berjalan. Statusnya mengikuti tombol utama halaman
     * "Tambah" pengajuan: langsung diajukan, bukan disimpan sebagai draf.
     */
    public function buatPengajuanAction(): Action
    {
        return Action::make('buatPengajuan')
            ->label('Buat Pengajuan Program Kerja')
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->color('primary')
            ->slideOver()
            ->modalWidth(Width::SixExtraLarge)
            ->modalHeading('Buat Pengajuan Program Kerja')
            ->modalDescription('Ajukan program kerja beserta alokasi anggarannya. Setelah tersimpan Anda diantar ke menu Pengajuan Program Kerja.')
            ->modalSubmitActionLabel('Ajukan')
            ->visible(fn (): bool => PengajuanProgramKerjaResource::canCreate())
            ->model(PengajuanProgramKerja::class)
            ->schema(fn (Schema $schema): Schema => PengajuanProgramKerjaForm::configure($schema))
            ->action(function (array $data): void {
                $pengajuan = PengajuanProgramKerja::create([
                    ...$data,
                    'user_id' => $data['user_id'] ?? auth()->id(),
                    'status' => EnumStatusPengajuan::Diajukan->value,
                ]);

                $pengajuan->catatLog($pengajuan->status, $pengajuan->user_id);

                Notification::make()
                    ->title('Pengajuan berhasil diajukan')
                    ->success()
                    ->send();
            })
            ->successRedirectUrl(fn (): string => PengajuanProgramKerjaResource::getUrl('index'));
    }

    /**
     * Membuat realisasi program kerja lalu langsung mengajukannya, mengikuti tombol
     * utama halaman "Tambah" realisasi. Bila kuota realisasi berjalan unit kerja sudah
     * penuh, realisasinya tetap tersimpan sebagai draf.
     */
    public function buatRealisasiAction(): Action
    {
        return Action::make('buatRealisasi')
            ->label('Buat Realisasi Program Kerja')
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->color('info')
            ->slideOver()
            ->modalWidth(Width::FiveExtraLarge)
            ->modalHeading('Buat Realisasi Program Kerja')
            ->modalDescription('Ajukan pelaksanaan program kerja beserta anggaran yang dipakai. Setelah tersimpan Anda diantar ke menu Realisasi Program Kerja.')
            ->modalSubmitActionLabel('Ajukan')
            ->visible(fn (): bool => RealisasiProgramKerjaResource::canCreate())
            ->model(RealisasiProgramKerja::class)
            ->schema(fn (Schema $schema): Schema => RealisasiProgramKerjaForm::configure($schema))
            ->action(function (array $data): void {
                $realisasi = RealisasiProgramKerja::create([
                    ...$data,
                    'status' => $data['status'] ?? EnumStatusRealisasi::Draft->value,
                ]);

                $realisasi->catatLog(EnumStatusRealisasi::Draft, auth()->id());

                if (! $realisasi->dapatDiajukan()) {
                    Notification::make()
                        ->title('Realisasi disimpan sebagai draf')
                        ->body('Kuota realisasi berjalan unit kerja sudah penuh, sehingga realisasi belum dapat diajukan. Selesaikan realisasi yang sedang berjalan terlebih dahulu.')
                        ->warning()
                        ->send();

                    return;
                }

                $realisasi->update(['status' => EnumStatusRealisasi::Diajukan]);
                $realisasi->catatLog(EnumStatusRealisasi::Diajukan, auth()->id());

                Notification::make()
                    ->title('Realisasi Program Kerja berhasil diajukan')
                    ->success()
                    ->send();
            })
            ->successRedirectUrl(fn (): string => RealisasiProgramKerjaResource::getUrl('index'));
    }

    /**
     * Mencatat pemasukan unit kerja. Pencatatnya diambil dari sesi, sama seperti halaman
     * "Tambah" pemasukan, agar notifikasi revisi maupun permintaan bukti sampai ke
     * orangnya.
     */
    public function catatPemasukanAction(): Action
    {
        return Action::make('catatPemasukan')
            ->label('Catat Pemasukan Unit')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading('Catat Pemasukan Unit Kerja')
            ->modalDescription('Catat pemasukan unit kerja beserta buktinya. Setelah tersimpan Anda diantar ke menu Pemasukan.')
            ->modalSubmitActionLabel('Simpan')
            ->visible(fn (): bool => PemasukanResource::canCreate())
            ->model(Pemasukan::class)
            ->schema(fn (Schema $schema): Schema => PemasukanForm::configure($schema))
            ->action(function (array $data): void {
                Pemasukan::create([...$data, 'user_id' => auth()->id()]);

                Notification::make()
                    ->title('Pemasukan berhasil dicatat')
                    ->success()
                    ->send();
            })
            ->successRedirectUrl(fn (): string => PemasukanResource::getUrl('index'));
    }

    /**
     * Mencatat ketercapaian program kerja tanpa anggaran, memakai aksi yang sama dengan
     * halaman Monitoring Program Kerja — termasuk aturan capaian yang tidak boleh mundur.
     */
    public function catatCapaianAction(): Action
    {
        return CatatCapaianProgramKerjaAction::make('catatCapaian')
            ->label('Catat Capaian Program')
            ->permission(MonitoringProgramKerja::PERMISSION_CATAT_CAPAIAN)
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->tahunKerja(fn (): ?TahunKerja => KonteksProgramKerja::tahunBerjalan())
            ->unitKerjaOptions(fn (): array => $this->unitKerjaOptions())
            ->defaultUnitKerja(fn (): ?int => auth()->user()?->unit_kerja_id)
            ->successRedirectUrl(fn (): string => MonitoringProgramKerja::canAccess()
                ? MonitoringProgramKerja::getUrl()
                : RealisasiProgramKerjaResource::getUrl('index'));
    }

    /**
     * Keterangan singkat tiap aksi, ditulis di sini karena kartunya perlu menjelaskan
     * apa yang terjadi setelah diklik — label tombol saja tidak cukup.
     *
     * @var array<string, string>
     */
    protected const KETERANGAN = [
        'buatPengajuan' => 'Ajukan program kerja beserta alokasi anggarannya.',
        'buatRealisasi' => 'Laporkan pelaksanaan program kerja dan anggaran yang dipakai.',
        'catatPemasukan' => 'Catat pemasukan unit kerja beserta buktinya.',
        'catatCapaian' => 'Perbarui ketercapaian target program kerja tanpa anggaran.',
    ];

    /**
     * Aksi yang benar-benar boleh dijalankan pengguna, sudah dirakit menjadi kartu:
     * label, keterangan, ikon, warna, dan pemicu modalnya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function aksiCepat(): array
    {
        $kartu = [];

        foreach (array_keys(static::KETERANGAN) as $nama) {
            // Aksinya diambil lewat getAction() supaya yang dirender adalah instans yang
            // sudah ditautkan ke komponen Livewire ini — itu yang membuat kartunya tahu
            // modal mana yang harus dibuka.
            $aksi = $this->getAction($nama);

            if (! $aksi instanceof Action || ! $aksi->isVisible()) {
                continue;
            }

            $kartu[] = [
                'label' => $aksi->getLabel(),
                'keterangan' => static::KETERANGAN[$nama],
                'icon' => $aksi->getIcon() ?? Heroicon::OutlinedBolt,
                'warna' => $aksi->getColor() ?? 'primary',
                'pemicu' => $aksi->getLivewireClickHandler(),
            ];
        }

        return $kartu;
    }

    /**
     * Unit kerja yang boleh dicatat capaiannya, mengikuti cakupan data pengguna — sama
     * dengan pilihan pada halaman Monitoring Program Kerja.
     *
     * @return array<int, string>
     */
    protected function unitKerjaOptions(): array
    {
        $query = UnitKerja::query()->where('is_active', true)->orderBy('name');

        $user = auth()->user();

        if ($user !== null && ! $user->canViewAllUnitData(static::getWidgetPermission())) {
            $query->whereIn('id', PermissionRegistrar::permittedUnitIds($user)->all());
        }

        return $query->pluck('name', 'id')->all();
    }
}
