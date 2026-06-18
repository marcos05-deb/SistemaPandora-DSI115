<script setup>
defineProps({
    title: { type: String, default: 'PANDORA' },
});
</script>

<template>
    <div class="min-h-screen bg-[var(--nord6)] flex flex-col font-sans">
        <div class="flex-1 flex flex-col items-center justify-center px-4 py-12">
            <div class="text-center mb-8">
                <div class="w-12 h-12 rounded-xl bg-[var(--frost4)] flex items-center justify-center mx-auto mb-4">
                    <span class="text-[18px] font-bold text-white">P</span>
                </div>
                <h1 class="text-2xl font-bold text-[var(--nord0)] tracking-tight">PANDORA</h1>
                <p class="text-[13px] text-[var(--nord3)] mt-1">Sistema de Gestión Clínica</p>
            </div>
            <slot />
        </div>
        <slot name="footer" />
    </div>
</template>
