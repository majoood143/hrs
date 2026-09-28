<?php

namespace App\Filament\Resources\StableResource;

use App\Enums\FeeType;
use App\Enums\StableApprovalStatus;
use App\Filament\Stable\Pages\Dashboard;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Models\User;
use App\Services\Stables\StableApproval;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Gate;

/**
 * The admins' decisions on a stable an owner registered: approve (with its commission: there is no
 * global one), reject or suspend (with a reason the owner sees), or change the commission later.
 */
final class StableApprovalActions
{
    /** @return array<int, Action> */
    public static function all(): array
    {
        return [self::approve(), self::commission(), self::linkOwner(), self::reject(), self::suspend()];
    }

    /**
     * Opens the stable's own panel (/stable) to work on its behalf: services, schedules, slots,
     * settings, bookings. Everything done there is logged and the owner can see it.
     */
    public static function openPanel(): Action
    {
        return Action::make('openStablePanel')
            ->label(__('admin_stable.admin_mode.open'))
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('gray')
            ->visible(fn (Stable $record) => ($user = auth()->user()) instanceof User && $user->managesStables())
            ->url(fn (Stable $record) => Dashboard::getUrl(panel: 'stable', tenant: $record))
            ->openUrlInNewTab();
    }

    /** @return array<int, mixed> */
    private static function commissionFields(): array
    {
        return [
            Select::make('commission_type')
                ->label(__('admin_stable.fields.commission_type'))
                ->options(collect(FeeType::cases())->mapWithKeys(fn (FeeType $type) => [$type->value => $type->label()]))
                ->default(FeeType::Percentage->value)
                ->required()
                ->live()
                ->native(false),
            TextInput::make('commission_value')
                ->label(__('admin_stable.fields.commission_value'))
                ->helperText(__('admin_stable.approval.commission_hint'))
                ->numeric()
                ->minValue(0)
                ->maxValue(fn (Get $get) => $get('commission_type') === FeeType::Percentage->value ? 100 : 99999)
                ->step(0.001)
                ->prefix(fn (Get $get) => $get('commission_type') === FeeType::Fixed->value ? SiteSetting::currencyHtml() : null)
                ->suffix(fn (Get $get) => $get('commission_type') === FeeType::Percentage->value ? '%' : null)
                ->required(),
        ];
    }

    private static function allowed(Stable $record): bool
    {
        return Gate::allows('update', $record);
    }

    private static function admin(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function approve(): Action
    {
        return Action::make('approveStable')
            ->label(__('admin_stable.approval.approve'))
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (Stable $record) => self::allowed($record) && ! $record->isApproved())
            ->modalHeading(fn (Stable $record) => __('admin_stable.approval.approve_heading', ['stable' => $record->en_name]))
            ->modalDescription(__('admin_stable.approval.approve_description'))
            ->fillForm(fn (Stable $record) => [
                'commission_type' => $record->commission_type?->value ?? FeeType::Percentage->value,
                'commission_value' => $record->commission_value,
            ])
            ->schema(self::commissionFields())
            ->action(function (Stable $record, array $data): void {
                app(StableApproval::class)->approve($record, FeeType::from($data['commission_type']), $data['commission_value'], self::admin());

                Notification::make()->success()->title(__('admin_stable.approval.approved'))->send();
            });
    }

    public static function commission(): Action
    {
        return Action::make('stableCommission')
            ->label(__('admin_stable.approval.set_commission'))
            ->icon('heroicon-o-receipt-percent')
            ->color('gray')
            ->visible(fn (Stable $record) => self::allowed($record) && $record->isApproved())
            ->fillForm(fn (Stable $record) => [
                'commission_type' => $record->commission_type?->value ?? FeeType::Percentage->value,
                'commission_value' => $record->commission_value,
            ])
            ->modalDescription(__('admin_stable.approval.commission_description'))
            ->schema(self::commissionFields())
            ->action(function (Stable $record, array $data): void {
                app(StableApproval::class)->setCommission($record, FeeType::from($data['commission_type']), $data['commission_value']);

                Notification::make()->success()->title(__('admin_stable.approval.commission_saved'))->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('rejectStable')
            ->label(__('admin_stable.approval.reject'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Stable $record) => self::allowed($record) && $record->approval_status === StableApprovalStatus::Pending)
            ->schema([
                Textarea::make('reason')
                    ->label(__('admin_stable.approval.reason'))
                    ->helperText(__('admin_stable.approval.reason_hint'))
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(function (Stable $record, array $data): void {
                app(StableApproval::class)->reject($record, $data['reason'], self::admin());

                Notification::make()->success()->title(__('admin_stable.approval.rejected'))->send();
            });
    }

    public static function suspend(): Action
    {
        return Action::make('suspendStable')
            ->label(__('admin_stable.approval.suspend'))
            ->icon('heroicon-o-pause-circle')
            ->color('warning')
            ->visible(fn (Stable $record) => self::allowed($record) && $record->isApproved() && $record->owners()->exists())
            ->modalDescription(__('admin_stable.approval.suspend_description'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('admin_stable.approval.reason'))
                    ->helperText(__('admin_stable.approval.reason_hint'))
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(function (Stable $record, array $data): void {
                app(StableApproval::class)->suspend($record, $data['reason'], self::admin());

                Notification::make()->success()->title(__('admin_stable.approval.suspended'))->send();
            });
    }

    /**
     * Gives an existing directory stable to an owner who signed up (instead of approving the copy
     * they registered), or adds a second owner.
     */
    public static function linkOwner(): Action
    {
        return Action::make('linkStableOwner')
            ->label(__('admin_stable.approval.link_owner'))
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            ->visible(fn (Stable $record) => self::allowed($record))
            ->modalDescription(__('admin_stable.approval.link_owner_description'))
            ->schema([
                Select::make('user_id')
                    ->label(__('admin_stable.fields.owner'))
                    ->options(fn (Stable $record) => User::query()
                        ->where('type', User::TYPE_STABLE_OWNER)
                        ->whereNotIn('id', $record->members()->pluck('users.id'))
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (User $user) => [$user->getKey() => $user->name.' · '.$user->email]))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Stable $record, array $data): void {
                $record->members()->syncWithoutDetaching([(int) $data['user_id'] => ['role' => 'owner']]);

                Notification::make()->success()->title(__('admin_stable.approval.owner_linked'))->send();
            });
    }
}
