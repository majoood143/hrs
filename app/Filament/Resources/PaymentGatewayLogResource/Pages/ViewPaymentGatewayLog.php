<?php

namespace App\Filament\Resources\PaymentGatewayLogResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\PaymentGatewayLogResource;
use App\Filament\Resources\ServiceOrderResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ViewPaymentGatewayLog extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = PaymentGatewayLogResource::class;

    public function infolist(Schema $schema): Schema
    {
        $pretty = fn ($state) => new HtmlString(
            '<pre class="text-xs bg-gray-50 dark:bg-gray-800 rounded p-3 overflow-x-auto whitespace-pre-wrap" dir="ltr">'
            .e(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            .'</pre>'
        );

        return $schema
            ->components([
                Section::make(__('admin_payment_gateway_log.sections.overview'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('gateway')
                            ->label(__('admin_payment_gateway_log.columns.gateway'))
                            ->badge(),

                        TextEntry::make('event')
                            ->label(__('admin_payment_gateway_log.columns.event'))
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn (string $state) => Str::of($state)->replace('_', ' ')->headline()),

                        TextEntry::make('outcome')
                            ->label(__('admin_payment_gateway_log.columns.result'))
                            ->badge()
                            ->getStateUsing(fn ($record) => $record->outcome)
                            ->formatStateUsing(fn (string $state) => __('admin_payment_gateway_log.outcomes.'.$state))
                            ->color(fn (string $state) => match ($state) {
                                'success' => 'success',
                                'failed' => 'danger',
                                'pending' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('status_code')
                            ->label(__('admin_payment_gateway_log.columns.status_code'))
                            ->placeholder('—')
                            ->badge(),

                        TextEntry::make('order.order_number')
                            ->label(__('admin_payment_gateway_log.columns.order'))
                            ->placeholder('—')
                            ->url(fn ($record) => $record->service_order_id
                                ? ServiceOrderResource::getUrl('view', ['record' => $record->service_order_id])
                                : null)
                            ->color('primary')
                            ->weight('bold'),

                        TextEntry::make('created_at')
                            ->label(__('admin_payment_gateway_log.columns.created_at'))
                            ->dateTime('M d, Y H:i:s'),
                    ]),

                Section::make(__('admin_payment_gateway_log.sections.payloads'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('request_payload')
                            ->label(__('admin_payment_gateway_log.gateway_logs.request'))
                            ->formatStateUsing($pretty)
                            ->html()
                            ->columnSpan(1),

                        TextEntry::make('response_payload')
                            ->label(__('admin_payment_gateway_log.gateway_logs.response'))
                            ->formatStateUsing($pretty)
                            ->html()
                            ->columnSpan(1),
                    ]),
            ]);
    }
}
