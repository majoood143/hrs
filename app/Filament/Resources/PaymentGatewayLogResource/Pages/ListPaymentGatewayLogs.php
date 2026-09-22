<?php

namespace App\Filament\Resources\PaymentGatewayLogResource\Pages;

use App\Filament\Resources\PaymentGatewayLogResource;
use App\Filament\Resources\PaymentGatewayLogResource\Widgets\PaymentGatewayLogStatsOverview;
use App\Models\PaymentGatewayLog;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPaymentGatewayLogs extends ListRecords
{
    protected static string $resource = PaymentGatewayLogResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            PaymentGatewayLogStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('admin_payment_gateway_log.tabs.all'))
                ->badge(fn () => PaymentGatewayLog::count()),

            'success' => Tab::make(__('admin_payment_gateway_log.tabs.success'))
                ->modifyQueryUsing(fn (Builder $query) => $query->outcome('success'))
                ->badge(fn () => PaymentGatewayLog::query()->outcome('success')->count())
                ->badgeColor('success'),

            'failed' => Tab::make(__('admin_payment_gateway_log.tabs.failed'))
                ->modifyQueryUsing(fn (Builder $query) => $query->outcome('failed'))
                ->badge(fn () => PaymentGatewayLog::query()->outcome('failed')->count())
                ->badgeColor('danger'),

            'error' => Tab::make(__('admin_payment_gateway_log.tabs.error'))
                ->modifyQueryUsing(fn (Builder $query) => $query->outcome('error'))
                ->badge(fn () => PaymentGatewayLog::query()->outcome('error')->count())
                ->badgeColor('gray'),
        ];
    }
}
