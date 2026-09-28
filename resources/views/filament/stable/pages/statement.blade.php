<x-filament-panels::page>
    <div class="flex flex-col gap-y-6">
        {{ $this->form }}

        @include('filament.stable.partials.statement-body', ['statement' => $this->statement()])
    </div>
</x-filament-panels::page>
