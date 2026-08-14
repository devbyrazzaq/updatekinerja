<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusPencairan;
use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\Concerns\HasPencairanPembayaran;
use App\Filament\Resources\VerifikasiBiroKeuangans\VerifikasiBiroKeuanganResource;
use App\Models\JadwalPencairan;
use App\Models\RealisasiProgramKerja;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * Menjadwalkan pencairan: memasukkan realisasi ke sebuah Jadwal Pencairan dan
 * menahannya pada status "Menunggu Anggaran Diberikan" sampai anggaran benar-benar
 * cair. Jadwal baru dapat dibuat langsung dari modal ini.
 *
 * Jadwal yang ditawarkan mengikuti tahun kerja realisasi bersangkutan, bukan tahun
 * kerja yang sedang berjalan, agar tunggakan tahun lalu tetap dapat dijadwalkan pada
 * gelombang pencairan tahunnya sendiri.
 */
class ProsesPencairanAction extends Action
{
    use HasPencairanPembayaran;

    public static function getDefaultName(): ?string
    {
        return 'prosesPencairan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Proses Pencairan')
            ->icon('heroicon-o-calendar-days')
            ->color('primary')
            ->modalHeading('Jadwalkan Pencairan Anggaran')
            ->modalDescription('Masukkan realisasi ke salah satu jadwal pencairan dan tentukan cara pembayarannya. Realisasi berstatus "Menunggu Anggaran Diberikan" sampai anggaran pada jadwal itu ditandai sudah dicairkan.')
            ->modalSubmitActionLabel('Jadwalkan')
            ->modalWidth(Width::Medium)
            ->visible(fn (RealisasiProgramKerja $record): bool => VerifikasiBiroKeuanganResource::currentUserCanVerify()
                && $record->status === EnumStatusRealisasi::VerifikasiKeuangan)
            ->fillForm(fn (RealisasiProgramKerja $record): array => static::nilaiAwalPembayaran($record))
            ->schema([
                Select::make('jadwal_pencairan_id')
                    ->label('Jadwal Pencairan')
                    ->options(fn (?RealisasiProgramKerja $record): array => static::opsiJadwalPencairan($record))
                    ->searchable()
                    ->required()
                    ->helperText('Pilih gelombang pencairan, atau buat jadwal baru bila belum tersedia.')
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nama Jadwal')
                            ->placeholder('Pencairan Awal Bulan Januari')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('tanggal_pencairan')
                            ->label('Tanggal Pencairan')
                            ->required(),
                    ])
                    ->createOptionModalHeading('Buat Jadwal Pencairan')
                    ->createOptionUsing(fn (array $data, ?RealisasiProgramKerja $record): int => JadwalPencairan::create([
                        ...$data,
                        'tahun_kerja_id' => $record?->tahunKerja()?->getKey(),
                        'status' => EnumStatusPencairan::Dijadwalkan,
                        'keuangan_id' => auth()->id(),
                    ])->getKey()),
                ...static::skemaPembayaran(),
                RichEditor::make('catatan')
                    ->label('Catatan')
                    ->toolbarButtons([
                        'bold', 'italic', 'underline', 'strike',
                        'bulletList', 'orderedList', 'link', 'undo', 'redo',
                    ]),
            ])
            ->action(function (array $data, RealisasiProgramKerja $record): void {
                $jadwal = JadwalPencairan::findOrFail($data['jadwal_pencairan_id']);

                $record->jadwalkanPencairan(
                    $jadwal,
                    auth()->id(),
                    $data['catatan'] ?? null,
                    static::metodePembayaranDari($data),
                    static::rekeningBankDari($data),
                );

                Notification::make()
                    ->title('Realisasi dijadwalkan')
                    ->body('Pencairan dijadwalkan pada '.$jadwal->labelPilihan().'. Tandai dicairkan bila anggaran sudah diterima unit kerja.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Jadwal pencairan yang masih terbuka pada tahun kerja realisasi ini.
     *
     * @return array<int, string>
     */
    protected static function opsiJadwalPencairan(?RealisasiProgramKerja $record): array
    {
        $tahunKerjaId = $record?->tahunKerja()?->getKey();

        return JadwalPencairan::query()
            ->belumDicairkan()
            ->when($tahunKerjaId, fn ($query, $id) => $query->where('tahun_kerja_id', $id))
            ->orderBy('tanggal_pencairan')
            ->get()
            ->mapWithKeys(fn (JadwalPencairan $jadwal): array => [$jadwal->getKey() => $jadwal->labelPilihan()])
            ->all();
    }
}
