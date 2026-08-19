<?php

namespace App\Filament\Actions;

use App\Exports\Export;
use App\Reports\Report;
use App\Reports\TabularReport;
use Closure;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;

/**
 * Tombol unduh laporan PDF. Sambungkan sumber datanya lewat {@see reporter()} —
 * sebuah {@see Report} untuk laporan bertata letak khusus, atau langsung sebuah
 * {@see Export} yang otomatis dibungkus {@see TabularReport} — dan permission lewat
 * {@see permission()} (dari AuthorizedAction).
 */
class PdfReportAction extends AuthorizedAction
{
    /**
     * @var class-string<Report|Export>
     */
    protected string $reporter;

    public static function getDefaultName(): ?string
    {
        return 'report';
    }

    /**
     * @param  class-string<Report|Export>  $reporter
     */
    public function reporter(string $reporter): static
    {
        $this->reporter = $reporter;

        return $this;
    }

    /**
     * Tanyakan cakupan laporan lebih dahulu: modal berisi $schema muncul sebelum
     * berkasnya dibuat, dan jawabannya diteruskan ke `action()` sebagai `$data`.
     * Tanpa ini tombol langsung mengunduh.
     *
     * @param  array<int, Component>|Closure  $schema
     */
    public function cakupan(array|Closure $schema): static
    {
        return $this
            ->schema($schema)
            ->modalHeading('Unduh Laporan PDF')
            ->modalDescription('Tentukan cakupan yang dilaporkan. Pilihan ini hanya berlaku untuk berkas yang diunduh, tidak mengubah tampilan halaman.')
            ->modalSubmitActionLabel('Unduh Laporan')
            ->modalIcon(Heroicon::DocumentArrowDown);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Laporan PDF');
        $this->icon(Heroicon::DocumentArrowDown);
        $this->color('gray');

        $this->action(fn () => $this->resolveReport()->download());
    }

    protected function resolveReport(): Report
    {
        $reporter = app($this->reporter);

        return $reporter instanceof Export
            ? new TabularReport($reporter)
            : $reporter;
    }
}
