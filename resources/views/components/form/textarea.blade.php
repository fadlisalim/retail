@props(['name', 'label' => null, 'value' => null, 'rows' => 4, 'required' => false, 'hint' => null])
<div>
    @if ($label)
        <label for="{{ $name }}" class="input-label">{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}" @if($required) required @endif
              {{ $attributes->merge(['class' => 'form-textarea']) }}>{{ old($name, $value) }}</textarea>
    @if ($hint)<p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
