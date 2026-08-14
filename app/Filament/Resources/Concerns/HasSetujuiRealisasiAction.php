<?php

namespace App\Filament\Resources\Concerns;

use App\Filament\Actions\RevisiRealisasiAction;
use App\Filament\Actions\SetujuiRealisasiAction;
use App\Filament\Actions\TolakRealisasiAction;
use Filament\Actions\Action;
use Livewire\Component;

/**
 * Aksi header keputusan verifikasi pada halaman detail realisasi.
 *
 * Logika keputusannya sendiri berada di kelas aksi mandiri
 * ({@see SetujuiRealisasiAction}, {@see RevisiRealisasiAction},
 * {@see TolakRealisasiAction}) agar dapat dipakai juga dari tabel — mis. halaman
 * Penyelesaian Tahun Lalu. Trait ini hanya menambahkan perilaku khas halaman
 * detail: setelah keputusan diambil, pengguna dikembalikan ke daftar tahapnya
 * karena record yang baru diputuskan tidak lagi berada di halaman itu.
 */
trait HasSetujuiRealisasiAction
{
    protected function setujuiRealisasiAction(): Action
    {
        return $this->kembaliKeDaftar(SetujuiRealisasiAction::make());
    }

    protected function revisiRealisasiAction(): Action
    {
        return $this->kembaliKeDaftar(RevisiRealisasiAction::make());
    }

    protected function tolakRealisasiAction(): Action
    {
        return $this->kembaliKeDaftar(TolakRealisasiAction::make());
    }

    protected function kembaliKeDaftar(Action $action): Action
    {
        $resource = static::getResource();

        return $action->after(fn (Component $livewire) => $livewire->redirect($resource::getUrl('index')));
    }
}
