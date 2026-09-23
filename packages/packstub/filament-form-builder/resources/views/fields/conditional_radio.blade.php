@php
    $type = $field->type;
    $answer = is_array($value) ? ($value['answer'] ?? null) : $value;
    $details = is_array($value) && is_scalar($value['details'] ?? null) ? (string) $value['details'] : '';
    $triggers = $type::triggers($field);
    $detailsId = $inputId.'-details';
    $detailsRequired = $type::detailsRequired($field);
    $detailsPlaceholder = $type::detailsPlaceholder($field);
@endphp
{{-- The details box shows through CSS :has() alone (no script needed); the
     script only takes its "required" and value out of play while it is hidden.
     The server keeps the details only for an answer that reveals them. --}}
<div class="fb-cond" data-fb-conditional>
    <fieldset class="fb-fieldset" aria-describedby="{{ $field->hint ? $inputId.'-hint ' : '' }}{{ $inputId }}-error" @if ($error) aria-invalid="true" @endif>
        <legend class="fb-label">
            {{ $field->label }}
            @if ($field->required)
                <span class="fb-required" aria-hidden="true">*</span>
            @endif
        </legend>
        <div class="fb-choices">
            @foreach ($field->choices() as $choiceValue => $choiceLabel)
                <label class="fb-choice" for="{{ $inputId }}-{{ $loop->index }}">
                    <input type="radio" id="{{ $inputId }}-{{ $loop->index }}" name="{{ $field->key }}[answer]" value="{{ $choiceValue }}" @checked((string) $answer === (string) $choiceValue) @if ($field->required) required @endif @if (in_array((string) $choiceValue, $triggers, true)) data-fb-reveals aria-controls="{{ $detailsId }}-wrap" @endif>
                    <span>{{ $choiceLabel }}</span>
                </label>
            @endforeach
        </div>
        @include('packstub-form-builder::fields._hint')
    </fieldset>
    <div class="fb-cond__details" id="{{ $detailsId }}-wrap" data-fb-cond-details>
        <label class="fb-label" for="{{ $detailsId }}">
            {{ $type::detailsLabel($field) }}
            @if ($detailsRequired)
                <span class="fb-required" aria-hidden="true">*</span>
            @endif
        </label>
        @if ($type::detailsType($field) === 'textarea')
            <textarea class="fb-input fb-textarea" id="{{ $detailsId }}" name="{{ $field->key }}[details]" rows="3" maxlength="{{ $type::detailsMaxLength($field) }}" @if ($detailsPlaceholder) placeholder="{{ $detailsPlaceholder }}" @endif @if ($detailsRequired) data-fb-required @endif aria-describedby="{{ $inputId }}-error">{{ $details }}</textarea>
        @else
            <input type="text" class="fb-input" id="{{ $detailsId }}" name="{{ $field->key }}[details]" value="{{ $details }}" maxlength="{{ $type::detailsMaxLength($field) }}" @if ($detailsPlaceholder) placeholder="{{ $detailsPlaceholder }}" @endif @if ($detailsRequired) data-fb-required @endif aria-describedby="{{ $inputId }}-error">
        @endif
    </div>
</div>
