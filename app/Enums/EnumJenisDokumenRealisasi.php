<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum EnumJenisDokumenRealisasi: string implements HasColor, HasIcon, HasLabel
{
    case Proposal = 'proposal';
    case Laporan = 'laporan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Proposal => 'Proposal',
            self::Laporan => 'Laporan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Proposal => 'info',
            self::Laporan => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Proposal => Heroicon::OutlinedDocumentText,
            self::Laporan => Heroicon::OutlinedClipboardDocumentCheck,
        };
    }
}
