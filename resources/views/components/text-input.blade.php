@props(['disabled' => false, 'isError' => false])

<input
    @disabled($disabled)
    {{ $attributes->merge(['class' =>
        'rounded-lg shadow-sm text-sm transition duration-150 ' .
        ($isError
            ? 'border-red-400 text-red-900 focus:border-red-500 focus:ring-2 focus:ring-red-500/30'
            : 'border-slate-300 text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30')
    ]) }}
>
