<?php

namespace App\Filament\Resources\ServiceOrderResource\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Filament\Resources\ServiceOrderResource;
use App\Models\OrderDocument;
use App\Models\OrderStage;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Services\Notifications\OrderNotifier;
use App\Services\Orders\OrderDocumentService;
use App\Services\Orders\OrderReceiptPdf;
use App\Services\Orders\OrderRefundService;
use App\Services\Orders\OrderWorkflow;
use App\Services\Orders\RefundException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\URL;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;

class ViewServiceOrder extends ViewRecord
{
    protected static string $resource = ServiceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActivityLogTimelineTableAction::make('activityLog')
                ->label(__('admin_service_order.actions.activity_log'))
                ->withRelations(['documents', 'refunds', 'stages'])
                ->limit(20),

            Action::make('approve')
                ->label(fn (ServiceOrder $record): string => __('admin_service_order.actions.approve', ['stage' => app(OrderWorkflow::class)->currentStage($record)?->name]))
                ->icon('heroicon-o-hand-thumb-up')
                ->color('success')
                ->modalHeading(__('admin_service_order.actions.approve_heading'))
                ->modalDescription(__('admin_service_order.actions.approve_desc'))
                ->visible(fn (ServiceOrder $record): bool => app(OrderWorkflow::class)->canDecide($record, auth()->user()))
                ->schema([
                    Textarea::make('comment')->label(__('admin_service_order.actions.comment'))->rows(3),
                ])
                ->action(function (array $data, ServiceOrder $record): void {
                    $result = app(OrderWorkflow::class)->approve($record, auth()->user(), $data['comment'] ?? null);
                    $this->record->refresh();

                    $result === OrderWorkflow::OK
                        ? Notification::make()->title(__('admin_service_order.notifications.approved'))->success()->send()
                        : Notification::make()->title(__('admin_service_order.notifications.cannot_decide'))->danger()->send();
                }),

