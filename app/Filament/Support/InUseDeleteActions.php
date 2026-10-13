<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;

/**
 * Delete actions that explain a refusal: models using `RefusesDeleteWhileReferenced` refuse to
 * delete a record still in use, and Filament reports that as a failure. `$messages` is the
 * translation file holding `in_use.title|body|bulk` (admin_locations, admin_lookups).
 */
final class InUseDeleteActions
{
    public static function delete(string $messages = 'admin_lookups'): DeleteAction
    {
        return DeleteAction::make()
            ->failureNotificationTitle(__("{$messages}.in_use.title"))
            ->failureNotificationBody(__("{$messages}.in_use.body"));
    }

    public static function bulk(string $messages = 'admin_lookups'): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->missingBulkProcessingFailureNotificationMessage(__("{$messages}.in_use.bulk"));
    }
}
