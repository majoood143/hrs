<?php

namespace App\Filament\Resources\GameCampaignResource\RelationManagers;

use App\Filament\Resources\GameAttemptResource;
use App\Filament\Resources\GameAttemptResource\AttemptActions;
use App\Models\GameAttempt;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The campaign's races, newest first, with the same review as admin → Games → Races. */
class AttemptsRelationManager extends RelationManager
{
    protected static string $relationship = 'attempts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('games.admin.attempts');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return GameAttemptResource::columns($table, withCampaign: false)
            ->recordActions([
                Action::make('open')->label(__('filament-actions::view.single.label'))->icon('heroicon-o-eye')->color('gray')
                    ->url(fn (GameAttempt $record) => GameAttemptResource::getUrl('view', ['record' => $record])),
                ...AttemptActions::all(),
            ]);
    }
}
