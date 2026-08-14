<?php

namespace App\Filament\Resources\DaftarProgramKerjas\Schemas;

use App\Enums\EnumJenisDokumenRealisasi;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class DaftarProgramKerjaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Program Kerja')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Nama Program Kerja')->columnSpanFull(),
                        TextEntry::make('tahunKerja.name')->label('Tahun Kerja'),
                        TextEntry::make('unitKerja.name')->label('Unit Kerja'),
                        TextEntry::make('bidang.name')->label('Bidang'),
                        TextEntry::make('kategori.name')->label('Kategori'),
                        TextEntry::make('program.name')->label('Program Induk'),
                        TextEntry::make('rekening.code')->label('Kode Akun')->placeholder('-'),
                        TextEntry::make('target')->label('Target')->placeholder('-'),
                        TextEntry::make('nilai_standar')->label('Nilai Standar')->placeholder('-'),
                        TextEntry::make('satuan_nilai_standar')->label('Satuan Nilai Standar')->placeholder('-'),
                        TextEntry::make('aktifitas')->label('Aktivitas')->html()->placeholder('-')->columnSpanFull(),
                        TextEntry::make('indikator')->label('Indikator')->html()->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('Daftar Pengajuan & Realisasi')
                    ->description('Setiap pengajuan program kerja ini, dengan tabel realisasi menyusul tepat di bawahnya.')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('pengajuanProgramKerjas')
                            ->hiddenLabel()
                            ->columns(5)
                            ->schema([
                                TextEntry::make('unitKerja.name')->label('Unit Kerja')->placeholder('-'),
                                TextEntry::make('user.name')->label('Pemohon')->placeholder('-'),
                                TextEntry::make('alokasi_anggaran')->label('Alokasi Anggaran')->money('IDR'),
                                TextEntry::make('status')->label('Status Pengajuan')->badge(),
                                TextEntry::make('persentase_ketercapaian')
                                    ->label('Ketercapaian Pengajuan')
                                    ->state(fn (PengajuanProgramKerja $record): string => $record->persentaseKetercapaian().'%')
                                    ->badge()
                                    ->color('info'),
                                RepeatableEntry::make('realisasiProgramKerjas')
                                    ->label('Realisasi')
                                    ->columnSpanFull()
                                    ->table([
                                        TableColumn::make('Nama Realisasi'),
                                        TableColumn::make('Anggaran Digunakan'),
                                        TableColumn::make('Ketercapaian'),
                                        TableColumn::make('Status'),
                                        TableColumn::make('Tanggal'),
                                        TableColumn::make('Aksi')->alignment(Alignment::End),
                                    ])
                                    ->schema([
                                        TextEntry::make('name')->placeholder('-'),
                                        TextEntry::make('anggaran_digunakan')->money('IDR'),
                                        TextEntry::make('persentase_ketercapaian')->suffix('%')->placeholder('-'),
                                        TextEntry::make('status')->badge(),
                                        TextEntry::make('created_at')->dateTime('d M Y H:i'),
                                        Actions::make([
                                            static::realisasiActionGroup(),
                                        ]),
                                    ])
                                    ->placeholder('Belum ada realisasi untuk pengajuan ini.'),
                            ])
                            ->placeholder('Belum ada pengajuan untuk program kerja ini.'),
                    ]),
            ]);
    }

    /**
     * Grup aksi per realisasi: detail kegiatan, daftar proposal, dan daftar
     * laporan. Proposal & laporan dibuka sebagai daftar berkas, lalu pratinjau.
     */
    protected static function realisasiActionGroup(): ActionGroup
    {
        return ActionGroup::make([
            static::detailKegiatanAction(),
            static::daftarDokumenAction('proposal', EnumJenisDokumenRealisasi::Proposal),
            static::daftarDokumenAction('laporan', EnumJenisDokumenRealisasi::Laporan),
        ])
            ->label('Aksi')
            ->icon(Heroicon::EllipsisVertical)
            ->button();
    }

    protected static function detailKegiatanAction(): Action
    {
        return Action::make('detailKegiatan')
            ->label('Detail Kegiatan')
            ->icon(Heroicon::OutlinedInformationCircle)
            ->color('gray')
            ->modalHeading('Detail Kegiatan')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->schema([
                Grid::make(2)->schema([
                    TextEntry::make('name')->label('Nama Kegiatan')->columnSpanFull(),
                    TextEntry::make('start_datetime')->label('Mulai')->dateTime('d F Y H:i')->placeholder('-'),
                    TextEntry::make('end_datetime')->label('Selesai')->dateTime('d F Y H:i')->placeholder('-'),
                    TextEntry::make('anggaran_digunakan')->label('Anggaran Digunakan')->money('IDR'),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('persentase_ketercapaian')->label('Ketercapaian Target')->suffix('%')->placeholder('-'),
                    TextEntry::make('status_anggaran')->label('Status Anggaran')->badge()->placeholder('-'),
                    TextEntry::make('status_penyelesaian_anggaran')->label('Tindak Lanjut Selisih')->badge()->placeholder('-'),
                    TextEntry::make('description')->label('Deskripsi')->html()->placeholder('-')->columnSpanFull(),
                    TextEntry::make('evaluasi_pengerjaan')->label('Evaluasi Pengerjaan')->html()->placeholder('-')->columnSpanFull(),
                ]),
            ]);
    }

    /**
     * Daftar berkas (proposal/laporan) satu realisasi. Setiap baris punya tombol
     * pratinjau yang membuka modal preview berkas.
     */
    protected static function daftarDokumenAction(string $name, EnumJenisDokumenRealisasi $jenis): Action
    {
        $relationship = $jenis === EnumJenisDokumenRealisasi::Proposal ? 'proposals' : 'laporans';

        return Action::make($name)
            ->label($jenis->getLabel())
            ->icon($jenis->getIcon())
            ->color($jenis->getColor())
            ->badge(fn (RealisasiProgramKerja $record): ?int => ($jumlah = $record->{$relationship}()->count()) > 0 ? $jumlah : null)
            ->slideOver()
            ->modalHeading('Daftar '.$jenis->getLabel())
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->schema([
                View::make('filament.infolists.realisasi-documents')
                    ->viewData([
                        'relationship' => $relationship,
                        'emptyLabel' => 'Belum ada '.strtolower($jenis->getLabel()).'.',
                        'dapatPratinjau' => true,
                    ]),
            ]);
    }
}
