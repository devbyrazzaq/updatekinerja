<?php

namespace App\Filament\Clusters\PengaturanSistem\Pages;

use App\Filament\Clusters\PengaturanSistem\PengaturanSistemCluster;
use App\Filament\Pages\Concerns\HasPageAuthorization;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Kerangka satu halaman pengaturan: mengisi form dari Setting, menyimpannya kembali, dan
 * memberi tahu dampaknya. Turunannya cukup menyebut komponen isian, nilai awal, dan cara
 * menyimpannya — tata letak, tombol simpan, serta otorisasinya sudah sama di semua halaman.
 *
 * @property-read Schema $form
 */
abstract class HalamanPengaturan extends Page
{
    use HasPageAuthorization;

    protected static ?string $cluster = PengaturanSistemCluster::class;

    protected string $view = 'filament.clusters.pengaturan-sistem.halaman';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Pengaturan Sistem';
    }

    public function mount(): void
    {
        $this->form->fill($this->nilaiTersimpan());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make($this->isian())
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Simpan Pengaturan')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->simpan($this->form->getState());

        Notification::make()
            ->title('Pengaturan berhasil disimpan')
            ->body($this->ringkasanDampak())
            ->success()
            ->send();
    }

    /**
     * Nilai yang tersimpan saat ini, dipakai mengisi form ketika halaman dibuka.
     *
     * @return array<string, mixed>
     */
    abstract protected function nilaiTersimpan(): array;

    /**
     * Komponen isian halaman ini.
     *
     * @return array<int, Component>
     */
    abstract protected function isian(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    abstract protected function simpan(array $data): void;

    /**
     * Keterangan singkat dampak pengaturan setelah disimpan.
     */
    abstract protected function ringkasanDampak(): string;
}
