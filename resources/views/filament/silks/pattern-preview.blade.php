{{-- The pattern being edited, drawn on the silks as the designer will show it (the other parts plain). --}}
@php
    use App\Support\Silks\SilksCatalog;
    use App\Support\Silks\SilksDesign;
    use App\Support\Silks\SilksTemplate;
    use App\Support\Silks\SilkSvgSanitizer;

    $area = in_array($get('area'), SilksTemplate::AREAS, true) ? $get('area') : 'body';
    $catalog = SilksCatalog::load()->withPattern($area, '__preview', (string) $get('en_name'), SilkSvgSanitizer::clean($get('svg')));

    $query = [];
    foreach (SilksTemplate::AREAS as $other) {
        $query += $other === $area
            ? [$other => '__preview', $other.'1' => 'royal-blue', $other.'2' => 'yellow']
            : [$other => SilksCatalog::PLAIN, $other.'1' => 'white', $other.'2' => 'royal-blue'];
    }
    $design = SilksDesign::fromQuery($query, $catalog);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div style="max-width: 18rem; margin: 0 auto; padding: 0.75rem; border-radius: 0.75rem; background: #fafaf9;">
        <x-silks.figure :paint="$design->paint()" uid="silks-admin-preview" :label="$design->describe()" style="display: block; width: 100%; height: auto;" />
    </div>
</x-dynamic-component>
