<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pacientes: { type: Object, required: true },
    correcciones: { type: Object, required: true },
    avisosPendientes: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search || '');
const tipoEvento = ref(props.filters.tipo_evento || '');
const nivelEvento = ref(props.filters.nivel_evento || '');
const pacienteFiltro = ref(props.filters.paciente || '');
const fechaDesde = ref(props.filters.fecha_desde || '');
const fechaHasta = ref(props.filters.fecha_hasta || '');

function applyFilters() {
    router.get('/admin/pacientes', {
        search: search.value || undefined,
        tipo_evento: tipoEvento.value || undefined,
        nivel_evento: nivelEvento.value || undefined,
        paciente: pacienteFiltro.value || undefined,
        fecha_desde: fechaDesde.value || undefined,
        fecha_hasta: fechaHasta.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

watch(search, () => applyFilters());

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('es-ES', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function marcarRevisado(id) {
    router.post(`/admin/pacientes/correcciones/${id}/revisar`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Auditoría de Pacientes" />

    <div class="space-y-[14px]">
        <div class="flex justify-between items-center bg-white py-[14px] px-[18px] shadow-sm border border-[var(--nord4)] rounded-[10px]">
            <div>
                <h2 class="text-[16px] font-medium text-[var(--nord0)] tracking-tight">Auditoría de Pacientes</h2>
                <p class="text-[12px] text-[var(--nord3)] mt-0.5">Consulta de registros y correcciones de datos generales. Sin edición clínica.</p>
            </div>
            <div class="text-[11px] text-[var(--nord3)] bg-[var(--surface-subtle)] px-3 py-1.5 rounded-full font-medium border border-[var(--nord4)]">
                {{ correcciones.total || 0 }} correcciones
            </div>
        </div>

        <div v-if="avisosPendientes.length" class="space-y-2">
            <div
                v-for="aviso in avisosPendientes"
                :key="aviso.id"
                class="rounded-[10px] border border-[var(--aurora-orange)]/50 bg-[var(--aurora-orange)]/10 px-4 py-3 flex flex-wrap items-center justify-between gap-3"
            >
                <div>
                    <p class="text-[13px] font-semibold text-[var(--nord0)]">Corrección sensible: se modificó el carnet de un paciente.</p>
                    <p class="text-[12px] text-[var(--nord3)] mt-0.5">
                        Paciente {{ aviso.paciente_id.substring(0, 8) }}… · {{ formatDate(aviso.created_at) }} · {{ aviso.responsable }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="`/admin/pacientes/correcciones/${aviso.id}`"
                        class="px-3 py-1.5 text-[12px] font-medium rounded-lg border border-[var(--nord4)] bg-white text-[var(--nord0)]"
                    >
                        Ver detalle
                    </Link>
                    <button
                        type="button"
                        class="px-3 py-1.5 text-[12px] font-medium rounded-lg bg-[var(--nord8)] text-white"
                        @click="marcarRevisado(aviso.id)"
                    >
                        Marcar revisado
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-[10px] shadow-sm border border-[var(--nord4)] space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <input v-model="pacienteFiltro" type="text" placeholder="Buscar por UUID de paciente" class="border border-[var(--nord4)] rounded-[8px] px-3 py-2 text-[13px]" @change="applyFilters" />
                <select v-model="tipoEvento" class="border border-[var(--nord4)] rounded-[8px] px-3 py-2 text-[13px]" @change="applyFilters">
                    <option value="">Tipo de evento</option>
                    <option value="actualizacion_datos_paciente">Actualización de datos</option>
                    <option value="correccion_carnet_paciente">Corrección de carnet</option>
                </select>
                <select v-model="nivelEvento" class="border border-[var(--nord4)] rounded-[8px] px-3 py-2 text-[13px]" @change="applyFilters">
                    <option value="">Nivel</option>
                    <option value="normal">Normal</option>
                    <option value="sensible">Sensible</option>
                </select>
                <input v-model="fechaDesde" type="date" class="border border-[var(--nord4)] rounded-[8px] px-3 py-2 text-[13px]" @change="applyFilters" />
                <input v-model="fechaHasta" type="date" class="border border-[var(--nord4)] rounded-[8px] px-3 py-2 text-[13px]" @change="applyFilters" />
                <input v-model="search" type="text" placeholder="Buscar UUID pacientes…" class="border border-[var(--nord4)] rounded-[8px] px-3 py-2 text-[13px]" />
            </div>
        </div>

        <div class="bg-white shadow-sm border border-[var(--nord4)] rounded-[10px] overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--nord4)] bg-[var(--surface-header)]">
                <h3 class="text-[13px] font-medium text-[var(--nord0)]">Correcciones de datos generales</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-[var(--surface-header)] text-[var(--nord3)] font-medium text-[11px] uppercase tracking-[0.05em]">
                            <th class="py-[12px] px-[16px]">Paciente</th>
                            <th class="py-[12px] px-[16px]">Identificador</th>
                            <th class="py-[12px] px-[16px]">Tipo</th>
                            <th class="py-[12px] px-[16px]">Campos</th>
                            <th class="py-[12px] px-[16px]">Responsable</th>
                            <th class="py-[12px] px-[16px]">Fecha</th>
                            <th class="py-[12px] px-[16px]">Nivel</th>
                            <th class="py-[12px] px-[16px]">Motivo</th>
                            <th class="py-[12px] px-[16px]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--nord5)]">
                        <tr v-for="c in correcciones.data" :key="c.id" class="hover:bg-[var(--nord6)]">
                            <td class="py-[12px] px-[16px] font-mono text-[11px]">{{ c.paciente_id.substring(0, 8) }}…</td>
                            <td class="py-[12px] px-[16px] text-[12px]">{{ c.carnet_protegido }}</td>
                            <td class="py-[12px] px-[16px] text-[11px]">{{ c.tipo_evento }}</td>
                            <td class="py-[12px] px-[16px] text-[11px] text-[var(--nord3)]">{{ (c.campos_modificados || []).join(', ') }}</td>
                            <td class="py-[12px] px-[16px] text-[12px]">{{ c.responsable }}</td>
                            <td class="py-[12px] px-[16px] text-[12px]">{{ formatDate(c.created_at) }}</td>
                            <td class="py-[12px] px-[16px]">
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-semibold"
                                    :class="c.nivel_evento === 'sensible' ? 'bg-[var(--aurora-orange)]/15 text-[var(--aurora-orange)]' : 'bg-[var(--surface-subtle)] text-[var(--nord3)]'"
                                >
                                    {{ c.nivel_evento }}
                                </span>
                            </td>
                            <td class="py-[12px] px-[16px] text-[11px] text-[var(--nord3)] max-w-[180px] truncate">{{ c.motivo }}</td>
                            <td class="py-[12px] px-[16px]">
                                <Link :href="`/admin/pacientes/correcciones/${c.id}`" class="text-[12px] text-[var(--nord8)] font-medium">Detalle</Link>
                            </td>
                        </tr>
                        <tr v-if="!correcciones.data?.length">
                            <td colspan="9" class="py-10 text-center text-[13px] text-[var(--nord3)]">No hay correcciones registradas.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="correcciones.links" />
        </div>

        <div class="bg-white shadow-sm border border-[var(--nord4)] rounded-[10px] overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--nord4)] bg-[var(--surface-header)] flex justify-between items-center">
                <h3 class="text-[13px] font-medium text-[var(--nord0)]">Registro anonimizado de pacientes</h3>
                <span class="text-[11px] text-[var(--nord3)]">{{ pacientes.total || 0 }} registros</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-[var(--surface-header)] text-[var(--nord3)] font-medium text-[11px] uppercase tracking-[0.05em]">
                            <th class="py-[12px] px-[16px]">UUID</th>
                            <th class="py-[12px] px-[16px]">Fecha de Registro</th>
                            <th class="py-[12px] px-[16px]">Última Actualización</th>
                            <th class="py-[12px] px-[16px]">Última Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--nord5)]">
                        <tr v-for="(paciente, idx) in pacientes.data" :key="paciente.codigo" :class="{ 'bg-[var(--surface-subtle)]': idx % 2 === 1 }">
                            <td class="py-[12px] px-[16px] font-mono text-[12px]">{{ paciente.codigo }}</td>
                            <td class="py-[12px] px-[16px] text-[12px] text-[var(--nord3)]">{{ formatDate(paciente.created_at) }}</td>
                            <td class="py-[12px] px-[16px] text-[12px] text-[var(--nord3)]">{{ formatDate(paciente.updated_at) }}</td>
                            <td class="py-[12px] px-[16px] text-[12px]">
                                <span class="bg-[var(--surface-header)] px-2 py-1 rounded-md text-[11px] border border-[var(--nord4)]">{{ paciente.ultima_accion }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="pacientes.links" />
        </div>
    </div>
</template>
