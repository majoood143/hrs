@if($entity)<a href="{{ route('racing.profile', [$type, $entity['id']]) }}">{{ $d($entity['name']) }}</a>@endif
