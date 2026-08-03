@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-xs font-semibold text-slate-600 mb-1']) }}>
    {{ $value ?? $slot }}
</label>
