@props(['column', 'title', 'currentSort', 'currentDirection', 'align' => 'left'])

@php
    $isSorted = $currentSort === $column;
    $nextDirection = $isSorted && $currentDirection === 'asc' ? 'desc' : 'asc';
    
    $alignmentClass = match($align) {
        'center' => 'justify-center text-center',
        'right' => 'justify-end text-right',
        default => 'justify-start text-left',
    };
@endphp

<th {{ $attributes->merge(['class' => 'py-4 px-6 font-semibold text-gray-500 uppercase tracking-wider text-xs']) }}>
    <a href="{{ route('tickets.index', array_merge(request()->query(), ['sort' => $column, 'direction' => $nextDirection])) }}" 
       class="group inline-flex items-center gap-1.5 hover:text-gray-800 transition-colors duration-150 w-full {{ $alignmentClass }}">
        <span>{{ $title }}</span>
        
        <span class="inline-flex items-center text-gray-450 group-hover:text-gray-600 transition-colors duration-150">
            @if ($isSorted)
                @if ($currentDirection === 'asc')
                    <svg class="w-3.5 h-3.5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"></path>
                    </svg>
                @else
                    <svg class="w-3.5 h-3.5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
                    </svg>
                @endif
            @else
                <svg class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-opacity duration-150 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                </svg>
            @endif
        </span>
    </a>
</th>
