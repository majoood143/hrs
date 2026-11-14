<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Filament\Resources\CustomerResource\RelationManagers\EventsRelationManager;
use App\Models\CrmSegment;
use App\Models\Customer;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The CRM's profiles: everyone who signed in with their phone (website or app), with the optional
 * profile from the app and what they did there (the events timeline on each profile). Read-only: an
 * admin does not edit a customer's identity. The ID number shows masked; the full number needs the
 * RevealNationalId:Customer permission and is logged.
 */
class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('admin_navigation.crm');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_customer.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_customer.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_customer.navigation.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phone')
                    ->label(__('admin_customer.columns.phone'))
                    // isolated left-to-right, so the Arabic admin shows "+968…", not "…968+"
                    ->formatStateUsing(fn (string $state) => "\u{2066}+{$state}\u{2069}")
                    ->searchable()
                    ->copyable()
                    ->copyableState(fn (Customer $record) => '+'.$record->phone)
                    ->weight('medium'),
                TextColumn::make('name')
                    ->label(__('admin_customer.columns.name'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('admin_customer.columns.email'))
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('country')
                    ->label(__('admin_crm.fields.country'))
                    ->placeholder('—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('gender')
                    ->label(__('admin_crm.fields.gender'))
                    ->formatStateUsing(fn (string $state) => __('admin_crm.genders.'.$state))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('source')
                    ->label(__('admin_crm.fields.source'))
                    ->formatStateUsing(fn (string $state) => __('admin_crm.sources.'.$state))
                    ->badge()
                    ->color(fn (string $state) => $state === 'app' ? 'success' : 'gray'),
                TextColumn::make('completion')
                    ->label(__('admin_crm.fields.completion'))
                    ->getStateUsing(fn (Customer $record) => $record->profileCompletion().'%')
                    ->toggleable(),
                IconColumn::make('marketing_opt_in')
                    ->label(__('admin_crm.fields.marketing'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('events_max_occurred_at')
                    ->label(__('admin_crm.fields.last_activity'))
                    ->max('events', 'occurred_at')
                    ->since()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->label(__('admin_customer.columns.orders'))
                    ->counts('orders')
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label(__('admin_customer.columns.last_login'))
                    ->dateTime('M d, Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin_customer.columns.created_at'))
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source')->label(__('admin_crm.fields.source'))->options(CrmSegmentResource::sourceOptions()),
                SelectFilter::make('gender')->label(__('admin_crm.fields.gender'))->options(CrmSegmentResource::genderOptions()),
                SelectFilter::make('country')->label(__('admin_crm.fields.country'))->options(fn () => CrmSegmentResource::countryOptions()),
                TernaryFilter::make('marketing_opt_in')->label(__('admin_crm.fields.marketing')),
                SelectFilter::make('segment')
                    ->label(__('admin_crm.segments.label'))
                    ->options(fn () => CrmSegment::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) && ($segment = CrmSegment::find($data['value']))
                        ? $query->whereIn('customers.id', $segment->customers()->select('customers.id'))
                        : $query),
                Filter::make('did')
                    ->label(__('admin_crm.segments.fields.did'))
                    ->schema([
                        Select::make('event')->label(__('admin_crm.segments.fields.did'))->options(fn () => CrmSegmentResource::eventOptions())->searchable(),
                        TextInput::make('days')->label(__('admin_crm.segments.fields.within_days'))->integer()->minValue(1)->maxValue(365)->default(30),
                    ])
                    ->query(fn (Builder $query, array $data) => filled($data['event'] ?? null)
                        ? $query->whereIn('customers.id', CrmSegment::queryFor(['did' => $data['event'], 'within_days' => (int) ($data['days'] ?: 30)])->select('customers.id'))
                        : $query)
                    ->indicateUsing(fn (array $data) => filled($data['event'] ?? null)
                        ? __('admin_crm.segments.did_indicator', ['event' => __('admin_crm.events.'.$data['event']), 'days' => (int) ($data['days'] ?: 30)])
                        : null),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('last_login_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [EventsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
