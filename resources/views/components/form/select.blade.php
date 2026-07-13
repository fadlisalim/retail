@props(['name', 'label' => null, 'options' => [], 'selected' => null, 'required' => false, 'placeholder' => null])
<div>
    @if ($label)
        <label for="{{ $name }}" class="input-label">{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    <select name="{{ $name }}" id="{{ $name }}" @if($required) required @endif {{ $attributes->merge(['class' => 'form-select']) }}>
        @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $selected) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
