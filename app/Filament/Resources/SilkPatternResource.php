<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SilkPatternResource\Pages\CreateSilkPattern;
use App\Filament\Resources\SilkPatternResource\Pages\EditSilkPattern;
use App\Filament\Resources\SilkPatternResource\Pages\ListSilkPatterns;
use App\Models\SilkPattern;
use App\Support\Silks\SilksCatalog;
use App\Support\Silks\SilksTemplate;
use App\Support\Silks\SilkSvgSanitizer;
use Closure;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

/** The patterns offered for each part (body, sleeves, cap) in the racing silks designer block. */
class SilkPatternResource extends Resource
{
    protected static ?string $model = SilkPattern::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 97;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_silks.patterns.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_silks.patterns.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_silks.patterns.plural');
    }

    /** @return array<string, string> */
    public static function areaOptions(): array
    {
        return collect(SilksTemplate::AREAS)->mapWithKeys(fn ($area) => [$area => __('silks.areas.'.$area)])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Section::make()
                    ->columnSpan(['lg' => 2])
                    ->columns(2)
                    ->schema([
                        Select::make('area')
                            ->label(__('admin_silks.fields.area'))
                            ->options(static::areaOptions())
                            ->default('body')
                            ->required()
                            ->native(false)
                            ->live(),
                        TextInput::make('key')
                            ->label(__('admin_silks.fields.key'))
                            ->helperText(__('admin_silks.fields.key_help'))
                            ->required()
                            ->alphaDash()
                            ->maxLength(60)
                            ->notIn([SilksCatalog::PLAIN])
                            ->validationMessages(['not_in' => __('admin_silks.errors.plain_reserved')])
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('area', $get('area'))),
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
                        Textarea::make('svg')
                            ->label(__('admin_silks.fields.svg'))
                            ->helperText(function (Get $get) {
                                [$width, $height] = SilksTemplate::BOXES[$get('area')] ?? SilksTemplate::BOXES['body'];

                                return __('admin_silks.fields.svg_help', ['width' => $width, 'height' => $height]);
                            })
                            ->required()
                            ->rows(12)
                            ->extraInputAttributes(['dir' => 'ltr', 'spellcheck' => 'false', 'class' => 'font-mono text-xs'])
                            ->live(onBlur: true)
                            ->rules([fn () => function (string $attribute, mixed $value, Closure $fail) {
                                $result = SilkSvgSanitizer::sanitize(is_string($value) ? $value : null);

                                if ($result['svg'] === null) {
                                    $fail(__('admin_silks.errors.svg_invalid'));
                                } elseif ($result['dropped']) {
                                    $fail(__('admin_silks.errors.svg_dropped', ['items' => implode(', ', $result['dropped'])]));
                                }
                            }])
                            ->columnSpanFull(),
                        TextInput::make('sort')
                            ->label(__('admin_silks.fields.sort'))
                            ->numeric()
                            ->minValue(0)
                            ->default(fn () => (int) SilkPattern::query()->max('sort') + 1),
                        Toggle::make('is_active')
                            ->label(__('admin_silks.fields.is_active'))
                            ->default(true)
                            ->inline(false),
                    ]),
                Section::make(__('admin_silks.fields.preview'))
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        ViewField::make('preview')
                            ->hiddenLabel()
                            ->view('filament.silks.pattern-preview')
                            ->dehydrated(false),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('svg')
                    ->label(__('admin_silks.fields.preview'))
                    ->view('filament.silks.pattern-thumb'),
                TextColumn::make('en_name')
                    ->label(__('admin_silks.fields.en_name'))
                    ->searchable(),
                TextColumn::make('ar_name')
                    ->label(__('admin_silks.fields.ar_name'))
                    ->searchable(),
                TextColumn::make('area')
                    ->label(__('admin_silks.fields.area'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => static::areaOptions()[$state] ?? $state),
                TextColumn::make('key')
                    ->label(__('admin_silks.fields.key'))
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')
                    ->label(__('admin_silks.fields.is_active')),
            ])
            ->filters([
                SelectFilter::make('area')
                    ->label(__('admin_silks.fields.area'))
                    ->options(static::areaOptions()),
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
            'index' => ListSilkPatterns::route('/'),
            'create' => CreateSilkPattern::route('/create'),
            'edit' => EditSilkPattern::route('/{record}/edit'),
        ];
    }
}
