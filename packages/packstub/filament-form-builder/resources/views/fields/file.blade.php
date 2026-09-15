@include('packstub-form-builder::fields._label')
<input
    class="fb-file"
    type="file"
    id="{{ $inputId }}"
    name="{{ $field->key }}"
    @if ($accept = $field->type->acceptAttribute($field)) accept="{{ $accept }}" @endif
    @if ($field->required) required aria-required="true" @endif
    aria-describedby="{{ $field->hint ? $inputId.'-hint ' : '' }}{{ $inputId }}-error"
    @if ($error) aria-invalid="true" @endif
>
@include('packstub-form-builder::fields._hint')
