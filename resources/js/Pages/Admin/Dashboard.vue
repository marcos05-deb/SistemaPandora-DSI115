<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });
</script>

<template>
    <Head title="Panel de Administración" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-[var(--nord0)] tracking-tight">Panel de Administración</h1>
            <p class="text-sm text-[var(--nord3)] mt-1">Métricas y monitoreo del sistema PANDORA.</p>
        </div>

        <div class="flex flex-col items-center justify-center py-20 text-center">
            <div class="w-20 h-20 rounded-full bg-[var(--aurora-purple)]/10 flex items-center justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-[var(--aurora-purple)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <h2 class="text-lg font-medium text-[var(--nord0)] mb-1">Métricas en Desarrollo</h2>
            <p class="text-sm text-[var(--nord3)] max-w-md">
                Los indicadores y estadísticas del sistema estarán disponibles próximamente.
                Por el momento, puedes consultar la gestión de personal y la auditoría de pacientes.
            </p>
        </div>
    </div>
</template>
