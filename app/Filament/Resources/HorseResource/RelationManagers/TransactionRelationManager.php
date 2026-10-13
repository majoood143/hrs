<?php

namespace App\Filament\Resources\HorseResource\RelationManagers;

use App\Filament\Resources\TransactionResource;
use App\Models\SiteSetting;
use App\Models\Transaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** A horse's transactions on its page; the same fields as the Transactions screen, without the horse. */
class TransactionRelationManager extends RelationManager
{
    protected static string $relationship = 'transaction';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin_transaction.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_transaction.navigation.label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(TransactionResource::detailFields(withHorse: false))->columns(2);
    }

    public function table(Table $table): Table
    {
        $label = fn (string $key) => __("admin_transaction.fields.{$key}");

        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')->label($label('id')),
                TextColumn::make('service_id')
                    ->label($label('service'))
                    ->formatStateUsing(fn (?int $state): string => TransactionResource::serviceOptions()[$state] ?? '—'),
                TextColumn::make('status')
                    ->label($label('status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Transaction::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('description')->label($label('description'))->limit(40),
                TextColumn::make('amount')
                    ->label($label('amount'))
                    ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state, 3)),
                TextColumn::make('user.name')->label($label('created_by')),
                TextColumn::make('created_at')->label($label('created_at'))->dateTime(),
            ])
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
