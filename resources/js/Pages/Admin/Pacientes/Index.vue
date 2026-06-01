<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pacientes: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({})
    }
});

const search = ref(props.filters?.search || '');
let searchTimeout = null;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/admin/pacientes', { search: value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        });
    }, 300);
});
</script>

<template>
    <Head title="Auditoría de Pacientes" />

    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-[var(--nord0)] tracking-tight">Auditoría de Pacientes</h1>
                <p class="text-sm text-[var(--nord3)] mt-1">Registro general de pacientes en el sistema.</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="p-6 border-b border-[var(--nord4)] bg-[var(--nord6)] flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-[var(--nord0)] flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--nord10)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Listado Global Anonimizado
                    </h2>
                    <p class="text-[13px] text-[var(--nord3)] mt-1">
                        Por políticas de privacidad, este panel solo muestra el código de sistema (UUID) de los pacientes y su fecha de ingreso.
                    </p>
                </div>
                
                <div class="relative w-full md:w-64">
                    <input 
                        v-model="search"
                        type="text" 
                        placeholder="Buscar por UUID..." 
                        class="w-full border border-[var(--nord4)] rounded-[7px] pl-9 pr-3 py-2 text-[13px] focus:ring-[rgba(136,192,208,0.2)] focus:border-[var(--nord10)] transition-colors outline-none"
                    />
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--nord3)] absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[600px]">
                    <thead>
                        <tr class="bg-[var(--nord6)] border-b border-[var(--nord4)] text-xs uppercase tracking-wider text-[var(--nord3)]">
                            <th class="p-4 font-semibold w-24">#</th>
                            <th class="p-4 font-semibold">Código UUID (Sistema)</th>
                            <th class="p-4 font-semibold">Fecha y Hora de Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(paciente, index) in pacientes.data" :key="paciente.codigo" class="border-b border-[var(--nord4)] hover:bg-[var(--nord6)] transition-colors text-sm">
                            <td class="p-4 font-mono text-[var(--nord3)]">{{ (pacientes.current_page - 1) * pacientes.per_page + index + 1 }}</td>
                            <td class="p-4 font-mono text-[var(--nord0)]">{{ paciente.codigo }}</td>
                            <td class="p-4 text-[var(--nord3)]">{{ paciente.created_at }}</td>
                        </tr>
                        <tr v-if="pacientes.data.length === 0">
                            <td colspan="3" class="p-8 text-center text-[var(--nord3)]">
                                No se han registrado pacientes en el sistema todavía.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pacientes.links && pacientes.links.length > 3" class="px-6 py-4 border-t border-[var(--nord4)] bg-[var(--nord6)] flex items-center justify-between">
                <div class="text-sm text-[var(--nord3)]">
                    Mostrando del <span class="font-medium text-[var(--nord0)]">{{ pacientes.from || 0 }}</span> al 
                    <span class="font-medium text-[var(--nord0)]">{{ pacientes.to || 0 }}</span> de 
                    <span class="font-medium text-[var(--nord0)]">{{ pacientes.total }}</span> resultados
                </div>
                <div class="flex items-center gap-1">
                    <template v-for="(link, i) in pacientes.links" :key="i">
                        <Link 
                            v-if="link.url"
                            :href="link.url" 
                            class="px-3 py-1 text-sm rounded transition-colors"
                            :class="link.active ? 'bg-[var(--nord10)] text-white font-medium' : 'text-[var(--nord0)] hover:bg-[var(--nord4)]'"
                            v-html="link.label"
                        />
                        <span 
                            v-else 
                            class="px-3 py-1 text-sm text-[var(--nord4)] cursor-not-allowed"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
