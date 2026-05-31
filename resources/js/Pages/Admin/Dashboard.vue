<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });
</script>

<template>
    <Head title="Panel de Administrador" />

    <div class="bg-white p-8 shadow-lg rounded-2xl h-[400px] flex flex-col items-center justify-center text-center">
        <svg class="w-20 h-20 text-gray-300 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <h2 class="text-2xl font-semibold text-[#1A1816] mb-2 tracking-tight">Gráficos próximamente</h2>
        <p class="text-gray-500 max-w-md">
            Esta sección está reservada para métricas y estadísticas del sistema. 
            Por ahora, utiliza el menú lateral para gestionar el personal.
        </p>
    </div>
</template>
