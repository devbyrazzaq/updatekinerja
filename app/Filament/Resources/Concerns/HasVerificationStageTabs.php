<?php

namespace App\Filament\Resources\Concerns;

use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menyediakan tab "Perlu Diverifikasi", "Sudah Direspon", dan "Ditolak" pada halaman
 * List sebuah resource verifikasi. Resource-nya harus memakai
 * {@see HasVerificationStageScopes}.
 */
trait HasVerificationStageTabs
{
    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        /** @var class-string<\Filament\Resources\Resource>&class-string<HasVerificationStageScopes> $resource */
        $resource = static::getResource();

        $tabs = [
            'perlu' => Tab::make($this->getPendingTabLabel())
                ->icon('heroicon-o-inbox-arrow-down')
                ->badge($resource::pendingStageQuery()->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $resource::applyPendingScope($query)),
            'direspon' => Tab::make($this->getRespondedTabLabel())
                ->icon('heroicon-o-check-circle')
                ->badgeColor('success')
                ->badge($resource::respondedStageQuery()->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $resource::applyRespondedScope($query)),
        ];

        if ($this->hasRejectedTab()) {
            $tabs['ditolak'] = Tab::make($this->getRejectedTabLabel())
                ->icon('heroicon-o-x-circle')
                ->badgeColor('danger')
                ->badge($resource::rejectedStageQuery()->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $resource::applyRejectedScope($query));
        }

        return $tabs;
    }

    protected function getPendingTabLabel(): string
    {
        return 'Perlu Diverifikasi';
    }

    protected function getRespondedTabLabel(): string
    {
        return 'Sudah Direspon';
    }

    protected function getRejectedTabLabel(): string
    {
        return 'Ditolak';
    }

    protected function hasRejectedTab(): bool
    {
        return true;
    }
}
