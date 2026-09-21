@props(['type', 'entity' => null])

@if($entity)
    <a href="{{ route('racing.profile', [$type, $entity['id']]) }}"
       class="font-medium text-warm-700 underline decoration-warm-300 underline-offset-4 hover:text-warm-900">{{ $entity['name'] }}</a>
@endif
