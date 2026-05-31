<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineOptions({ layout: AuthenticatedLayout });

defineProps({
    especialista: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Panel Principal</h1>
                <p class="text-gray-500">Bienvenido al Sistema de Gestión Clínica PANDORA.</p>
            </div>
            
            <Link 
                href="/logout" 
                method="post" 
                as="button" 
                class="px-4 py-2 bg-red-600 text-white font-semibold rounded hover:bg-red-700 transition-colors"
            >
                Cerrar Sesión
            </Link>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Estado del Sistema</h3>
                <p class="text-3xl font-bold text-pandora-navy">Activo</p>
                <p class="text-sm text-gray-400 mt-1">Sprint 1 — Cimientos y Seguridad</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Sesión Segura</h3>
                <p class="text-sm text-green-600 font-medium">🔒 Clave simétrica activa</p>
                <p class="text-sm text-gray-400 mt-1">Cifrado XSalsa20-Poly1305 habilitado</p>
            </div>
        </div>
    </div>
</template>
