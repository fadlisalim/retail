@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])
<div>
    @if ($label)
        <label for="{{ $name }}" class="input-label">{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}"
           value="{{ old($name, $value) }}" @if($required) required @endif
           {{ $attributes->merge(['class' => 'form-input']) }}>
    @if ($hint)<p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
