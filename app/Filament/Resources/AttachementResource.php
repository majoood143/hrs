<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttachementResource\Pages;
use App\Filament\Resources\AttachementResource\RelationManagers;
use App\Models\Attachement;
use App\Models\Horse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Components\RichEditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Filament\Tables\Columns\ViewColumn;
use Illuminate\Support\Facades\Storage;
use PhpParser\Node\Stmt\Label;

class AttachementResource extends Resource
{
    protected static ?string $model = Attachement::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\RichEditor::make('description')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\FileUpload::make('file_path')
                    ->required(),
                    //->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options(function () {
                        $enumValues = DB::select("SHOW COLUMNS FROM attachements WHERE Field = 'type'")[0]->Type;
                        preg_match('/^enum\((.*)\)$/', $enumValues, $matches);
                        $values = array_map(fn($value) => trim($value, "'"), explode(',', $matches[1]));

                        return array_combine($values, $values);
                    })
                    //->options(Attachement::class)
                    ->required(),
                Forms\Components\Select::make('horse_id')
                    ->relationship(name: 'horse', titleAttribute: 'en_name')
                    ->searchable()
                    ->preload()
                    ->required(),
            Forms\Components\Select::make('user_id')
                    ->relationship(name: 'user', titleAttribute: 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Toggle::make('is_visible_to_client')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\Toggle::make('is_approved')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('file_path')
                ->circular()
                ->label('File')
                ->openUrlInNewTab()
                ->height(50)
                ->width(50),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('horse.en_name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_visible_to_client')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_approved')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListAttachements::route('/'),
            'create' => Pages\CreateAttachement::route('/create'),
            'view' => Pages\ViewAttachement::route('/{record}'),
            'edit' => Pages\EditAttachement::route('/{record}/edit'),
        ];
    }
}
