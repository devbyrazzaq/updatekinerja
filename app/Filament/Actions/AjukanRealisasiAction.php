<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Forms\Components\CatatanRevisi;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Models\RealisasiProgramKerja;
use App\Models\Setting;
use App\Services\Notifikasi\NotifikasiVerifikasi;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * Aksi mengajukan realisasi beserta dokumen proposalnya, dipakai bersama oleh tabel
 * realisasi dan header halaman detail.
 *
 * Realisasi berstatus draf diajukan untuk pertama kali, sedangkan realisasi yang
 * diminta revisi pada tahap verifikasi proposal diperbaiki lewat aksi yang sama
 * ("Perbaiki Proposal") lalu diteruskan kembali ke tahap tempat revisi diminta.
 * Revisi pada tahap verifikasi laporan tidak ditangani di sini, melainkan oleh
 * {@see KirimLaporanRealisasiAction} karena yang perlu diperbaiki adalah laporannya.
 */
class AjukanRealisasiAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'ajukanRealisasi';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (RealisasiProgramKerja $record): string => static::adalahPerbaikan($record) ? 'Perbaiki Proposal' : 'Ajukan')
            ->icon(fn (RealisasiProgramKerja $record): string => static::adalahPerbaikan($record) ? 'heroicon-o-pencil-square' : 'heroicon-o-paper-airplane')
            ->color('info')
            ->modalHeading(fn (RealisasiProgramKerja $record): string => static::adalahPerbaikan($record) ? 'Perbaiki Proposal Realisasi' : 'Ajukan Realisasi')
            ->modalDescription(fn (RealisasiProgramKerja $record): string => (static::adalahPerbaikan($record)
                ? 'Perbaiki proposal sesuai catatan revisi, lalu realisasi diteruskan kembali untuk verifikasi. '
                : 'Realisasi akan dikirim untuk verifikasi Rektor beserta dokumen proposalnya. ')
                .static::keteranganKuota($record))
            ->modalSubmitActionLabel(fn (RealisasiProgramKerja $record): string => static::adalahPerbaikan($record) ? 'Kirim Ulang' : 'Ajukan')
            ->modalWidth(Width::TwoExtraLarge)
            ->visible(fn (RealisasiProgramKerja $record): bool => RealisasiProgramKerjaResource::currentUserCanAbility('update')
                && ($record->status === EnumStatusRealisasi::Draft || static::adalahPerbaikan($record)))
            ->fillForm(fn (RealisasiProgramKerja $record): array => [
                'proposal_path' => $record->proposal_path,
                'proposal_original_names' => $record->proposal_original_names,
            ])
            ->schema(fn (RealisasiProgramKerja $record): array => [
                ...CatatanRevisi::komponen($record),
                FileUpload::make('proposal_path')
                    ->label('Dokumen Proposal')
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
                    ->openable(),
            ])
            ->action(function (array $data, RealisasiProgramKerja $record): void {
                if (! $record->dapatDiajukan()) {
                    $penolakan = static::alasanPenolakan($record);

                    Notification::make()
                        ->title($penolakan['title'])
                        ->body($penolakan['body'])
                        ->danger()
                        ->send();

                    return;
                }

                $diajukanKembali = $record->status === EnumStatusRealisasi::Revisi;
                $tujuan = $record->statusTujuanPengajuan();

                $record->update([
                    'proposal_path' => $data['proposal_path'],
                    'proposal_original_names' => $data['proposal_original_names'] ?? null,
                    'status' => $tujuan,
                    'catatan_verifikasi' => null,
                ]);

                $record->catatLog($tujuan, auth()->id(), null, diajukanKembali: $diajukanKembali);

                app(NotifikasiVerifikasi::class)->realisasiDiajukan($record, $diajukanKembali);

                Notification::make()->title('Realisasi berhasil diajukan')->success()->send();
            });
    }

    /**
     * Realisasi yang diminta revisi pada tahap verifikasi proposal, sehingga aksi ini
     * berubah menjadi perbaikan proposal alih-alih pengajuan pertama.
     */
    protected static function adalahPerbaikan(RealisasiProgramKerja $record): bool
    {
        return $record->status === EnumStatusRealisasi::Revisi && ! $record->adalahRevisiLaporan();
    }

    /**
     * Sebab realisasi tidak bisa diajukan, dipisah agar unit kerja tahu persis apa
     * yang harus dibereskan: tahun kerjanya belum/tidak lagi menerima realisasi,
     * ada tunggakan realisasi tahun sebelumnya, atau kuota berjalannya penuh.
     *
     * @return array{title: string, body: string}
     */
    protected static function alasanPenolakan(RealisasiProgramKerja $record): array
    {
        $tahunKerja = $record->tahunKerja();

        if (! $record->tahunKerjaMenerimaPengajuan()) {
            $nama = $tahunKerja?->name ?? 'Tahun kerja realisasi ini';
            $status = $tahunKerja?->status?->getLabel();

            if ($record->melanjutkanAlurBerjalan()) {
                return [
                    'title' => 'Tahun kerja sudah ditutup',
                    'body' => "{$nama} sudah dikunci, sehingga perbaikan atas realisasi ini tidak lagi bisa dikirim. Hubungi admin bila realisasi ini seharusnya masih dituntaskan.",
                ];
            }

            return [
                'title' => 'Tahun kerja belum menerima realisasi',
                'body' => $status === null
                    ? "{$nama} belum ditetapkan sebagai tahun kerja berjalan, sehingga anggarannya belum bisa direalisasikan."
                    : "{$nama} berstatus {$status}, sehingga tidak menerima pengajuan realisasi baru. Realisasi baru hanya bisa diajukan pada tahun kerja yang sedang berjalan.",
            ];
        }

        $unitKerjaId = $record->unitKerjaId();

        if ($unitKerjaId !== null && Setting::blokirTunggakanTahunLalu()) {
            $tunggakan = RealisasiProgramKerja::tunggakanTahunLampau($unitKerjaId)
                ->whereKeyNot($record->getKey())
                ->with('pengajuanProgramKerja.penawaranProgramKerja')
                ->get();

            if ($tunggakan->isNotEmpty()) {
                $daftar = $tunggakan
                    ->map(fn (RealisasiProgramKerja $realisasi): string => $realisasi->name)
                    ->implode(', ');

                return [
                    'title' => 'Masih ada realisasi tahun sebelumnya yang belum tuntas',
                    'body' => "Selesaikan lebih dulu realisasi berikut sebelum mengajukan realisasi tahun kerja yang baru: {$daftar}.",
                ];
            }
        }

        return [
            'title' => 'Kuota realisasi berjalan sudah penuh',
            'body' => 'Unit kerja ini sedang menjalankan '.Setting::maksRealisasiBerjalan().' realisasi. Selesaikan salah satunya lebih dulu sebelum mengajukan realisasi berikutnya. Batas ini diatur di menu Pengaturan Sistem.',
        ];
    }

    /**
     * Ringkasan pemakaian kuota realisasi berjalan pada unit kerja realisasi ini,
     * beserta peringatan tunggakan tahun sebelumnya bila ada.
     */
    protected static function keteranganKuota(RealisasiProgramKerja $record): string
    {
        if ($record->melanjutkanAlurBerjalan()) {
            return 'Perbaikan ini meneruskan realisasi yang sudah berjalan, sehingga tidak memakai kuota realisasi baru.';
        }

        $unitKerjaId = $record->unitKerjaId();
        $maksimal = Setting::maksRealisasiBerjalan();

        if ($unitKerjaId === null) {
            return "Batas realisasi berjalan per unit kerja: {$maksimal}.";
        }

        $berjalan = RealisasiProgramKerja::berjalanUntukUnit($unitKerjaId)->whereKeyNot($record->getKey())->count();
        $keterangan = "Unit kerja ini sedang menjalankan {$berjalan} dari {$maksimal} realisasi yang diizinkan berjalan bersamaan.";

        $tunggakan = RealisasiProgramKerja::tunggakanTahunLampau($unitKerjaId)->whereKeyNot($record->getKey())->count();

        if ($tunggakan > 0) {
            $keterangan .= Setting::blokirTunggakanTahunLalu()
                ? " Termasuk {$tunggakan} realisasi tahun kerja sebelumnya yang belum tuntas dan harus diselesaikan lebih dulu."
                : " Termasuk {$tunggakan} realisasi tahun kerja sebelumnya yang belum tuntas — segera selesaikan lewat menu Penyelesaian Tahun Lalu.";
        }

        return $keterangan;
    }
}
