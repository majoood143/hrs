<?php

namespace App\Filament\Stable\Resources\StableTrainers;

use App\Filament\Stable\Concerns\InteractsWithStable;
use App\Filament\Stable\Resources\StableTrainers\Pages\ManageStableTrainers;
use App\Models\StableTrainer;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Only shown when the stable switches staff on in its settings. */
class StableTrainerResource extends Resource
{
    use InteractsWithStable;

    protected static ?string $model = StableTrainer::class;

    protected static ?string $tenantRelationshipName = 'trainers';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 10;

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
        return __('stable_panel.trainers.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stable_panel.trainers.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('en_name')->label(__('stable_panel.fields.en_name'))->required()->maxLength(255),
                TextInput::make('ar_name')->label(__('stable_panel.fields.ar_name'))->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                TextInput::make('phone')->label(__('stable_panel.fields.phone'))->tel()->maxLength(32),
                Toggle::make('is_active')->label(__('stable_panel.fields.is_active'))->default(true)->inline(false),
                FileUpload::make('photo')->label(__('stable_panel.fields.photo'))->image()->avatar()->disk('public')->directory('stables/trainers'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')->label('')->disk('public')->circular(),
                TextColumn::make('en_name')
                    ->label(__('stable_panel.fields.name'))
                    ->formatStateUsing(fn (StableTrainer $record) => $record->name)
                    ->searchable(['en_name', 'ar_name']),
                TextColumn::make('phone')->label(__('stable_panel.fields.phone'))->placeholder('—'),
                IconColumn::make('is_active')->label(__('stable_panel.fields.is_active'))->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStableTrainers::route('/')];
    }
}
