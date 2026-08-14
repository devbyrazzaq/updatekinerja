<?php

namespace App\Filament\Resources\Concerns;

use App\Models\TahunKerja;
use App\Models\UnitKerja;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Penyaring bersama seluruh menu Verifikasi: unit kerja pengaju dan rentang waktu
 * pengajuan. Dipakai bersama karena setiap tahap verifikasi menampilkan antrian yang
 * sama-sama perlu dipersempit per unit kerja dan per periode pengajuan, walau
 * modelnya berbeda (Pengajuan menyimpan `unit_kerja_id` langsung, Realisasi
 * mewarisinya dari pengajuan induk).
 */
trait HasVerificationTableFilters
{
    /**
     * Penyaring unit kerja pengaju.
     *
     * @param  string|null  $relationship  Jalur relasi menuju model pemilik
     *                                     `unit_kerja_id`, mis. 'pengajuanProgramKerja'.
     *                                     Null bila kolomnya ada pada model itu sendiri.
     */
    protected static function unitKerjaFilter(?string $relationship = null): SelectFilter
    {
        return SelectFilter::make('unit_kerja_id')
            ->label('Unit Kerja')
            ->placeholder('Semua Unit Kerja')
            ->options(fn (): array => UnitKerja::query()->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->query(function (Builder $query, array $data) use ($relationship): Builder {
                $unitKerjaId = $data['value'] ?? null;

                if (blank($unitKerjaId)) {
                    return $query;
                }

                if ($relationship === null) {
                    return $query->where('unit_kerja_id', $unitKerjaId);
                }

                return $query->whereHas(
                    $relationship,
                    fn (Builder $query): Builder => $query->where('unit_kerja_id', $unitKerjaId),
                );
            });
    }

    /**
     * Penyaring tahun kerja. Sejak sistem menjalankan dua slot konteks sekaligus,
     * satu antrian verifikasi bisa memuat lebih dari satu tahun; penyaring ini
     * memberi bawaan tahun berjalan agar pekerjaan harian tidak berubah. Pada menu
     * yang memang tidak menggarap tahun berjalan — mis. Verifikasi Pengajuan
     * Perencanaan — bawaannya jatuh ke tahun pertama yang boleh dipilih.
     *
     * @param  string  $relationship  Jalur relasi menuju model pemilik `tahun_kerja_id`,
     *                                mis. 'penawaranProgramKerja'.
     * @param  array<int, int>  $tahunKerjaIds  Tahun kerja yang boleh dipilih pada fase ini.
     */
    protected static function tahunKerjaFilter(string $relationship, array $tahunKerjaIds): SelectFilter
    {
        return SelectFilter::make('tahun_kerja_id')
            ->label('Tahun Kerja')
            ->placeholder('Semua Tahun Kerja')
            ->options(fn (): array => TahunKerja::query()
                ->whereIn('id', $tahunKerjaIds)
                ->orderByDesc('tahun')
                ->pluck('name', 'id')
                ->all())
            ->default(function () use ($tahunKerjaIds): ?int {
                $berjalanId = TahunKerja::berjalan()?->getKey();

                return in_array($berjalanId, $tahunKerjaIds, true)
                    ? $berjalanId
                    : ($tahunKerjaIds[0] ?? null);
            })
            ->query(fn (Builder $query, array $data): Builder => $query->when(
                $data['value'] ?? null,
                fn (Builder $query, mixed $tahunKerjaId): Builder => $query->whereHas(
                    $relationship,
                    fn (Builder $query): Builder => $query->where('tahun_kerja_id', $tahunKerjaId),
                ),
            ));
    }

    /**
     * Penyaring rentang waktu pengajuan: dari tanggal sampai dengan tanggal, keduanya
     * inklusif dan boleh diisi salah satu saja.
     *
     * Filter dikenali lewat namanya, sehingga satu tabel yang memasang lebih dari satu
     * penyaring rentang waktu (mis. waktu pencatatan dan periode pelaksanaan) wajib
     * memberi `$name` berbeda pada yang kedua — kalau tidak, yang belakangan menimpa
     * yang lebih dulu.
     */
    protected static function waktuPengajuanFilter(string $column = 'created_at', string $label = 'Waktu Pengajuan', string $name = 'waktu_pengajuan'): Filter
    {
        return Filter::make($name)
            ->label($label)
            ->schema([
                DatePicker::make('dari')
                    ->label('Dari Tanggal')
                    ->native(false)
                    ->maxDate(fn (Get $get): mixed => $get('sampai')),
                DatePicker::make('sampai')
                    ->label('Sampai Dengan')
                    ->native(false)
                    ->minDate(fn (Get $get): mixed => $get('dari')),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when(
                    $data['dari'] ?? null,
                    fn (Builder $query, string $tanggal): Builder => $query->whereDate($column, '>=', $tanggal),
                )
                ->when(
                    $data['sampai'] ?? null,
                    fn (Builder $query, string $tanggal): Builder => $query->whereDate($column, '<=', $tanggal),
                ))
            ->indicateUsing(function (array $data) use ($label): array {
                $indicators = [];

                if (filled($data['dari'] ?? null)) {
                    $indicators[] = Indicator::make($label.' dari '.static::formatTanggalIndikator($data['dari']))
                        ->removeField('dari');
                }

                if (filled($data['sampai'] ?? null)) {
                    $indicators[] = Indicator::make($label.' sampai '.static::formatTanggalIndikator($data['sampai']))
                        ->removeField('sampai');
                }

                return $indicators;
            });
    }

    protected static function formatTanggalIndikator(string $tanggal): string
    {
        return Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y');
    }
}
