<?php

namespace App\Filament\Actions;

use App\Exports\Export;
use Filament\Support\Icons\Heroicon;

/**
 * Tombol ekspor data ke berkas .xlsx. Sambungkan sebuah {@see Export} lewat
 * {@see exporter()} dan permission lewat {@see permission()} (dari AuthorizedAction).
 */
class ExcelExportAction extends AuthorizedAction
{
    /**
     * @var class-string<Export>
     */
    protected string $exporter;

    public static function getDefaultName(): ?string
    {
        return 'export';
    }

    /**
     * @param  class-string<Export>  $exporter
     */
    public function exporter(string $exporter): static
    {
        $this->exporter = $exporter;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Ekspor');
        $this->icon(Heroicon::ArrowDownTray);
        $this->color('gray');

        $this->action(fn () => app($this->exporter)->download());
    }
}
