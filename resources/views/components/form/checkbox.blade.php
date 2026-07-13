@props(['name', 'label' => null, 'checked' => false, 'value' => 1])
<label class="flex items-center gap-2 text-sm text-gray-700">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}"
           @checked(old($name, $checked)) {{ $attributes->merge(['class' => 'rounded border-gray-300 text-brand-600 focus:ring-brand-500']) }}>
    <span>{{ $label ?? $slot }}</span>
</label>
