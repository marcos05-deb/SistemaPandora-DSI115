<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, Link, usePage, router } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import GestionarCitaModal from '@/Components/Expediente/GestionarCitaModal.vue';
import { formatLocalDate, formatLocalTime, todayLocalYmd } from '@/utils/dates';

defineOptions({ layout: ClinicalLayout });

function debounce(fn, delay = 300) {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
}

const props = defineProps({
    citas: { type: Object, required: true },
    citasPorDia: { type: Array, default: null },
    filtros: { type: Object, default: () => ({}) },
    especialistas: { type: Array, default: () => [] },
    navegacion: { type: Object, default: () => ({}) },
    agendaMeta: { type: Object, default: () => ({ limite: 500, truncada: false }) },
});

const page = usePage();

const isCoordinator = computed(() => {
    return page.props.auth?.user?.roles?.includes('area_coordinator');
});

const isGestionarCitaModalOpen = ref(false);
const gestionarCitaMode = ref('reprogramar');
const selectedCita = ref(null);

const form = useForm({
    estado: props.filtros.estado || 'todos',
    fecha: props.filtros.fecha || '',
    fecha_desde: props.filtros.fecha_desde || '',
    fecha_hasta: props.filtros.fecha_hasta || '',
    especialista_id: props.filtros.especialista_id || '',
    resultado_asistencia: props.filtros.resultado_asistencia || '',
    paciente: props.filtros.paciente || '',
    vista: props.filtros.vista || 'lista',
    referencia: props.filtros.referencia || '',
    page: 1,
});

