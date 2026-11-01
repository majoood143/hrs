@php($typeOptions = \App\Models\Jockey::typeOptions())
<div class="flex flex-wrap gap-1.5">
    @foreach($jockey->types ?? [] as $type)
        @continue(! isset($typeOptions[$type]))
        <span class="rounded-full bg-warm-100 {{ $size }} font-bold uppercase tracking-wide text-warm-700">{{ $typeOptions[$type] }}</span>
    @endforeach
    @if($jockey->years_experience !== null)
        <span class="rounded-full bg-warm-100 {{ $size }} font-bold uppercase tracking-wide text-warm-700">{{ trans_choice('farriers.years_experience_badge', $jockey->years_experience, ['count' => $jockey->years_experience]) }}</span>
    @endif
</div>
