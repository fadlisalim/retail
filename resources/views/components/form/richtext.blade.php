@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'required' => false])
@php $inputId = $name.'_rt'; @endphp
<div>
    @if ($label)
        <label for="{{ $inputId }}" class="input-label">{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    {{-- Trix writes HTML into this hidden input; the editor is the visible surface. --}}
    <input type="hidden" id="{{ $inputId }}" name="{{ $name }}" value="{{ old($name, $value) }}">
    <trix-editor input="{{ $inputId }}" class="trix-content"></trix-editor>
    @if ($hint)<p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
