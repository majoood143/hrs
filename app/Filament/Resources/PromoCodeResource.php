<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\PromoCodeResource\Pages\CreatePromoCode;
use App\Filament\Resources\PromoCodeResource\Pages\EditPromoCode;
use App\Filament\Resources\PromoCodeResource\Pages\ListPromoCodes;
use App\Models\PromoCode;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Support\PhoneNumber;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin → Payments → Promo codes (the booking project's PromoCodeResource): discount codes for stable
 * sessions and lesson packages. Shown while General Settings → Modules → Promo codes is on.
 */
class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 26;

    public static function canAccess(): bool
    {
        return parent::canAccess() && PromoCode::enabled();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin_navigation.payments');
    }

    public static function getModelLabel(): string
    {
        return __('promo_codes.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('promo_codes.admin.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('promo_codes.admin.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        $f = fn (string $key) => __('promo_codes.admin.fields.'.$key);
        $percentage = fn (Get $get) => $get('discount_type') === PromoCode::PERCENTAGE;
        $currency = SiteSetting::currency()['code'];

        return $schema->columns(1)->components([
            Section::make(__('promo_codes.admin.sections.details'))
                ->icon('heroicon-o-ticket')
                ->columns(2)
                ->schema([
                    Text::make(__('promo_codes.admin.notice'))->columnSpanFull(),
                    TextInput::make('code')->label($f('code'))->helperText($f('code_helper'))
                        ->required()->maxLength(50)->alphaNum()
                        ->dehydrateStateUsing(fn (?string $state) => PromoCode::normalize($state))
                        ->unique(ignoreRecord: true)
                        ->extraInputAttributes(['dir' => 'ltr', 'style' => 'text-transform: uppercase']),
                    TextInput::make('name')->label($f('name'))->required()->maxLength(255),
                    Textarea::make('description')->label($f('description'))->maxLength(500)->rows(2)->columnSpanFull(),
                ]),
            Section::make(__('promo_codes.admin.sections.discount'))
                ->icon('heroicon-o-receipt-percent')
                ->columns(3)
                ->schema([
                    Select::make('discount_type')->label($f('discount_type'))->required()->native(false)->live()
                        ->options(['percentage' => __('promo_codes.admin.types.percentage'), 'fixed' => __('promo_codes.admin.types.fixed')]),
                    TextInput::make('discount_value')
                        ->label(fn (Get $get) => $percentage($get) ? $f('discount_value_percentage') : $f('discount_value_fixed'))
                        ->required()->numeric()->minValue(0.001)->step(0.001)
                        ->maxValue(fn (Get $get) => $percentage($get) ? 100 : null)
                        ->suffix(fn (Get $get) => $percentage($get) ? '%' : $currency),
                    TextInput::make('max_discount_amount')->label($f('max_discount_amount'))->helperText($f('max_discount_amount_helper'))
                        ->numeric()->minValue(0.001)->step(0.001)->suffix($currency)
                        ->visible($percentage),
                ]),
            Section::make(__('promo_codes.admin.sections.validity'))
                ->icon('heroicon-o-calendar')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('valid_from')->label($f('valid_from'))->native(false)->seconds(false),
                    DateTimePicker::make('valid_until')->label($f('valid_until'))->native(false)->seconds(false)->after('valid_from'),
                    TextInput::make('max_uses')->label($f('max_uses'))->helperText($f('max_uses_helper'))->integer()->minValue(1),
                    Toggle::make('is_active')->label($f('is_active'))->default(true)->inline(false),
                ]),
            Section::make(__('promo_codes.admin.sections.scope'))
                ->icon('heroicon-o-user-group')
                ->columns(2)
                ->schema([
                    Select::make('stable_id')->label($f('stable'))->helperText($f('stable_helper'))
                        ->relationship('stable', 'en_name')
                        ->getOptionLabelFromRecordUsing(fn (Stable $record) => $record->name)
                        ->searchable(['en_name', 'ar_name'])->preload()->native(false),
                    TextInput::make('restricted_mobile')->label($f('restricted_mobile'))->helperText($f('restricted_mobile_helper'))
                        ->tel()->maxLength(20)
                        ->dehydrateStateUsing(fn (?string $state) => PhoneNumber::normalize($state))
                        ->extraInputAttributes(['dir' => 'ltr']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $f = fn (string $key) => __('promo_codes.admin.fields.'.$key);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('stable')->withCount(['orders as uses' => fn (Builder $q) => $q->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])]))
            ->columns([
                TextColumn::make('code')->label($f('code'))->searchable()->copyable()->badge()->color('primary'),
                TextColumn::make('name')->label($f('name'))->searchable()->limit(30)
                    ->description(fn (PromoCode $record) => $record->restricted_mobile ? "\u{2066}+{$record->restricted_mobile}\u{2069}" : null),
                TextColumn::make('discount_value')->label(__('promo_codes.admin.sections.discount'))->formatStateUsing(fn (PromoCode $record) => $record->label()),
                TextColumn::make('stable.en_name')->label($f('stable'))->badge()
                    ->getStateUsing(fn (PromoCode $record) => $record->stable?->name ?? __('promo_codes.admin.all_stables'))
                    ->color(fn (PromoCode $record) => $record->stable_id ? 'info' : 'gray'),
                TextColumn::make('uses')->label($f('used'))->alignCenter()
                    ->formatStateUsing(fn (PromoCode $record, $state) => $record->max_uses !== null ? "{$state} / {$record->max_uses}" : (string) $state),
                TextColumn::make('valid_until')->label($f('valid_until'))->dateTime('j M Y')->placeholder(__('promo_codes.admin.no_expiry'))
                    ->color(fn (PromoCode $record) => $record->valid_until?->isPast() ? 'danger' : null),
                TextColumn::make('source')->label($f('source'))->badge()->color('gray')
                    ->getStateUsing(fn (PromoCode $record) => __('promo_codes.admin.sources.'.($record->source ?? 'admin'))),
                IconColumn::make('is_active')->label($f('is_active'))->boolean()->alignCenter(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label($f('is_active')),
                SelectFilter::make('source')->label($f('source'))
                    ->options(['game' => __('promo_codes.admin.sources.game')]),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromoCodes::route('/'),
            'create' => CreatePromoCode::route('/create'),
            'edit' => EditPromoCode::route('/{record}/edit'),
        ];
    }
}
