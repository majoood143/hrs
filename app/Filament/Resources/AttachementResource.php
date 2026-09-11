<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\AttachementResource\Pages\ListAttachements;
use App\Filament\Resources\AttachementResource\Pages\CreateAttachement;
use App\Filament\Resources\AttachementResource\Pages\ViewAttachement;
use App\Filament\Resources\AttachementResource\Pages\EditAttachement;
use App\Filament\Resources\AttachementResource\Pages;
use App\Filament\Resources\AttachementResource\RelationManagers;
use App\Models\Attachement;
use App\Models\Horse;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Components\RichEditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Filament\Tables\Columns\ViewColumn;
use Illuminate\Support\Facades\Storage;
use PhpParser\Node\Stmt\Label;

class AttachementResource extends Resource
{
    protected static ?string $model = Attachement::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getModelLabel(): string
    {
        return __('admin_attachment.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_attachment.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_attachment.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->maxLength(255)
                    ->default(null),
                RichEditor::make('description')
                    ->maxLength(255)
                    ->default(null),
                FileUpload::make('file_path')
                    ->required(),
                    //->maxLength(255),
                Select::make('type')
                    ->options(function () {
                        $enumValues = DB::select("SHOW COLUMNS FROM attachements WHERE Field = 'type'")[0]->Type;
                        preg_match('/^enum\((.*)\)$/', $enumValues, $matches);
                        $values = array_map(fn($value) => trim($value, "'"), explode(',', $matches[1]));

                        return array_combine($values, $values);
                    })
                    //->options(Attachement::class)
                    ->required(),
                Select::make('horse_id')
                    ->relationship(name: 'horse', titleAttribute: 'en_name')
                    ->searchable()
                    ->preload()
                    ->required(),
            Select::make('user_id')
                    ->relationship(name: 'user', titleAttribute: 'name')
                    ->searchable()
                    ->preload(),
                Toggle::make('is_visible_to_client')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('is_approved')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                ImageColumn::make('file_path')
                ->circular()
                ->label('File')
                ->openUrlInNewTab()
                ->height(50)
                ->width(50),
                TextColumn::make('type'),
                TextColumn::make('horse.en_name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_visible_to_client')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('is_approved')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttachements::route('/'),
            'create' => CreateAttachement::route('/create'),
            'view' => ViewAttachement::route('/{record}'),
            'edit' => EditAttachement::route('/{record}/edit'),
        ];
    }
}
