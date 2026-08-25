<svg {{ $attributes->merge(['viewBox' => '0 0 100 100', 'fill' => 'none', 'xmlns' => 'http://www.w3.org/2000/svg']) }}>
    <!-- Bottom layer / Shadow (ERP Foundation) -->
    <path d="M10 65 L50 85 L90 65 L50 45 Z" class="fill-indigo-900 dark:fill-indigo-950" opacity="0.9" />
    
    <!-- Middle layer (Data processing/CRM) -->
    <path d="M10 50 L50 70 L90 50 L50 30 Z" class="fill-indigo-700 dark:fill-indigo-800" opacity="0.95" />
    
    <!-- Top layer (User interface / Synergy) -->
    <path d="M10 35 L50 55 L90 35 L50 15 Z" class="fill-indigo-500 dark:fill-indigo-600" opacity="1" />
    
    <!-- Accents / Highlights for 3D effect -->
    <path d="M10 35 L50 55 L90 35" class="stroke-sky-300 dark:stroke-sky-400" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
    <path d="M50 55 L50 85" class="stroke-indigo-400 dark:stroke-indigo-500" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
    
    <!-- Nexus Node -->
    <circle cx="50" cy="15" r="4" class="fill-sky-300 dark:fill-sky-400" />
</svg>
