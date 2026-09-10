@php($content = \App\Support\Localized::value($data, 'content'))

<section class="mx-auto max-w-3xl px-6 py-10">
    <div data-reveal class="max-w-none space-y-4 leading-relaxed text-warm-900/85 [&_a]:text-warm-700 [&_a]:underline [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-warm-900 [&_h3]:font-display [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-warm-900 [&_li]:ms-5 [&_ul]:list-disc [&_ol]:list-decimal">
        {!! $content ?? '' !!}
    </div>
</section>
