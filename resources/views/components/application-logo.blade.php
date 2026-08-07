<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" width="36" height="36" style="max-width:100%;height:auto;aspect-ratio:1/1;" {{ $attributes }}>
    <defs>
        <linearGradient id="ai-logo-bg" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#4F46E5" />
            <stop offset="50%" stop-color="#7C3AED" />
            <stop offset="100%" stop-color="#2563EB" />
        </linearGradient>
        <linearGradient id="ai-spark-grad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#38BDF8" />
            <stop offset="100%" stop-color="#818CF8" />
        </linearGradient>
        <filter id="ai-glow" x="-20%" y="-20%" width="140%" height="140%">
            <feGaussianBlur stdDeviation="2.5" result="blur" />
            <feComposite in="SourceGraphic" in2="blur" operator="over" />
        </filter>
    </defs>
    <!-- Outer Rounded Squircle -->
    <rect width="100" height="100" rx="26" fill="url(#ai-logo-bg)" />
    
    <!-- Outer subtle accent border ring -->
    <rect x="2.5" y="2.5" width="95" height="95" rx="23.5" fill="none" stroke="#FFFFFF" stroke-opacity="0.2" stroke-width="2" />
    
    <!-- Support Headset Arc -->
    <path d="M26 48 C26 30, 74 30, 74 48" fill="none" stroke="#FFFFFF" stroke-width="6.5" stroke-linecap="round" opacity="0.95" />
    
    <!-- Left Ear Cushion -->
    <rect x="21" y="44" width="10" height="20" rx="4.5" fill="#FFFFFF" />
    <!-- Right Ear Cushion -->
    <rect x="69" y="44" width="10" height="20" rx="4.5" fill="#FFFFFF" />
    
    <!-- Microphone boom arm -->
    <path d="M26 56 C26 69, 40 74, 46 74" fill="none" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" />
    <circle cx="48.5" cy="74" r="3.5" fill="#38BDF8" />

    <!-- Center AI Sparkle Star Core -->
    <g transform="translate(50, 47)">
        <!-- 4-point AI Diamond Star -->
        <path d="M0 -12 C1.2 -4.5, 4.5 -1.2, 12 0 C4.5 1.2, 1.2 4.5, 0 12 C-1.2 4.5, -4.5 1.2, -12 0 C-4.5 -1.2, -1.2 -4.5, 0 -12 Z" fill="url(#ai-spark-grad)" filter="url(#ai-glow)" />
        <circle cx="0" cy="0" r="2.8" fill="#FFFFFF" />
    </g>
    
    <!-- Top Right Sparkle Accent -->
    <path d="M68 28 C68.8 25.5, 70.5 23.8, 73 23 C70.5 22.2, 68.8 20.5, 68 18 C67.2 20.5, 65.5 22.2, 63 23 C65.5 23.8, 67.2 25.5, 68 28 Z" fill="#F472B6" />
</svg>
