<script setup>
defineProps({
    title: { type: String, default: 'PANDORA' },
});
</script>

<template>
    <div class="min-h-screen bg-pandora-surface flex flex-col">
        <div class="flex-1 flex flex-col items-center justify-center px-4 py-12">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-pandora-navy tracking-wide">PANDORA</h1>
                <p class="text-gray-500 mt-1">Sistema de Gestión Clínica</p>
            </div>
            <slot />
        </div>
        <slot name="footer" />
    </div>
</template>
