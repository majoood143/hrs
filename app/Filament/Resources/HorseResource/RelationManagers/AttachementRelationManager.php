<?php

namespace App\Filament\Resources\HorseResource\RelationManagers;

use App\Filament\Support\AttachmentFields;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** A horse's papers on its page (private files, see `AttachmentFields`). */
class AttachementRelationManager extends RelationManager
{
    protected static string $relationship = 'attachement';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin_attachment.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_attachment.navigation.label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(AttachmentFields::form(withHorse: false))->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns(AttachmentFields::columns(withHorse: false))
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
