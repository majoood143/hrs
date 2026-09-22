<?php

namespace App\Filament\Resources;

use App\Enums\FeeType;
use App\Filament\Resources\ServiceFeeSettingResource\Pages\CreateServiceFeeSetting;
use App\Filament\Resources\ServiceFeeSettingResource\Pages\EditServiceFeeSetting;
use App\Filament\Resources\ServiceFeeSettingResource\Pages\ListServiceFeeSettings;
use App\Filament\Support\TranslatableInput;
use App\Models\ServiceFeeSetting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Packstub\FormBuilder\FormBuilder;

/**
 * The customer-facing fee added on top of a service's price. Whatever a rule says today, an
 * existing order keeps the fee it was created with.
 */
class ServiceFeeSettingResource extends Resource
{
    protected static ?string $model = ServiceFeeSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('admin_navigation.payments');
    }

    public static function getModelLabel(): string
    {
        return __('admin_service_fee_setting.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_service_fee_setting.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_service_fee_setting.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Text::make(__('admin_service_fee_setting.notice')),

                        TranslatableInput::grid(fn (string $code, array $meta) => TextInput::make("name.{$code}")
                            ->label(__('admin_service_fee_setting.fields.name').' ('.$meta['name'].')')
                            ->required($code === 'en')
                            ->maxLength(255)),

                        TranslatableInput::grid(fn (string $code, array $meta) => TextInput::make("description.{$code}")
                            ->label(__('admin_service_fee_setting.fields.description').' ('.$meta['name'].')')
                            ->maxLength(500)),

                        Select::make('fee_type')
                            ->label(__('admin_service_fee_setting.fields.fee_type'))
                            ->options(collect(FeeType::cases())->mapWithKeys(fn (FeeType $type) => [$type->value => $type->label()])->all())
                            ->default(FeeType::Percentage->value)
                            ->required()
                            ->native(false),

                        TextInput::make('fee_value')
                            ->label(__('admin_service_fee_setting.fields.fee_value'))
                            ->helperText(__('admin_service_fee_setting.fields.fee_value_helper'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.001),
                    ])
                    ->columns(2),

                Section::make()
                    ->schema([
                        Select::make('service_id')
                            ->label(__('admin_service_fee_setting.fields.service'))
                            ->helperText(__('admin_service_fee_setting.fields.service_helper'))
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('form_id')
                            ->label(__('admin_service_fee_setting.fields.form'))
                            ->helperText(__('admin_service_fee_setting.fields.form_helper'))
                            ->options(fn () => FormBuilder::formModel()::query()->get()->mapWithKeys(fn ($form) => [$form->getKey() => $form->name])->all())
                            ->searchable(),

                        DatePicker::make('effective_from')->label(__('admin_service_fee_setting.fields.effective_from')),
                        DatePicker::make('effective_to')->label(__('admin_service_fee_setting.fields.effective_to')),

                        Toggle::make('is_active')
                            ->label(__('admin_service_fee_setting.fields.is_active'))
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin_service_fee_setting.fields.name'))
                    ->searchable(),
                TextColumn::make('scope_name')
                    ->label(__('admin_service_fee_setting.columns.scope')),
                TextColumn::make('formatted_value')
                    ->label(__('admin_service_fee_setting.columns.rate'))
                    ->badge(),
                TextColumn::make('effective_from')
                    ->label(__('admin_service_fee_setting.fields.effective_from'))
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('effective_to')
                    ->label(__('admin_service_fee_setting.fields.effective_to'))
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label(__('admin_service_fee_setting.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('admin_service_fee_setting.fields.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceFeeSettings::route('/'),
            'create' => CreateServiceFeeSetting::route('/create'),
            'edit' => EditServiceFeeSetting::route('/{record}/edit'),
        ];
    }
}
