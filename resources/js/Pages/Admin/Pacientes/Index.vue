<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pacientes: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const search = ref(props.filters.search || '');

watch(search, (newSearch) => {
    router.get('/admin/pacientes', { search: newSearch }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Auditoría de Pacientes" />

    <div class="space-y-[14px]">
        <div class="flex justify-between items-center bg-white py-[14px] px-[18px] shadow-sm border border-[var(--nord4)] rounded-[10px]">
            <div>
                <h2 class="text-[16px] font-medium text-[var(--nord0)] tracking-tight">Auditoría de Pacientes</h2>
                <p class="text-[12px] text-[var(--nord3)] mt-0.5">Listado anonimizado de todos los expedientes del sistema.</p>
            </div>
            <div class="text-[11px] text-[var(--nord3)] bg-[var(--surface-subtle)] px-3 py-1.5 rounded-full font-medium border border-[var(--nord4)]">
                {{ pacientes.total || 0 }} registros
            </div>
        </div>

        <div class="bg-white p-4 rounded-[10px] shadow-sm border border-[var(--nord4)]">
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input 
                    v-model="search" 
                    type="text" 
                    placeholder="Buscar por UUID..." 
                    class="w-full border border-[var(--nord4)] rounded-[8px] pl-9 pr-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] bg-white transition-colors placeholder-[var(--nord3)] outline-none" 
                />
            </div>
        </div>

        <div class="bg-white shadow-sm border border-[var(--nord4)] rounded-[10px] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-[var(--surface-header)] text-[var(--nord3)] font-medium text-[11px] uppercase tracking-[0.05em]">
                            <th class="py-[12px] px-[16px] font-semibold">UUID del Expediente</th>
                            <th class="py-[12px] px-[16px] font-semibold">Creado por</th>
                            <th class="py-[12px] px-[16px] font-semibold">Fecha de Registro</th>
                            <th class="py-[12px] px-[16px] font-semibold">Última Actualización</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--nord5)]">
                        <tr v-for="(paciente, idx) in pacientes.data" :key="paciente.id" 
                            class="hover:bg-[var(--nord6)] transition-colors duration-150"
                            :class="{ 'bg-[var(--surface-subtle)]': idx % 2 === 1 }">
                            <td class="py-[12px] px-[16px] font-mono text-[12px] text-[var(--nord0)]">{{ paciente.codigo }}</td>
                            <td class="py-[12px] px-[16px] text-[12px] text-[var(--nord3)]">{{ paciente.creator?.name || 'Desconocido' }}</td>
                            <td class="py-[12px] px-[16px] text-[12px] text-[var(--nord3)]">{{ formatDate(paciente.created_at) }}</td>
                            <td class="py-[12px] px-[16px] text-[12px] text-[var(--nord3)]">{{ formatDate(paciente.updated_at) }}</td>
                        </tr>
                        <tr v-if="!pacientes.data || pacientes.data.length === 0">
                            <td colspan="4" class="py-16 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-[var(--nord4)] mb-3" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <div class="text-[14px] font-medium text-[var(--nord0)]">No hay pacientes registrados</div>
                                    <div class="text-[12px] text-[var(--nord3)] mt-1">Aún no se ha creado ningún expediente en el sistema.</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="pacientes.links" />
        </div>
    </div>
</template>
