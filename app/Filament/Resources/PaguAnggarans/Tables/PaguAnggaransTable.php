<?php

namespace App\Filament\Resources\PaguAnggarans\Tables;

use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Resources\PaguAnggarans\PaguAnggaranResource;
use App\Models\PaguAnggaran;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Services\KonteksProgramKerja;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class PaguAnggaransTable
{
    public static function configure(Table $table): Table
    {
        // Tabel kini memuat tahun berjalan sekaligus tahun perencanaan, sehingga peta
        // pembanding dan pemakaian anggaran disusun per tahun kerja lalu ditutup ke
        // dalam closure kolom — bukan disimpan statis, agar tiap render memakai data
        // terbaru dan tidak membocorkan cache antar permintaan.
        $tahunKerjas = TahunKerja::query()
            ->whereIn('id', KonteksProgramKerja::tahunPerencanaanIds())
            ->get()
            ->keyBy('id');

        $referensiIds = $tahunKerjas->pluck('referensi_tahun_kerja_id')->filter()->unique();

        $referensiMap = static::paguPerTahun($referensiIds->all());
        $penggunaanMap = static::penggunaanPerTahun($tahunKerjas->all());

        /** Nominal pembanding satu baris, mengikuti tahun referensi milik tahun kerjanya. */
        $referensi = function (int|string|null $tahunKerjaId, int|string|null $unitKerjaId) use ($tahunKerjas, $referensiMap): float {
            $referensiId = $tahunKerjas[$tahunKerjaId]?->referensi_tahun_kerja_id;

            return $referensiId === null ? 0.0 : ($referensiMap[$referensiId][$unitKerjaId] ?? 0.0);
        };

        /** Anggaran terpakai satu baris pada tahun kerjanya sendiri. */
        $penggunaan = fn (int|string|null $tahunKerjaId, int|string|null $unitKerjaId): float => $penggunaanMap[$tahunKerjaId][$unitKerjaId] ?? 0.0;

        // Selama seluruh tahun yang tampil memakai satu pembanding yang sama, kolomnya
        // menyebut nama tahun itu; bila berbeda-beda, label jatuh ke sebutan umum.
        $labelReferensi = $referensiIds->count() === 1
            ? 'Anggaran '.(TahunKerja::find($referensiIds->first())?->name ?? 'Referensi')
            : 'Anggaran Referensi';

        return $table
            ->emptyStateHeading('Belum ada pagu anggaran')
            ->emptyStateDescription('Tetapkan tahun kerja berjalan atau tahun kerja perencanaan terlebih dahulu, lalu bagikan pagu anggaran ke tiap unit kerja.')
            ->emptyStateIcon('heroicon-o-currency-dollar')
            ->modifyQueryUsing(fn (Builder $query) => KonteksProgramKerja::applyPerencanaan($query->with('tahunKerja.referensiTahunKerja')))
            ->columns([
                TextColumn::make('tahunKerja.name')
                    ->label('Tahun Kerja')
                    ->badge()
                    ->color(fn (PaguAnggaran $record): string => $record->tahunKerja?->status?->getColor() ?? 'gray')
                    ->sortable(),
                TextColumn::make('unitKerja.name')
                    ->label('Unit Kerja')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('referensi_amount')
                    ->label($labelReferensi)
                    ->money('IDR')
                    ->color('gray')
                    ->alignEnd()
                    ->tooltip(fn (PaguAnggaran $record): ?string => $record->tahunKerja?->referensiTahunKerja?->name)
                    ->state(fn (PaguAnggaran $record): float => $referensi($record->tahun_kerja_id, $record->unit_kerja_id))
                    ->visible($referensiIds->isNotEmpty())
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->money('IDR')
                            ->using(fn (QueryBuilder $query): float => (float) static::barisTampil($query)
                                ->sum(fn (object $row): float => $referensi($row->tahun_kerja_id, $row->unit_kerja_id))),
                    ),
                TextColumn::make('amount')
                    ->label('Pagu Saat Ini')
                    ->money('IDR')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('IDR')),
                TextColumn::make('penggunaan_amount')
                    ->label('Penggunaan Anggaran')
                    ->money('IDR')
                    ->alignEnd()
                    ->state(fn (PaguAnggaran $record): float => $penggunaan($record->tahun_kerja_id, $record->unit_kerja_id))
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->money('IDR')
                            ->using(fn (QueryBuilder $query): float => (float) static::barisTampil($query)
                                ->sum(fn (object $row): float => $penggunaan($row->tahun_kerja_id, $row->unit_kerja_id))),
                    ),
                TextColumn::make('persentase_penggunaan')
                    ->label('Progres')
                    ->alignEnd()
                    ->html()
                    ->state(fn (PaguAnggaran $record): HtmlString => static::progresPenggunaan(
                        (float) $record->amount,
                        $penggunaan($record->tahun_kerja_id, $record->unit_kerja_id),
                    ))
                    ->summarize(
                        Summarizer::make()
                            ->label('Rata-rata')
                            ->using(fn (QueryBuilder $query): string => static::persentaseTotal($query, $penggunaan)),
                    ),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tahun_kerja_id')
                    ->label('Tahun Kerja')
                    ->options(fn (): array => TahunKerja::query()
                        ->whereIn('id', KonteksProgramKerja::tahunPerencanaanIds())
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn (): ?int => TahunKerja::berjalan()?->getKey()),
                SelectFilter::make('unit_kerja_id')
                    ->label('Unit Kerja')
                    ->relationship('unitKerja', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('ubah')
                    ->label('Ubah')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->modalHeading('Ubah Pagu Anggaran')
                    ->modalDescription('Perbarui nominal pagu anggaran untuk unit kerja ini.')
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg')
                    ->visible(fn (PaguAnggaran $record): bool => PaguAnggaranResource::canEdit($record))
                    ->fillForm(fn (PaguAnggaran $record): array => ['amount' => $record->amount])
                    ->schema([
                        Placeholder::make('unit_kerja')
                            ->label('Unit Kerja')
                            ->content(fn (PaguAnggaran $record): string => $record->unitKerja?->name ?? '-'),
                        MoneyInput::make('amount')
                            ->label('Nominal Pagu')
                            ->required(),
                    ])
                    ->successNotificationTitle('Pagu anggaran berhasil diperbarui')
                    ->action(fn (array $data, PaguAnggaran $record): bool => $record->update([
                        'amount' => $data['amount'],
                    ])),
            ]);
    }

    /**
     * Peta nominal pagu per unit kerja untuk sekumpulan tahun kerja, dibaca sekali
     * dalam satu kueri: [tahun_kerja_id][unit_kerja_id] => nominal.
     *
     * @param  array<int, int>  $tahunKerjaIds
     * @return array<int, array<int, float>>
     */
    protected static function paguPerTahun(array $tahunKerjaIds): array
    {
        if ($tahunKerjaIds === []) {
            return [];
        }

        $peta = [];

        foreach (PaguAnggaran::query()->whereIn('tahun_kerja_id', $tahunKerjaIds)->get(['tahun_kerja_id', 'unit_kerja_id', 'amount']) as $pagu) {
            $peta[$pagu->tahun_kerja_id][$pagu->unit_kerja_id] = (float) $pagu->amount;
        }

        return $peta;
    }

    /**
     * Peta anggaran terpakai per unit kerja untuk sekumpulan tahun kerja, diambil
     * dari realisasi milik pengajuan berjalan tiap tahun.
     *
     * @param  array<int, TahunKerja>  $tahunKerjas
     * @return array<int, array<int, float>>
     */
    protected static function penggunaanPerTahun(array $tahunKerjas): array
    {
        $peta = [];

        foreach ($tahunKerjas as $tahunKerja) {
            $peta[$tahunKerja->getKey()] = RealisasiProgramKerja::query()
                ->join('pengajuan_program_kerjas', 'pengajuan_program_kerjas.id', '=', 'realisasi_program_kerjas.pengajuan_program_kerja_id')
                ->whereIn('realisasi_program_kerjas.pengajuan_program_kerja_id', $tahunKerja->pengajuanBerjalan()->select('id'))
                ->groupBy('pengajuan_program_kerjas.unit_kerja_id')
                ->selectRaw('pengajuan_program_kerjas.unit_kerja_id as unit_kerja_id, SUM(realisasi_program_kerjas.anggaran_digunakan) as total')
                ->pluck('total', 'unit_kerja_id')
                ->map(fn (mixed $total): float => (float) $total)
                ->all();
        }

        return $peta;
    }

    /**
     * Persentase penggunaan gabungan (total penggunaan dibanding total pagu) untuk
     * baris yang sedang tampil.
     *
     * @param  QueryBuilder  $query  Query record yang sedang difilter.
     * @param  Closure(int|string|null, int|string|null): float  $penggunaan
     */
    protected static function persentaseTotal(QueryBuilder $query, Closure $penggunaan): string
    {
        $rows = static::barisTampil($query);

        $totalPagu = (float) $rows->sum('amount');
        $totalPenggunaan = (float) $rows->sum(
            fn (object $row): float => $penggunaan($row->tahun_kerja_id, $row->unit_kerja_id),
        );

        $persentase = $totalPagu > 0 ? $totalPenggunaan / $totalPagu * 100 : 0.0;

        return number_format($persentase, 1, ',', '.').'%';
    }

    /**
     * Baris pagu yang sedang tampil beserta kolom yang dibutuhkan ringkasan.
     *
     * @param  QueryBuilder  $query  Query record yang sedang difilter.
     * @return Collection<int, object>
     */
    protected static function barisTampil(QueryBuilder $query): Collection
    {
        return $query->get(['tahun_kerja_id', 'unit_kerja_id', 'amount']);
    }

    /**
     * Cincin progres kecil + persentase penggunaan anggaran satu unit kerja.
     */
    protected static function progresPenggunaan(float $pagu, float $penggunaan): HtmlString
    {
        $persentase = $pagu > 0 ? $penggunaan / $pagu * 100 : 0.0;
        $dash = number_format(max(0.0, min(100.0, $persentase)), 2, '.', '');
        $label = number_format($persentase, 1, ',', '.').'%';

        $warna = match (true) {
            $pagu <= 0 => '#9ca3af',
            $persentase > 100 => '#ef4444',
            $persentase >= 90 => '#f59e0b',
            default => '#22c55e',
        };

        return new HtmlString(<<<HTML
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.5rem;">
                <span style="font-variant-numeric:tabular-nums;">{$label}</span>
                <svg width="22" height="22" viewBox="0 0 36 36" style="flex:none;">
                    <circle cx="18" cy="18" r="15.915" fill="none" stroke="currentColor" stroke-opacity="0.15" stroke-width="4"></circle>
                    <circle cx="18" cy="18" r="15.915" fill="none" stroke="{$warna}" stroke-width="4"
                        stroke-dasharray="{$dash} 100" stroke-dashoffset="25" stroke-linecap="round"></circle>
                </svg>
            </div>
            HTML);
    }
}
