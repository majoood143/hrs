<?php

namespace App\Filament\Stable\Resources\BookingSlots\Pages;

use App\Filament\Stable\Resources\BookingSlots\BookingSlotResource;
use App\Models\BookingSlot;
use App\Models\StableOffering;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Carbon;

class ManageBookingSlots extends ManageRecords
{
    protected static string $resource = BookingSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('stable_panel.slots.add_one_off'))
                ->icon('heroicon-o-plus')
                ->mutateDataUsing(function (array $data, CreateAction $action): array {
                    $offering = StableOffering::query()->findOrFail($data['stable_offering_id']);
                    $start = BookingSlot::normalizeTime($data['start_time']);
                    $date = substr((string) $data['date'], 0, 10);

                    if (BookingSlot::query()->where('stable_offering_id', $offering->getKey())->where('date', $date)->where('start_time', $start)->exists()) {
                        Notification::make()->danger()->title(__('stable_panel.slots.duplicate'))->send();
                        $action->halt();
                    }

                    return [
                        ...$data,
                        'date' => $date,
                        'start_time' => $start,
                        'end_time' => Carbon::parse($date.' '.$start)->addMinutes($offering->duration_minutes)->format('H:i:s'),
                        'stable_schedule_id' => null,
                    ];
                }),
        ];
    }
}
