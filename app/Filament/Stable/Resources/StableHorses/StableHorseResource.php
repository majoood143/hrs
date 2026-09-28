<?php

namespace App\Filament\Stable\Resources\StableHorses;

use App\Filament\Stable\Concerns\InteractsWithStable;
use App\Filament\Stable\Resources\StableHorses\Pages\ManageStableHorses;
use App\Models\StableHorse;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** The stable's school horses. Only shown when the stable switches staff on in its settings. */
class StableHorseResource extends Resource
{
    use InteractsWithStable;

    protected static ?string $model = StableHorse::class;

    protected static ?string $tenantRelationshipName = 'horses';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return static::staffEnabled() && parent::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.staff');
    }

    public static function getModelLabel(): string
    {
        return __('stable_panel.horses.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stable_panel.horses.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label(__('stable_panel.fields.name'))->required()->maxLength(255),
                Toggle::make('is_active')->label(__('stable_panel.fields.is_active'))->default(true)->inline(false),
                Textarea::make('notes')->label(__('stable_panel.fields.notes'))->rows(3)->columnSpanFull(),
                FileUpload::make('photo')->label(__('stable_panel.fields.photo'))->image()->disk('public')->directory('stables/horses'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')->label('')->disk('public')->square(),
                TextColumn::make('name')->label(__('stable_panel.fields.name'))->searchable(),
                TextColumn::make('notes')->label(__('stable_panel.fields.notes'))->limit(40)->placeholder('—'),
                IconColumn::make('is_active')->label(__('stable_panel.fields.is_active'))->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStableHorses::route('/')];
    }
}
