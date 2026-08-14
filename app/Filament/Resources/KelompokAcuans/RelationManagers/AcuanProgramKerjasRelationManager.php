<?php

namespace App\Filament\Resources\KelompokAcuans\RelationManagers;

use App\Filament\Actions\ExcelImportAction;
use App\Filament\Resources\AcuanProgramKerjas\AcuanProgramKerjaResource;
use App\Filament\Resources\AcuanProgramKerjas\Schemas\AcuanProgramKerjaForm;
use App\Filament\Resources\AcuanProgramKerjas\Schemas\AcuanProgramKerjaInfolist;
use App\Filament\Resources\AcuanProgramKerjas\Tables\AcuanProgramKerjasTable;
use App\Imports\AcuanProgramKerjasImport;
use App\Models\AcuanProgramKerja;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AcuanProgramKerjasRelationManager extends RelationManager
{
    protected static string $relationship = 'acuanProgramKerjas';

    protected static ?string $title = 'Acuan Program Kerja';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return AcuanProgramKerjaForm::configure($schema, $this->getOwnerRecord()->getKey());
    }

    public function infolist(Schema $schema): Schema
    {
        return AcuanProgramKerjaInfolist::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->emptyStateHeading('Belum ada acuan pada kelompok ini')
            ->emptyStateDescription('Tambahkan acuan secara manual atau impor dari berkas melalui tombol di kanan atas.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('targets'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Program Kerja')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('unitKerja.name')
                    ->label('Unit Kerja')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('kategori.name')
                    ->label('Kategori')
                    ->badge()
                    ->toggleable(),
                ...AcuanProgramKerjasTable::tahunColumns($this->getOwnerRecord()),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Acuan')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Tambah Acuan Program Kerja')
                    ->visible(fn (): bool => AcuanProgramKerjaResource::canCreate())
                    ->fillForm(fn (): array => [
                        'kelompok_acuan_id' => $this->getOwnerRecord()->getKey(),
                        'targets' => AcuanProgramKerjaForm::defaultTargetRows($this->getOwnerRecord()->getKey()),
                    ]),
                ExcelImportAction::make()
                    ->importer(AcuanProgramKerjasImport::class)
                    ->permission('import_acuan_program_kerja')
                    ->formFields([
                        Hidden::make('kelompok_acuan_id')->default($this->getOwnerRecord()->getKey()),
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->slideOver()
                    ->modalHeading(fn (AcuanProgramKerja $record): string => $record->name)
                    ->visible(fn (AcuanProgramKerja $record): bool => AcuanProgramKerjaResource::canView($record)),
            ]);
    }
}
