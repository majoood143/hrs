@foreach(($blocks ?? []) as $block)
    @php($type = $block['type'] ?? null)
    @php($data = $block['data'] ?? [])
    @if($type && \Illuminate\Support\Facades\View::exists("cms.blocks.{$type}"))
        @include("cms.blocks.{$type}", ['data' => $data])
    @endif
@endforeach