            Action::make('reject')
                ->label(__('admin_service_order.actions.reject'))
                ->icon('heroicon-o-hand-thumb-down')
                ->color('danger')
                ->modalHeading(__('admin_service_order.actions.reject'))
                ->modalDescription(fn (ServiceOrder $record): string => __($record->isPaid() ? 'admin_service_order.actions.reject_desc_paid' : 'admin_service_order.actions.reject_desc'))
                ->visible(fn (ServiceOrder $record): bool => app(OrderWorkflow::class)->canDecide($record, auth()->user()))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('admin_service_order.actions.reason'))
                        ->helperText(__('admin_service_order.actions.reason_helper'))
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data, ServiceOrder $record): void {
                    $result = app(OrderWorkflow::class)->reject($record, auth()->user(), $data['reason']);
                    $this->record->refresh();

                    $result === OrderWorkflow::OK
                        ? Notification::make()->title(__('admin_service_order.notifications.rejected'))->warning()->send()
                        : Notification::make()->title(__('admin_service_order.notifications.cannot_decide'))->danger()->send();
                }),

            Action::make('complete')
                ->label(__('admin_service_order.actions.complete'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('admin_service_order.actions.complete'))
                ->modalDescription(__('admin_service_order.actions.complete_desc'))
                ->visible(fn (ServiceOrder $record): bool => app(OrderWorkflow::class)->canComplete($record))
                ->action(function (ServiceOrder $record): void {
                    $done = app(OrderWorkflow::class)->complete($record, auth()->id());
                    $this->record->refresh();

                    $done
                        ? Notification::make()->title(__('admin_service_order.notifications.completed'))->success()->send()
                        : Notification::make()->title(__('admin_service_order.notifications.not_completable'))->danger()->send();
                }),

            ActionGroup::make([
                Action::make('uploadDocument')
                    ->label(__('admin_service_order.actions.upload_document'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->modalHeading(__('admin_service_order.actions.upload_document'))
                    ->modalDescription(__('admin_service_order.actions.upload_document_desc'))
                    ->visible(fn (ServiceOrder $record): bool => ($record->isPaid() || $record->isFree()) && $record->status !== OrderStatus::Rejected)
                    ->schema(fn (ServiceOrder $record): array => [
                        TextInput::make('title')->label(__('admin_service_order.actions.document_title'))->required()->maxLength(150),
                        FileUpload::make('file')
                            ->label(__('admin_service_order.actions.document_file'))
                            ->disk(OrderDocument::DISK)
                            ->directory(OrderDocument::DIRECTORY.'/'.$record->getKey())
                            ->visibility('private')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->storeFileNamesIn('original_name')
                            ->required(),
                    ])
                    ->action(function (array $data, ServiceOrder $record): void {
                        app(OrderDocumentService::class)->attach($record, $data['title'], $data['file'], $data['original_name'] ?? null, auth()->id());
                        $this->record->refresh();

                        Notification::make()->title(__('admin_service_order.notifications.document_added'))->success()->send();
                    }),

                Action::make('replaceDocument')
                    ->label(__('admin_service_order.actions.replace_document'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->modalHeading(__('admin_service_order.actions.replace_document'))
                    ->modalDescription(__('admin_service_order.actions.replace_document_desc'))
                    ->visible(fn (ServiceOrder $record): bool => $record->documents()->exists() && $record->status !== OrderStatus::Rejected)
                    ->schema(fn (ServiceOrder $record): array => [
                        Select::make('document_id')
                            ->label(__('admin_service_order.actions.document'))
                            ->options($record->documents()->pluck('title', 'id')->all())
                            ->required()
                            ->native(false),
                        TextInput::make('title')->label(__('admin_service_order.actions.document_new_title'))->helperText(__('admin_service_order.actions.document_new_title_helper'))->maxLength(150),
                        FileUpload::make('file')
                            ->label(__('admin_service_order.actions.document_file'))
                            ->disk(OrderDocument::DISK)
                            ->directory(OrderDocument::DIRECTORY.'/'.$record->getKey())
                            ->visibility('private')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->storeFileNamesIn('original_name')
                            ->required(),
                    ])
                    ->action(function (array $data, ServiceOrder $record): void {
                        $document = $record->documents()->find($data['document_id']);

                        if (! $document) {
                            Notification::make()->title(__('admin_service_order.notifications.document_missing'))->danger()->send();

                            return;
                        }

                        app(OrderDocumentService::class)->replace($document, $data['file'], $data['original_name'] ?? null, $data['title'] ?? null, auth()->id());
                        $this->record->refresh();

                        Notification::make()->title(__('admin_service_order.notifications.document_replaced'))->success()->send();
                    }),

                Action::make('deleteDocument')
                    ->label(__('admin_service_order.actions.delete_document'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('admin_service_order.actions.delete_document'))
                    ->modalDescription(fn (ServiceOrder $record): string => __($record->status === OrderStatus::Completed ? 'admin_service_order.actions.delete_document_desc_completed' : 'admin_service_order.actions.delete_document_desc'))
                    ->visible(fn (ServiceOrder $record): bool => $record->documents()->exists())
                    ->schema(fn (ServiceOrder $record): array => [
                        Select::make('document_id')
                            ->label(__('admin_service_order.actions.document'))
                            ->options($record->documents()->pluck('title', 'id')->all())
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data, ServiceOrder $record): void {
                        $document = $record->documents()->find($data['document_id']);

                        if (! $document) {
                            Notification::make()->title(__('admin_service_order.notifications.document_missing'))->danger()->send();

                            return;
                        }

                        app(OrderDocumentService::class)->delete($document, auth()->id());
                        $this->record->refresh();

                        Notification::make()->title(__('admin_service_order.notifications.document_deleted'))->success()->send();
                    }),
            ])
                ->label(__('admin_service_order.action_groups.documents'))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->button()
                ->dropdownPlacement('bottom-end'),

            ActionGroup::make([
                Action::make('refund')
                    ->label(__('admin_service_order.actions.refund'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->modalHeading(__('admin_service_order.actions.refund'))
                    ->modalDescription(fn (ServiceOrder $record): string => __('admin_service_order.actions.refund_desc', ['max' => SiteSetting::formatCurrency($record->refundableAmount(), 3)]))
                    ->visible(fn (ServiceOrder $record): bool => $record->isPaid() && $record->refundableAmount() > 0)
                    ->schema(fn (ServiceOrder $record): array => [
                        TextInput::make('amount')
                            ->label(__('admin_service_order.actions.refund_amount'))
                            ->numeric()
                            ->step(0.001)
                            ->minValue(0.001)
                            ->maxValue($record->refundableAmount())
                            ->default($record->refundableAmount())
                            ->required(),
                        Select::make('method')
                            ->label(__('admin_service_order.actions.refund_method'))
                            ->options(['gateway' => __('admin_service_order.refund.methods.gateway'), 'manual' => __('admin_service_order.refund.methods.manual')])
                            ->default('gateway')
                            ->native(false)
                            ->required(),
                        TextInput::make('reference')->label(__('admin_service_order.actions.refund_reference'))->maxLength(255),
                        Textarea::make('reason')->label(__('admin_service_order.actions.refund_reason'))->rows(2),
                    ])
                    ->action(function (array $data, ServiceOrder $record): void {
                        try {
                            app(OrderRefundService::class)->record($record, (int) round((float) $data['amount'] * 1000), $data['reason'] ?? null, auth()->id(), $data['method'], $data['reference'] ?? null);
                        } catch (RefundException $e) {
                            Notification::make()->title(__('admin_service_order.notifications.refund_failed'))->body($e->getMessage())->danger()->send();

                            return;
                        }

                        $this->record->refresh();
                        Notification::make()->title(__('admin_service_order.notifications.refunded'))->success()->send();
                    }),

                Action::make('resend')
                    ->label(__('admin_service_order.actions.resend'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->modalHeading(__('admin_service_order.actions.resend'))
                    ->modalDescription(__('admin_service_order.actions.resend_desc'))
                    ->visible(fn (ServiceOrder $record): bool => $record->isPaid() || $record->isFree())
                    ->schema(fn (ServiceOrder $record): array => [
                        Select::make('type')
                            ->label(__('admin_service_order.actions.resend_type'))
                            ->options(array_filter([
                                OrderNotifier::RECEIVED => __('admin_service_order.notification_types.order_received'),
                                OrderNotifier::COMPLETED => $record->status === OrderStatus::Completed ? __('admin_service_order.notification_types.order_completed') : null,
                            ]))
                            ->default(OrderNotifier::RECEIVED)
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data, ServiceOrder $record): void {
                        $channels = app(OrderNotifier::class)->notify($record, $data['type'], force: true);
                        $this->record->refresh();

                        $channels === []
                            ? Notification::make()->title(__('admin_service_order.notifications.resend_none'))->warning()->send()
                            : Notification::make()->title(__('admin_service_order.notifications.resent', ['channels' => implode(' + ', array_map('strtoupper', $channels))]))->success()->send();
                    }),

                Action::make('receipt')
                    ->label(__('admin_service_order.actions.receipt'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn (ServiceOrder $record): bool => app(OrderReceiptPdf::class)->available($record))
                    ->action(function (ServiceOrder $record) {
                        $receipts = app(OrderReceiptPdf::class);

                        return response()->streamDownload(
                            fn () => print ($receipts->render($record)),
                            $receipts->filename($record),
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
            ])
                ->label(__('admin_service_order.action_groups.more'))
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')
                ->button()
                ->dropdownPlacement('bottom-end'),
        ];
    }

    /** Why the order cannot be completed yet, for the admin (empty when nothing stands in the way, or completing is not in question). */
    private function completionHint(ServiceOrder $record): string
    {
        $workflow = app(OrderWorkflow::class);

        if (! ($record->isPaid() || $record->isFree()) || ! in_array($record->status, [OrderStatus::New, OrderStatus::InReview, OrderStatus::Processing], true)) {
            return '';
        }

        return collect($workflow->completionBlockers($record))
            ->map(fn (string $blocker) => __('admin_service_order.blockers.'.$blocker))
            ->implode(' ');
    }

    public function infolist(Schema $schema): Schema
    {
        $money = fn ($state) => SiteSetting::formatCurrency($state, 3);

        return $schema
            ->components([
                Section::make(__('admin_service_order.sections.overview'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('order_number')
                            ->label(__('admin_service_order.fields.order_number'))
                            ->weight('bold')
                            ->copyable(),
                        TextEntry::make('service.name')
                            ->label(__('admin_service_order.fields.service'))
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->label(__('admin_service_order.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                            ->color(fn (OrderStatus $state) => $state->color()),
                        TextEntry::make('payment_status')
                            ->label(__('admin_service_order.fields.payment_status'))
                            ->badge()
                            ->formatStateUsing(fn (PaymentStatus $state) => $state->label())
                            ->color(fn (PaymentStatus $state) => $state->color()),
                        Text::make(fn (ServiceOrder $record): string => __('admin_service_order.fields.refund_due', ['amount' => SiteSetting::formatCurrency($record->refundableAmount(), 3)]))
                            ->color('danger')
                            ->weight('bold')
                            ->columnSpanFull()
                            ->visible(fn (ServiceOrder $record) => $record->refundDue()),
                        Text::make(fn (ServiceOrder $record): string => $this->completionHint($record))
                            ->color('warning')
                            ->columnSpanFull()
                            ->visible(fn (ServiceOrder $record) => $this->completionHint($record) !== ''),
                    ]),

                Section::make(__('admin_service_order.sections.customer'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('customer_name')->label(__('admin_service_order.fields.customer_name'))->placeholder('—'),
                        TextEntry::make('customer_email')->label(__('admin_service_order.fields.customer_email'))->placeholder('—')->copyable(),
                        TextEntry::make('customer_phone')->label(__('admin_service_order.fields.customer_phone'))->placeholder('—')->copyable(),
                        TextEntry::make('locale')->label(__('admin_service_order.fields.locale'))->badge(),
                        TextEntry::make('customer_account')
                            ->label(__('admin_service_order.fields.customer_account'))
                            ->state(fn (ServiceOrder $record) => $record->customer_id ? __('admin_service_order.fields.linked') : __('admin_service_order.fields.guest'))
                            ->badge()
                            ->color(fn (ServiceOrder $record) => $record->customer_id ? 'success' : 'gray'),
                    ]),

                Section::make(__('admin_service_order.sections.money'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('price')->label(__('admin_service_order.fields.price'))->formatStateUsing($money),
                        TextEntry::make('fee_amount')->label(__('admin_service_order.fields.fee_amount'))->formatStateUsing($money),
                        TextEntry::make('vat_on_price')->label(__('admin_service_order.fields.vat_on_price'))->formatStateUsing($money),
                        TextEntry::make('vat_on_fee')->label(__('admin_service_order.fields.vat_on_fee'))->formatStateUsing($money),
                        TextEntry::make('total')->label(__('admin_service_order.fields.total'))->formatStateUsing($money)->weight('bold'),
                        TextEntry::make('client_share')
                            ->label(__('admin_service_order.fields.client_share'))
                            ->getStateUsing(fn (ServiceOrder $record) => $record->clientShare())
                            ->formatStateUsing($money),
                        TextEntry::make('fee_share')
                            ->label(__('admin_service_order.fields.fee_share'))
                            ->getStateUsing(fn (ServiceOrder $record) => $record->feeShare())
                            ->formatStateUsing($money),
                        TextEntry::make('commission_amount')
                            ->label(__('admin_service_order.fields.commission'))
                            ->formatStateUsing($money)
                            ->visible(fn (ServiceOrder $record) => (float) $record->commission_amount > 0),
                        TextEntry::make('vat_on_commission')
                            ->label(__('admin_service_order.fields.vat_on_commission'))
                            ->formatStateUsing($money)
                            ->visible(fn (ServiceOrder $record) => (float) $record->vat_on_commission > 0),
                        TextEntry::make('due_to_us')
                            ->label(__('admin_service_order.fields.due_to_us'))
                            ->getStateUsing(fn (ServiceOrder $record) => $record->dueToUs())
                            ->formatStateUsing($money)
                            ->weight('bold'),
                        TextEntry::make('refunded_amount')->label(__('admin_service_order.fields.refunded_amount'))->formatStateUsing($money),
                    ]),

                Section::make(__('admin_service_order.sections.payment'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('payment_method')
                            ->label(__('admin_service_order.fields.payment_method'))
                            ->formatStateUsing(fn (?PaymentGateway $state) => $state?->label())
                            ->placeholder('—'),
                        TextEntry::make('payment_reference')->label(__('admin_service_order.fields.payment_reference'))->placeholder('—')->copyable(),
                        TextEntry::make('receipt_number')->label(__('admin_service_order.fields.receipt_number'))->placeholder('—'),
                        TextEntry::make('paid_at')->label(__('admin_service_order.fields.paid_at'))->dateTime('M d, Y H:i')->placeholder('—'),
                    ]),

                Section::make(__('admin_service_order.sections.review'))
                    ->collapsible()
                    ->visible(fn (ServiceOrder $record) => $record->stages->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('stages')
                            ->hiddenLabel()
                            ->columns(5)
                            ->schema([
                                TextEntry::make('position')->hiddenLabel()->prefix('#'),
                                TextEntry::make('name')->hiddenLabel()->weight('bold'),
                                TextEntry::make('role_name')->hiddenLabel()->badge()->color('gray'),
                                TextEntry::make('status')
                                    ->hiddenLabel()
                                    ->badge()
                                    ->formatStateUsing(fn (string $state) => __('admin_service_order.stage_statuses.'.$state))
                                    ->color(fn (string $state) => match ($state) {
                                        OrderStage::APPROVED => 'success',
                                        OrderStage::REJECTED => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('decider.name')
                                    ->hiddenLabel()
                                    ->placeholder('—')
                                    ->helperText(fn (OrderStage $record) => $record->comment),
                            ]),
                    ]),

                Section::make(__('admin_service_order.sections.documents'))
                    ->collapsible()
                    ->visible(fn (ServiceOrder $record) => $record->documents->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('documents')
                            ->hiddenLabel()
                            ->columns(3)
                            ->schema([
                                TextEntry::make('title')->hiddenLabel()->weight('bold')
                                    ->url(fn (OrderDocument $record) => URL::signedRoute('orders.documents.admin', $record), shouldOpenInNewTab: true)
                                    ->color('primary'),
                                TextEntry::make('size')->hiddenLabel()->formatStateUsing(fn ($state, OrderDocument $record) => $record->humanSize()),
                                TextEntry::make('created_at')->hiddenLabel()->dateTime('M d, Y H:i'),
                            ]),
                    ]),

                Section::make(__('admin_service_order.sections.refunds'))
                    ->collapsible()
                    ->visible(fn (ServiceOrder $record) => $record->refunds->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('refunds')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('created_at')->hiddenLabel()->dateTime('M d, Y H:i'),
                                TextEntry::make('amount')->hiddenLabel()->formatStateUsing($money)->weight('bold'),
                                TextEntry::make('method')->hiddenLabel()->badge()->color('gray')->formatStateUsing(fn (string $state) => __('admin_service_order.refund.methods.'.$state)),
                                TextEntry::make('reference')->hiddenLabel()->placeholder('—')->helperText(fn ($record) => $record->reason),
                            ]),
                    ]),

                Section::make(__('admin_service_order.sections.notifications'))
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('notifications')
                            ->hiddenLabel()
                            ->columns(5)
                            ->placeholder(__('admin_service_order.notifications.none'))
                            ->schema([
                                TextEntry::make('created_at')->hiddenLabel()->dateTime('M d, Y H:i:s'),
                                TextEntry::make('channel')->hiddenLabel()->badge()->color('gray'),
                                TextEntry::make('type')->hiddenLabel()->formatStateUsing(fn (string $state) => __('admin_service_order.notification_types.'.$state)),
                                TextEntry::make('recipient')->hiddenLabel(),
                                TextEntry::make('status')
                                    ->hiddenLabel()
                                    ->badge()
                                    ->formatStateUsing(fn (string $state) => __('admin_notification_log.statuses.'.$state))
                                    ->color(fn (string $state) => match ($state) {
                                        'sent' => 'success',
                                        'skipped' => 'warning',
                                        default => 'danger',
                                    })
                                    ->tooltip(fn ($record) => $record->error),
                            ]),
                    ]),

                Section::make(__('admin_service_order.sections.timeline'))
                    ->schema([
                        RepeatableEntry::make('events')
                            ->hiddenLabel()
                            ->columns(3)
                            ->schema([
                                TextEntry::make('created_at')->hiddenLabel()->dateTime('M d, Y H:i:s'),
                                TextEntry::make('type')->hiddenLabel()->badge()->color('gray'),
                                TextEntry::make('message')->hiddenLabel()->placeholder('—'),
                            ]),
                    ]),
            ])
            ->columns(1);
    }
}
