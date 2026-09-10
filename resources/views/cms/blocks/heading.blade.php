@php($level = $data['level'] ?? 'h2')
@php($align = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$data['alignment'] ?? 'center'])
@php($text = \App\Support\Localized::value($data, 'text'))

<section class="mx-auto max-w-4xl px-6 py-10">
    <{{ $level }} data-reveal class="{{ $align }} font-display font-semibold text-warm-900 {{ $level === 'h1' ? 'text-4xl' : ($level === 'h2' ? 'text-3xl' : ($level === 'h3' ? 'text-2xl' : 'text-xl')) }}">
        {{ $text }}
    </{{ $level }}>
</section>
