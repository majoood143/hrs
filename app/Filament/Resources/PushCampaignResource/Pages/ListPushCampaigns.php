<?php

namespace App\Filament\Resources\PushCampaignResource\Pages;

use App\Filament\Resources\PushCampaignResource;
use App\Filament\Resources\PushCampaignResource\Widgets\PushActivityChart;
use App\Filament\Resources\PushCampaignResource\Widgets\PushAudience;
use App\Filament\Resources\PushCampaignResource\Widgets\PushFunnel;
use App\Filament\Resources\PushCampaignResource\Widgets\PushStatsOverview;
use App\Filament\Resources\PushCampaignResource\Widgets\PushTopics;
use App\Filament\Resources\PushCampaignResource\Widgets\UpcomingPushCampaigns;
use App\Models\PushCampaign;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPushCampaigns extends ListRecords
{
    protected static string $resource = PushCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PushStatsOverview::class,
            PushActivityChart::class,
            PushFunnel::class,
            PushAudience::class,
            PushTopics::class,
            UpcomingPushCampaigns::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }

    public function getTabs(): array
    {
        $tab = fn (string $key, ?\Closure $query = null) => Tab::make(__('admin_widgets.push.tabs.'.$key))
            ->modifyQueryUsing($query ?? fn (Builder $query) => $query);

        return [
            'all' => $tab('all'),
            'manual' => $tab('manual', fn (Builder $query) => $query->whereNull('source')),
            'automatic' => $tab('automatic', fn (Builder $query) => $query->whereNotNull('source')),
            'scheduled' => $tab('scheduled', fn (Builder $query) => $query->where('status', 'scheduled'))
                ->badge(fn () => PushCampaign::query()->where('status', 'scheduled')->count() ?: null),
            'drafts' => $tab('drafts', fn (Builder $query) => $query->where('status', 'draft')),
            'sent' => $tab('sent', fn (Builder $query) => $query->where('status', 'sent')),
        ];
    }
}
