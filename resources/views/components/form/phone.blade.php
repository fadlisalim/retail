@props([
    'name' => 'whatsapp',
    'label' => 'Nomor WhatsApp',
    'value' => null,
    'required' => false,
    'hint' => 'Ketik nomor lokal, mis. 0812xxxxxxx',
])
@php
    // [Nama negara, kode telepon, bendera]. Indonesia default.
    $countries = [
        ['Indonesia', '62', '🇮🇩'],
        ['Malaysia', '60', '🇲🇾'],
        ['Singapura', '65', '🇸🇬'],
        ['Brunei', '673', '🇧🇳'],
        ['Filipina', '63', '🇵🇭'],
        ['Thailand', '66', '🇹🇭'],
        ['Vietnam', '84', '🇻🇳'],
        ['Australia', '61', '🇦🇺'],
        ['Arab Saudi', '966', '🇸🇦'],
        ['Uni Emirat Arab', '971', '🇦🇪'],
        ['Amerika Serikat', '1', '🇺🇸'],
        ['Inggris', '44', '🇬🇧'],
        ['Tiongkok', '86', '🇨🇳'],
        ['Jepang', '81', '🇯🇵'],
        ['India', '91', '🇮🇳'],
    ];
    $dials = array_map(fn ($c) => $c[1], $countries);
@endphp
<div x-data="phoneField(@js((string) old($name, $value)), @js($dials))">
    @if ($label)
        <label class="input-label">{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    <div class="flex">
        <select x-model="dial" @change="sync()" aria-label="Kode negara"
                class="form-select w-auto shrink-0 rounded-r-none border-r-0 pr-7 text-sm">
            @foreach ($countries as [$cname, $dc, $flag])
                <option value="{{ $dc }}">{{ $flag }} +{{ $dc }}</option>
            @endforeach
        </select>
        <input type="tel" inputmode="numeric" x-model="local" @input="sync()"
               placeholder="0812xxxxxxx" @if($required) required @endif
               class="form-input rounded-l-none">
    </div>
    {{-- The real submitted value: international, digits only (e.g. 628123…). --}}
    <input type="hidden" name="{{ $name }}" :value="full">
    <p class="mt-1 text-xs text-gray-400">
        {{ $hint }} — dikirim sebagai <span class="font-medium text-gray-500" x-text="full || '62…'"></span>
    </p>
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
