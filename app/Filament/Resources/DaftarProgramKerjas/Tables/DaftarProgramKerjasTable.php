<?php

namespace App\Filament\Resources\DaftarProgramKerjas\Tables;

use App\Enums\EnumStatusPengajuan;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\AcuanTarget;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DaftarProgramKerjasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada program kerja yang ditawarkan')
            ->emptyStateDescription('Program kerja untuk tahun kerja aktif akan tampil di sini.')
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->modifyQueryUsing(function (Builder $query) use ($table): void {
                $unitKerjaId = $table->getLivewire()->unitKerjaId ?? null;

                if ($unitKerjaId !== null) {
                    $query->where('unit_kerja_id', $unitKerjaId);
                }

                $query
                    ->withCount('pengajuanProgramKerjas')
                    ->withSum('pengajuanProgramKerjas', 'alokasi_anggaran');
            })
            ->columns([
                TextColumn::make('unitKerja.name')
                    ->label('Unit Kerja')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Program Kerja')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('kategori.name')
                    ->label('Kategori')
                    ->badge(),
                TextColumn::make('program.name')
                    ->label('Program Induk')
                    ->toggleable(),
                TextColumn::make('target')
                    ->label('Target')
                    ->formatStateUsing(fn (?string $state): ?string => $state !== null ? AcuanTarget::formatNilai($state) : null),
                TextColumn::make('pengajuan_program_kerjas_count')
                    ->label('Jumlah Pengajuan')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
                TextColumn::make('pengajuan_program_kerjas_sum_alokasi_anggaran')
                    ->label('Total Alokasi Anggaran')
                    ->money('IDR')
                    ->placeholder('Rp 0'),
                TextColumn::make('rekening.code')
                    ->label('Kode Akun')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kategori_id')->label('Kategori')->relationship('kategori', 'name')->searchable()->preload(),
                SelectFilter::make('program_id')->label('Program Induk')->relationship('program', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make()->label('Detail'),
                static::ajukanAction(),
            ]);
    }

    public static function ajukanAction(): Action
    {
        return Action::make('ajukan')
            ->label('Ajukan')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            // Resource diambil dari halaman aktif karena tabel ini dipakai bersama oleh
            // salinan Perencanaan yang permission-nya berbeda.
            ->visible(fn (Page $livewire): bool => $livewire::getResource()::currentUserCanAbility('ajukan'))
            ->modalHeading('Ajukan Program Kerja')
            ->schema([
                MoneyInput::make('alokasi_anggaran')
                    ->label('Pengajuan Anggaran')
                    ->required(),
                RichEditor::make('deskripsi_kegiatan')
                    ->label('Deskripsi Kegiatan')
                    ->toolbarButtons([
                        'bold', 'italic', 'underline', 'strike',
                        'bulletList', 'orderedList', 'link', 'undo', 'redo',
                    ]),
            ])
            ->action(function (array $data, PenawaranProgramKerja $record): void {
                PengajuanProgramKerja::create([
                    'penawaran_program_kerja_id' => $record->id,
                    'unit_kerja_id' => auth()->user()?->unit_kerja_id ?? $record->unit_kerja_id,
                    'user_id' => auth()->id(),
                    'alokasi_anggaran' => $data['alokasi_anggaran'],
                    'deskripsi_kegiatan' => $data['deskripsi_kegiatan'] ?? null,
                    'status' => EnumStatusPengajuan::Diajukan,
                ]);

                Notification::make()->title('Program kerja berhasil diajukan')->success()->send();
            });
    }
}
