<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SilkColorResource\Pages\CreateSilkColor;
use App\Filament\Resources\SilkColorResource\Pages\EditSilkColor;
use App\Filament\Resources\SilkColorResource\Pages\ListSilkColors;
use App\Models\SilkColor;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** The colours offered in the racing silks designer block. */
class SilkColorResource extends Resource
{
    protected static ?string $model = SilkColor::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static ?int $navigationSort = 96;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_silks.colors.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_silks.colors.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_silks.colors.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('en_name')
                    ->label(__('admin_silks.fields.en_name'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set, $get, string $operation) {
                        if ($operation === 'create' && blank($get('key'))) {
                            $set('key', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('ar_name')
                    ->label(__('admin_silks.fields.ar_name'))
                    ->required()
                    ->maxLength(255),
                ColorPicker::make('hex')
                    ->label(__('admin_silks.fields.hex'))
                    ->required()
                    ->regex('/^#[0-9a-fA-F]{6}$/'),
                TextInput::make('key')
                    ->label(__('admin_silks.fields.key'))
                    ->helperText(__('admin_silks.fields.key_help'))
                    ->required()
                    ->alphaDash()
                    ->maxLength(60)
                    ->unique(ignoreRecord: true),
                TextInput::make('sort')
                    ->label(__('admin_silks.fields.sort'))
                    ->numeric()
                    ->minValue(0)
                    ->default(fn () => (int) SilkColor::query()->max('sort') + 1),
                Toggle::make('is_active')
                    ->label(__('admin_silks.fields.is_active'))
                    ->default(true)
                    ->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('hex')
                    ->label(__('admin_silks.fields.hex')),
                TextColumn::make('en_name')
                    ->label(__('admin_silks.fields.en_name'))
                    ->searchable(),
                TextColumn::make('ar_name')
                    ->label(__('admin_silks.fields.ar_name'))
                    ->searchable(),
                TextColumn::make('key')
                    ->label(__('admin_silks.fields.key'))
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')
                    ->label(__('admin_silks.fields.is_active')),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
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
            'index' => ListSilkColors::route('/'),
            'create' => CreateSilkColor::route('/create'),
            'edit' => EditSilkColor::route('/{record}/edit'),
        ];
    }
}
