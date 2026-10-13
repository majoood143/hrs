<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttachementResource\Pages\CreateAttachement;
use App\Filament\Resources\AttachementResource\Pages\EditAttachement;
use App\Filament\Resources\AttachementResource\Pages\ListAttachements;
use App\Filament\Resources\AttachementResource\Pages\ViewAttachement;
use App\Filament\Support\AttachmentFields;
use App\Models\Attachement;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AttachementResource extends Resource
{
    protected static ?string $model = Attachement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

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
        return (string) static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(AttachmentFields::form())->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(AttachmentFields::columns())
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin_attachment.fields.type'))
                    ->options(fn () => Attachement::typeOptions()),
                TernaryFilter::make('is_approved')
                    ->label(__('admin_attachment.fields.is_approved')),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
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