const applyFilters = debounce(() => {
    form.get('/citas', {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
}, 300);

watch(() => form.estado, applyFilters);
watch(() => form.fecha, applyFilters);
watch(() => form.fecha_desde, applyFilters);
watch(() => form.fecha_hasta, applyFilters);
watch(() => form.especialista_id, applyFilters);
watch(() => form.resultado_asistencia, applyFilters);
watch(() => form.paciente, applyFilters);

function clearFilters() {
    form.estado = 'todos';
    form.fecha = '';
    form.fecha_desde = '';
    form.fecha_hasta = '';
    form.especialista_id = '';
    form.resultado_asistencia = '';
    form.paciente = '';
    form.vista = 'lista';
    form.referencia = '';
    applyFilters();
}

function setVista(vista) {
    form.vista = vista;
    if (!form.referencia) {
        form.referencia = props.navegacion.hoy || todayLocalYmd();
    }
    applyFilters();
}

function navegar(referencia) {
    form.referencia = referencia;
    applyFilters();
}

function verRangoEnLista() {
    if (!props.agendaMeta?.rangoLista) return;
    form.vista = 'lista';
    form.fecha = '';
    form.fecha_desde = props.agendaMeta.rangoLista.fecha_desde;
    form.fecha_hasta = props.agendaMeta.rangoLista.fecha_hasta;
    form.page = 1;
    applyFilters();
}

function cambiarPaginaAgenda(pageNum) {
    form.page = pageNum;
    applyFilters();
}

function formatDay(dateStr) {
    return formatLocalDate(dateStr, { day: '2-digit', month: 'short', year: 'numeric' }, 'es-ES', '-');
}

function formatTime(dateStr) {
    return formatLocalTime(dateStr);
}

function pacienteLabel(cita) {
    const nombre = cita.paciente?.nombre_completo;
    const carnet = cita.paciente?.carnet;
    if (nombre && carnet) return `${nombre} (${carnet})`;
    if (carnet) return carnet;
    if (nombre) return nombre;
    return 'Sin identificación';
}

function pacienteInicial(cita) {
    const base = cita.paciente?.nombre_completo || cita.paciente?.carnet || '?';
    return String(base).charAt(0).toUpperCase();
}

function puedeRegistrarAsistencia(cita) {
    if (!cita?.can?.registrar_asistencia || cita.estado !== 'programada') return false;
    if (!cita?.fecha_hora) return false;
    return new Date(cita.fecha_hora).getTime() <= Date.now();
}

function puedeGestionarFutura(cita) {
    if (cita.estado !== 'programada' || !cita?.fecha_hora) return false;
    return new Date(cita.fecha_hora).getTime() > Date.now();
}

function marcarAsistencia(cita, estado) {
    if (!puedeRegistrarAsistencia(cita) || !cita.expediente_id) return;
    router.patch(`/expedientes/${cita.expediente_id}/citas/${cita.id}/asistencia`, {
        estado,
    }, { preserveScroll: true });
}

function openGestionarCitaModal(cita, mode) {
    selectedCita.value = cita;
    gestionarCitaMode.value = mode;
    isGestionarCitaModalOpen.value = true;
}

const statusColors = {
    programada: 'bg-[var(--nord9)]/10 text-[var(--nord9)] border-[var(--nord9)]/30',
    asistida: 'bg-[var(--aurora-green)]/10 text-[var(--aurora-green)] border-[var(--aurora-green)]/30',
    ausente: 'bg-[var(--aurora-orange)]/10 text-[var(--aurora-orange)] border-[var(--aurora-orange)]/30',
    reprogramada: 'bg-[var(--aurora-purple)]/10 text-[var(--aurora-purple)] border-[var(--aurora-purple)]/30',
    cancelada: 'bg-[var(--aurora-red)]/10 text-[var(--aurora-red)] border-[var(--aurora-red)]/30',
};

const statusLabels = {
    programada: 'Programada',
    asistida: 'Asistida',
    ausente: 'Ausente',
    reprogramada: 'Reprogramada',
    cancelada: 'Cancelada',
};
</script>

<template>
    <Head title="Citas Asignadas" />

    <div class="max-w-7xl mx-auto mt-2">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[var(--nord0)] tracking-tight">Citas Clínicas</h1>
                <p class="text-[13px] text-[var(--nord3)] mt-1">Agenda diaria/semanal e historial de asistencia</p>
                <div class="flex items-center gap-2 mt-3">
                    <button type="button" @click="setVista('lista')" class="px-3 py-1.5 text-[12px] font-semibold rounded-lg border" :class="form.vista === 'lista' ? 'bg-[var(--nord8)] text-white border-[var(--nord8)]' : 'border-[var(--nord4)] text-[var(--nord3)]'">Lista</button>
                    <button type="button" @click="setVista('diaria')" class="px-3 py-1.5 text-[12px] font-semibold rounded-lg border" :class="form.vista === 'diaria' ? 'bg-[var(--nord8)] text-white border-[var(--nord8)]' : 'border-[var(--nord4)] text-[var(--nord3)]'">Diaria</button>
                    <button type="button" @click="setVista('semanal')" class="px-3 py-1.5 text-[12px] font-semibold rounded-lg border" :class="form.vista === 'semanal' ? 'bg-[var(--nord8)] text-white border-[var(--nord8)]' : 'border-[var(--nord4)] text-[var(--nord3)]'">Semanal</button>
                </div>
                <p
                    v-if="agendaMeta?.truncada"
                    class="mt-2 text-[12px] text-[var(--aurora-orange)] font-medium"
                >
                    Se muestran {{ citas?.data?.length || 0 }} de {{ agendaMeta.total }} citas del rango (máx. {{ agendaMeta.limite }} por página).
                    <button
                        v-if="agendaMeta.rangoLista"
                        type="button"
                        class="underline ml-1"
                        @click="verRangoEnLista"
                    >
                        Ver en lista
                    </button>
                </p>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-[var(--nord4)] p-4 mb-6">
            <div class="flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2 items-center">
                    <input v-model="form.paciente" type="text" placeholder="Buscar por carnet o código..."
                        class="w-full md:w-56 border-none bg-[var(--nord6)] text-[var(--nord0)] text-sm rounded-xl px-3 py-2" />
                    <select v-model="form.estado" class="w-full md:w-auto border-none bg-[var(--nord6)] text-[var(--nord0)] text-sm rounded-xl px-3 py-2 pr-8 appearance-none cursor-pointer">
                        <option value="todos">Estado: todos</option>
                        <option value="programada">Programada</option>
                        <option value="asistida">Asistida</option>
                        <option value="ausente">Ausente</option>
                        <option value="reprogramada">Reprogramada</option>
                        <option value="cancelada">Cancelada</option>
                    </select>
                    <select v-model="form.resultado_asistencia" class="w-full md:w-auto border-none bg-[var(--nord6)] text-[var(--nord0)] text-sm rounded-xl px-3 py-2 pr-8 appearance-none cursor-pointer">
                        <option value="">Asistencia: todas</option>
                        <option value="asistida">Asistió</option>
                        <option value="ausente">Ausente</option>
                    </select>
                    <select v-if="isCoordinator" v-model="form.especialista_id" class="w-full md:w-auto border-none bg-[var(--nord6)] text-[var(--nord0)] text-sm rounded-xl px-3 py-2 pr-8 appearance-none cursor-pointer">
                        <option value="">Especialista: todos</option>
                        <option v-for="esp in especialistas" :key="esp.id" :value="esp.id">{{ esp.nombre }}</option>
                    </select>
                    <template v-if="form.vista === 'lista'">
                        <input v-model="form.fecha" type="date" class="border-none bg-[var(--nord6)] text-[var(--nord0)] text-sm rounded-xl px-3 py-2" />
                        <input v-model="form.fecha_desde" type="date" class="border-none bg-[var(--nord6)] text-[var(--nord0)] text-sm rounded-xl px-3 py-2" title="Desde" />
                        <input v-model="form.fecha_hasta" type="date" class="border-none bg-[var(--nord6)] text-[var(--nord0)] text-sm rounded-xl px-3 py-2" title="Hasta" />
                    </template>
                    <button v-if="form.fecha || form.fecha_desde || form.fecha_hasta || (form.estado && form.estado !== 'todos') || form.especialista_id || form.paciente || form.resultado_asistencia" @click="clearFilters"
                        type="button" class="text-[12px] font-semibold text-[var(--aurora-red)] px-2 py-1">
                        Limpiar
                    </button>
                </div>
                <div v-if="form.vista === 'diaria' || form.vista === 'semanal'" class="flex items-center gap-2">
                    <button type="button" @click="navegar(navegacion.anterior)" class="px-3 py-1.5 text-[12px] rounded-lg border border-[var(--nord4)]">Anterior</button>
                    <button type="button" @click="navegar(navegacion.hoy)" class="px-3 py-1.5 text-[12px] rounded-lg border border-[var(--nord4)] font-semibold">Hoy</button>
                    <button type="button" @click="navegar(navegacion.siguiente)" class="px-3 py-1.5 text-[12px] rounded-lg border border-[var(--nord4)]">Siguiente</button>
                </div>
            </div>
        </div>

        <!-- Vista semanal agrupada -->
        <div v-if="form.vista === 'semanal'" class="space-y-4">
            <div v-if="!citasPorDia || citasPorDia.length === 0" class="bg-white rounded-2xl shadow-sm border border-[var(--nord4)] p-10 text-center">
                <p class="text-[14px] font-medium text-[var(--nord0)]">No hay citas en esta semana</p>
                <p class="text-[12px] text-[var(--nord3)] mt-1">Usa la navegación para cambiar de semana</p>
            </div>
            <div v-for="dia in citasPorDia" :key="dia.fecha" class="bg-white rounded-2xl shadow-sm border border-[var(--nord4)] overflow-hidden">
                <div class="px-5 py-3 bg-[var(--surface-header)] border-b border-[var(--nord4)]">
                    <h3 class="text-[14px] font-bold text-[var(--nord0)]">{{ formatDay(dia.fecha) }}</h3>
                </div>
                <div class="divide-y divide-[var(--nord4)]">
                    <div v-for="cita in dia.citas" :key="cita.id" class="px-5 py-3 flex flex-wrap items-center justify-between gap-3 hover:bg-[var(--nord6)]/30">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="text-[13px] font-semibold text-[var(--nord0)] tabular-nums">{{ formatTime(cita.fecha_hora) }}</span>
                            <div class="min-w-0">
                                <p class="text-[13px] font-medium text-[var(--nord0)] truncate">{{ pacienteLabel(cita) }}</p>
                                <p class="text-[11px] text-[var(--nord3)] truncate">{{ cita.profesional?.nombre || '-' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                :class="statusColors[cita.estado] || statusColors.programada">
                                {{ statusLabels[cita.estado] || cita.estado }}
                            </span>
                            <template v-if="cita.estado === 'programada'">
                                <button
                                    v-if="cita.can?.registrar_asistencia"
                                    type="button"
                                    :disabled="!puedeRegistrarAsistencia(cita)"
                                    @click="marcarAsistencia(cita, 'asistida')"
                                    class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-[var(--aurora-green)] text-white disabled:opacity-40"
                                >Asistió</button>
                                <button
                                    v-if="cita.can?.registrar_asistencia"
                                    type="button"
                                    :disabled="!puedeRegistrarAsistencia(cita)"
                                    @click="marcarAsistencia(cita, 'ausente')"
                                    class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-[var(--aurora-red)] text-white disabled:opacity-40"
                                >Ausente</button>
                                <button
                                    v-if="cita.can?.reprogramar && puedeGestionarFutura(cita)"
                                    type="button"
                                    @click="openGestionarCitaModal(cita, 'reprogramar')"
                                    class="px-2.5 py-1 text-[11px] font-semibold rounded-lg border border-[var(--nord8)] text-[var(--nord8)]"
                                >Reprogramar</button>
                                <button
                                    v-if="cita.can?.cancelar && puedeGestionarFutura(cita)"
                                    type="button"
                                    @click="openGestionarCitaModal(cita, 'cancelar')"
                                    class="px-2.5 py-1 text-[11px] font-semibold rounded-lg border border-[var(--aurora-red)] text-[var(--aurora-red)]"
                                >Cancelar</button>
                            </template>
                            <Link v-if="cita.paciente?.carnet" :href="`/pacientes/${cita.paciente?.carnet}`"
                                class="inline-flex items-center px-3 py-1.5 rounded-lg text-[12px] font-semibold text-white"
                                style="background: linear-gradient(135deg, var(--frost4), var(--nord9));">
                                Ver Exp.
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="citas.links && citas.links.length > 3" class="flex justify-end gap-1">
                <template v-for="(link, k) in citas.links" :key="k">
                    <button
                        v-if="link.url"
                        type="button"
                        class="px-3 py-1 text-[13px] border rounded-lg"
                        :class="link.active ? 'bg-[var(--frost4)] text-white border-[var(--frost4)]' : 'bg-white border-[var(--nord4)]'"
                        @click="cambiarPaginaAgenda(Number(new URL(link.url, window.location.origin).searchParams.get('page') || 1))"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>

        <div v-else class="bg-white rounded-2xl shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-[var(--nord4)] text-[11px] uppercase tracking-wider text-[var(--nord3)] bg-[var(--nord6)]/50">
                            <th class="px-6 py-4 font-semibold">Fecha y Hora</th>
                            <th class="px-6 py-4 font-semibold">Paciente</th>
                            <th class="px-6 py-4 font-semibold">Especialista</th>
                            <th class="px-6 py-4 font-semibold">Estado</th>
                            <th class="px-6 py-4 font-semibold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--nord4)]">
                        <tr v-for="cita in citas.data" :key="cita.id" class="hover:bg-[var(--nord6)]/30 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center bg-[var(--frost4)]/10 text-[var(--frost4)]">
                                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-[14px] font-semibold text-[var(--nord0)]">
                                            {{ formatDay(cita.fecha_hora) }}
                                        </div>
                                        <div class="text-[12px] text-[var(--nord3)] font-medium">
                                            {{ formatTime(cita.fecha_hora) }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-6 h-6 shrink-0 rounded-full bg-[var(--nord4)] flex items-center justify-center text-[10px] font-bold text-[var(--nord0)]">
                                        {{ pacienteInicial(cita) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[14px] font-medium text-[var(--nord0)] truncate">
                                            {{ cita.paciente?.nombre_completo || 'Paciente' }}
                                        </p>
                                        <p class="text-[12px] font-mono text-[var(--nord3)]">
                                            {{ cita.paciente?.carnet || 'Sin carnet' }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-[13px] font-medium text-[var(--nord0)]">{{ cita.profesional?.nombre || '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                    :class="statusColors[cita.estado] || statusColors.programada">
                                    {{ statusLabels[cita.estado] || cita.estado }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-[13px] font-medium">
                                <div class="inline-flex flex-wrap justify-end gap-1.5 max-w-xs ml-auto">
                                    <template v-if="cita.estado === 'programada'">
                                        <button
                                            v-if="cita.can?.registrar_asistencia"
                                            type="button"
                                            :disabled="!puedeRegistrarAsistencia(cita)"
                                            @click="marcarAsistencia(cita, 'asistida')"
                                            class="px-2.5 py-1.5 text-[11px] font-semibold rounded-lg bg-[var(--aurora-green)] text-white disabled:opacity-40 disabled:cursor-not-allowed"
                                        >Asistió</button>
                                        <button
                                            v-if="cita.can?.registrar_asistencia"
                                            type="button"
                                            :disabled="!puedeRegistrarAsistencia(cita)"
                                            @click="marcarAsistencia(cita, 'ausente')"
                                            class="px-2.5 py-1.5 text-[11px] font-semibold rounded-lg bg-[var(--aurora-red)] text-white disabled:opacity-40 disabled:cursor-not-allowed"
                                        >Ausente</button>
                                        <button
                                            v-if="cita.can?.reprogramar && puedeGestionarFutura(cita)"
                                            type="button"
                                            @click="openGestionarCitaModal(cita, 'reprogramar')"
                                            class="px-2.5 py-1.5 text-[11px] font-semibold rounded-lg border border-[var(--nord8)] text-[var(--nord8)]"
                                        >Reprogramar</button>
                                        <button
                                            v-if="cita.can?.cancelar && puedeGestionarFutura(cita)"
                                            type="button"
                                            @click="openGestionarCitaModal(cita, 'cancelar')"
                                            class="px-2.5 py-1.5 text-[11px] font-semibold rounded-lg border border-[var(--aurora-red)] text-[var(--aurora-red)]"
                                        >Cancelar</button>
                                    </template>
                                    <Link v-if="cita.paciente?.carnet" :href="`/pacientes/${cita.paciente?.carnet}`"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-white font-semibold shadow-sm"
                                        style="background: linear-gradient(135deg, var(--frost4), var(--nord9));">
                                        Ver Exp.
                                    </Link>
                                    <span v-else class="text-[var(--nord3)] italic text-[12px]">Sin acceso</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="citas.data.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-[var(--nord3)]">
                                <svg class="mx-auto h-12 w-12 text-[var(--nord4)] mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <p class="text-[14px] font-medium text-[var(--nord0)]">No hay citas encontradas</p>
                                <p class="text-[12px] mt-1">Intente ajustar los filtros de búsqueda</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="citas.links && citas.links.length > 3" class="px-6 py-4 border-t border-[var(--nord4)] flex items-center justify-between bg-[var(--nord6)]/30">
                <span class="text-[12px] text-[var(--nord3)]">
                    Mostrando del <span class="font-medium text-[var(--nord0)]">{{ citas.meta?.from || 0 }}</span> al <span class="font-medium text-[var(--nord0)]">{{ citas.meta?.to || 0 }}</span> de <span class="font-medium text-[var(--nord0)]">{{ citas.meta?.total || 0 }}</span> resultados
                </span>
                <div class="flex flex-wrap gap-1">
                    <template v-for="(link, k) in citas.links" :key="k">
                        <div v-if="link.url === null"
                            class="px-3 py-1 text-[13px] text-[var(--nord4)] border border-[var(--nord4)] rounded-lg cursor-not-allowed" v-html="link.label" />
                        <Link v-else
                            class="px-3 py-1 text-[13px] border rounded-lg transition-colors hover:-translate-y-0.5"
                            :class="link.active
                                ? 'bg-[var(--frost4)] border-[var(--frost4)] text-white font-bold shadow-sm'
                                : 'bg-white border-[var(--nord4)] text-[var(--nord0)] hover:border-[var(--frost4)] hover:text-[var(--frost4)]'"
                            :href="link.url"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <GestionarCitaModal
            v-if="selectedCita"
            :show="isGestionarCitaModalOpen"
            :mode="gestionarCitaMode"
            :cita="selectedCita"
            :expediente="{ id: selectedCita.expediente_id }"
            @close="isGestionarCitaModalOpen = false"
        />
    </div>
</template>
