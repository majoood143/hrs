<?php

namespace App\Filament\Support;

use App\Models\Service;
use App\Models\SiteSetting;
use App\Services\Orders\OrderPricing;
use App\Support\FormOrderSettings;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Spatie\Permission\Models\Role;

/**
 * The "Service & payment" tab on the form editor: link the form to a service (whose price the
 * customer pays, or nothing if it is free), say which fields identify the customer, and choose how
 * they are told about their order. Registered on the form builder in AppServiceProvider.
 */
class FormPaymentTab
{
    public static function make(): Tab
    {
        $hasService = fn (Get $get): bool => filled($get('settings.payment.service_id'));

        return Tab::make(__('admin_form_payment.tab'))
            ->icon('heroicon-o-banknotes')
            ->schema([
                Section::make(__('admin_form_payment.service.heading'))
                    ->description(__('admin_form_payment.service.description'))
                    ->schema([
                        Select::make('settings.payment.service_id')
                            ->label(__('admin_form_payment.service.label'))
                            ->helperText(__('admin_form_payment.service.helper'))
                            ->options(fn () => Service::query()->orderBy('name')->get()->mapWithKeys(fn (Service $service) => [
                                $service->getKey() => $service->name.' · '.SiteSetting::formatCurrency($service->price, 3).($service->is_active ? '' : ' · '.__('admin_form_payment.service.inactive')),
                            ])->all())
                            ->placeholder(__('admin_form_payment.service.none'))
                            ->searchable()
                            ->live(),

                        Text::make(function (Get $get): string {
                            $service = Service::find($get('settings.payment.service_id'));

                            if (! $service) {
                                return '';
                            }

                            $quote = app(OrderPricing::class)->quote($service);

                            return $quote->isFree()
                                ? __('admin_form_payment.service.free')
                                : __('admin_form_payment.service.quote', [
                                    'price' => SiteSetting::formatCurrency($quote->price / 1000, 3),
                                    'fee' => SiteSetting::formatCurrency($quote->fee / 1000, 3),
                                    'vat' => SiteSetting::formatCurrency(($quote->vatOnPrice + $quote->vatOnFee) / 1000, 3),
                                    'total' => SiteSetting::formatCurrency($quote->total() / 1000, 3),
                                ]);
                        })->visible($hasService),
                    ]),

                Section::make(__('admin_form_payment.customer.heading'))
                    ->description(__('admin_form_payment.customer.description'))
                    ->visible($hasService)
                    ->columns(3)
                    ->schema([
                        Select::make('settings.customer.name_field')
                            ->label(__('admin_form_payment.customer.name'))
                            ->options(fn (Get $get) => FormOrderSettings::fieldOptions($get('fields'), ['text']))
                            ->placeholder(__('admin_form_payment.customer.auto')),

                        Select::make('settings.customer.phone_field')
                            ->label(__('admin_form_payment.customer.phone'))
                            ->helperText(__('admin_form_payment.customer.phone_helper'))
                            ->options(fn (Get $get) => FormOrderSettings::fieldOptions($get('fields'), ['phone']))
                            ->required($hasService),

                        Select::make('settings.customer.email_field')
                            ->label(__('admin_form_payment.customer.email'))
                            ->options(fn (Get $get) => FormOrderSettings::fieldOptions($get('fields'), ['email']))
                            ->placeholder(__('admin_form_payment.customer.auto'))
                            ->required(fn (Get $get): bool => $hasService($get) && (bool) $get('settings.notifications.email')),
                    ]),

                Section::make(__('admin_form_payment.review.heading'))
                    ->description(__('admin_form_payment.review.description'))
                    ->visible($hasService)
                    ->schema([
                        Repeater::make('settings.approval.stages')
                            ->label(__('admin_form_payment.review.stages'))
                            ->helperText(__('admin_form_payment.review.stages_helper'))
                            ->addActionLabel(__('admin_form_payment.review.add_stage'))
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => ($state['name']['en'] ?? null) ?: ($state['role'] ?? null))
                            ->defaultItems(0)
                            ->columns(3)
                            ->schema([
                                TranslatableInput::grid(fn (string $code, array $meta) => TextInput::make("name.{$code}")
                                    ->label(__('admin_form_payment.review.stage_name').' ('.$meta['name'].')')
                                    ->required($code === 'en')
                                    ->maxLength(100), 2)->columnSpan(2),

                                Select::make('role')
                                    ->label(__('admin_form_payment.review.role'))
                                    ->helperText(__('admin_form_payment.review.role_helper'))
                                    ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'name')->all())
                                    ->searchable()
                                    ->required(),
                            ]),

                        Toggle::make('settings.approval.requires_document')
                            ->label(__('admin_form_payment.review.requires_document'))
                            ->helperText(__('admin_form_payment.review.requires_document_helper'))
                            ->default(false),
                    ]),

                Section::make(__('admin_form_payment.notifications.heading'))
                    ->description(__('admin_form_payment.notifications.description'))
                    ->visible($hasService)
                    ->columns(2)
                    ->schema([
                        Toggle::make('settings.notifications.email')
                            ->label(__('admin_form_payment.notifications.email'))
                            ->default(true)
                            ->live(),

                        Toggle::make('settings.notifications.sms')
                            ->label(__('admin_form_payment.notifications.sms'))
                            ->helperText(__('admin_form_payment.notifications.sms_helper'))
                            ->default(false),
                    ]),
            ]);
    }
}
