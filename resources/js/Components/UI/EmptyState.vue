<script setup>
defineProps({
    icon: { type: String, default: 'default' },
    title: { type: String, default: 'Sin datos' },
    description: { type: String, default: '' },
});

const iconPaths = {
    default: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
    users: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    search: 'M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0M21 21l-6-6',
    document: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    chart: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
};
</script>

<template>
    <div class="relative flex flex-col items-center justify-center py-16 text-center overflow-hidden">
        <!-- Fondo de puntos decorativos -->
        <div class="absolute inset-0 pointer-events-none opacity-40"
            style="background-image: radial-gradient(circle, var(--nord4) 1px, transparent 1px); background-size: 24px 24px;">
        </div>
        <!-- Gradiente de fondo suave -->
        <div class="absolute inset-0 pointer-events-none"
            style="background: radial-gradient(ellipse at 50% 60%, color-mix(in srgb, var(--nord6) 0%, transparent), var(--nord6) 70%);">
        </div>

        <div class="relative z-10 flex flex-col items-center">
            <!-- Anillo exterior + caja del ícono -->
            <div class="relative mb-5">
                <div class="absolute inset-0 rounded-2xl opacity-20 blur-xl scale-110"
                    style="background: var(--frost4);"></div>
                <div class="relative w-20 h-20 rounded-2xl flex items-center justify-center shadow-sm"
                    style="background: linear-gradient(135deg, var(--nord6) 0%, var(--nord5) 100%); border: 1.5px solid var(--nord4);">
                    <!-- Puntos decorativos en las esquinas -->
                    <span class="absolute top-2 right-2 w-1.5 h-1.5 rounded-full bg-[var(--nord4)]"></span>
                    <span class="absolute bottom-2 left-2 w-1.5 h-1.5 rounded-full bg-[var(--nord4)]"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" :d="iconPaths[icon] || iconPaths.default" />
                    </svg>
                </div>
            </div>

            <h3 class="text-[15px] font-bold text-[var(--nord0)] mb-1.5 tracking-tight">{{ title }}</h3>
            <p class="text-[13px] text-[var(--nord3)] max-w-xs mb-5 leading-relaxed">{{ description }}</p>
            <slot />
        </div>
    </div>
</template>
