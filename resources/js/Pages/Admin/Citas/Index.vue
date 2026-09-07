<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';
import 'dayjs/locale/es';

dayjs.extend(utc);
dayjs.extend(timezone);
dayjs.locale('es');
dayjs.tz.setDefault("America/El_Salvador");

const props = defineProps({
    citasBase: {
        type: Array,
        default: () => []
    }
});

// Estado de fecha visual
const currentDate = ref(dayjs().tz("America/El_Salvador").startOf('month'));
const selectedDay = ref(null);
const citasDelDia = ref([]);
const isLoadingDetalle = ref(false);

// AbortController para peticiones concurrentes
let currentAbortController = null;

// Rango de la grilla
const gridStart = computed(() => currentDate.value.startOf('month').startOf('week'));
const gridEnd = computed(() => currentDate.value.endOf('month').endOf('week'));

// Días de la semana (L a D)
const weekDays = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

// Arreglo de días para la cuadrícula
const calendarDays = computed(() => {
    const days = [];
    let current = gridStart.value;
    while (current.isBefore(gridEnd.value) || current.isSame(gridEnd.value, 'day')) {
        days.push(current);
        current = current.add(1, 'day');
    }
    return days;
});

// Función para pedir las citas a Inertia en base al rango
const fetchCitasBase = () => {
    // Cerramos slide-over explícitamente para evitar fantasmas
    selectedDay.value = null;
    citasDelDia.value = [];

    router.get(
        '/admin/citas',
        { 
            start: gridStart.value.format('YYYY-MM-DD'), 
            end: gridEnd.value.format('YYYY-MM-DD')
        },
        { 
            preserveState: true, 
            preserveScroll: true, 
            only: ['citasBase'] 
        }
    );
};

// Navegación
const previousMonth = () => {
    currentDate.value = currentDate.value.subtract(1, 'month');
    fetchCitasBase();
};

const nextMonth = () => {
    currentDate.value = currentDate.value.add(1, 'month');
    fetchCitasBase();
};

const goToToday = () => {
    currentDate.value = dayjs().tz("America/El_Salvador").startOf('month');
    fetchCitasBase();
};

// Inicialización de citas (montaje inicial)
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('start')) {
        fetchCitasBase();
    }
});

// Obtener detalles de un día vía Axios
const fetchCitasPorDia = async (dateObj) => {
    selectedDay.value = dateObj;
    citasDelDia.value = [];
    isLoadingDetalle.value = true;
    
    if (currentAbortController) {
        currentAbortController.abort();
    }
    currentAbortController = new AbortController();

    try {
        const response = await axios.get(`/admin/api/citas/${dateObj.format('YYYY-MM-DD')}`, {
            signal: currentAbortController.signal
        });
        citasDelDia.value = response.data.data;
    } catch (error) {
        if (!axios.isCancel(error)) {
            console.error('Error fetching citas', error);
        }
    } finally {
        isLoadingDetalle.value = false;
    }
};

// Helpers para la grilla
const getCitasCount = (dateObj) => {
    const dateStr = dateObj.format('YYYY-MM-DD');
    // Las citasBase vienen con fecha_hora en ISO8601 (UTC)
    // Convertimos a timezone local para emparejar
    return props.citasBase.filter(c => dayjs(c.fecha_hora).tz("America/El_Salvador").format('YYYY-MM-DD') === dateStr).length;
};
</script>

<template>
    <Head title="Calendario de Citas" />
    <AdminLayout>
        <div class="px-6 py-8 h-full flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-[var(--chrome-text)]">Calendario de Citas</h1>
                    <p class="text-[13px] text-[var(--chrome-text-muted)] mt-1">Supervisión general de atención clínica</p>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="goToToday" class="px-3 py-1.5 text-[13px] font-medium bg-[var(--chrome-sidebar)] border border-[var(--chrome-border)] rounded-lg hover:bg-[var(--chrome-sidebar-hover)] transition-colors">
                        Hoy
                    </button>
                    <div class="flex items-center rounded-lg overflow-hidden border border-[var(--chrome-border)] bg-[var(--chrome-sidebar)]">
                        <button @click="previousMonth" class="px-3 py-1.5 hover:bg-[var(--chrome-sidebar-hover)] transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <span class="px-4 py-1.5 text-[14px] font-medium min-w-[140px] text-center border-x border-[var(--chrome-border)]">
                            {{ currentDate.format('MMMM YYYY').replace(/^\w/, (c) => c.toUpperCase()) }}
                        </span>
                        <button @click="nextMonth" class="px-3 py-1.5 hover:bg-[var(--chrome-sidebar-hover)] transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Calendario -->
            <div class="flex-1 bg-white rounded-xl shadow-sm border border-[var(--nord4)] overflow-hidden flex flex-col relative">
                <div class="grid grid-cols-7 border-b border-[var(--nord4)] bg-[var(--nord6)]">
                    <div v-for="day in weekDays" :key="day" class="py-2.5 text-center text-[12px] font-semibold text-[var(--nord3)] uppercase tracking-wider">
                        {{ day }}
                    </div>
                </div>
                
                <div class="flex-1 grid grid-cols-7 grid-rows-6 auto-rows-fr">
                    <div v-for="date in calendarDays" :key="date.format('YYYY-MM-DD')"
                         @click="fetchCitasPorDia(date)"
                         class="min-h-[100px] border-b border-r border-[var(--nord4)] p-2 transition-colors cursor-pointer hover:bg-[var(--nord6)]/50 relative group"
                         :class="{
                             'bg-[var(--nord6)]/20': !date.isSame(currentDate, 'month'),
                             'bg-[var(--aurora-purple)]/5': date.isSame(dayjs(), 'day'),
                             'border-r-0': date.day() === 0,
                             'border-b-0': calendarDays.indexOf(date) >= calendarDays.length - 7
                         }">
                        
                        <div class="flex items-center justify-between">
                            <span class="text-[14px] font-medium w-7 h-7 flex items-center justify-center rounded-full"
                                  :class="date.isSame(dayjs(), 'day') ? 'bg-[var(--aurora-purple)] text-white' : (date.isSame(currentDate, 'month') ? 'text-[var(--nord0)]' : 'text-[var(--nord4)]')">
                                {{ date.format('D') }}
                            </span>
                        </div>
                        
                        <!-- Indicadores de citas -->
                        <div class="mt-2" v-if="getCitasCount(date) > 0">
                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-[var(--aurora-purple)]/10 text-[var(--aurora-purple)] text-[11px] font-bold">
                                <span class="w-1.5 h-1.5 rounded-full bg-[var(--aurora-purple)]"></span>
                                {{ getCitasCount(date) }} citas
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Slide-over (Panel lateral para el día seleccionado) -->
                <transition enter-active-class="transform transition ease-in-out duration-300" enter-from-class="translate-x-full" enter-to-class="translate-x-0" leave-active-class="transform transition ease-in-out duration-300" leave-from-class="translate-x-0" leave-to-class="translate-x-full">
                    <div v-if="selectedDay" class="absolute inset-y-0 right-0 w-96 bg-white shadow-2xl border-l border-[var(--nord4)] flex flex-col z-20">
                        <div class="px-5 py-4 border-b border-[var(--nord4)] flex items-center justify-between bg-[var(--nord6)]">
                            <div>
                                <h3 class="text-[16px] font-bold text-[var(--nord0)]">{{ selectedDay.format('dddd D [de] MMMM') }}</h3>
                                <p class="text-[12px] text-[var(--nord3)]">Desglose de atenciones</p>
                            </div>
                            <button @click="selectedDay = null" class="p-2 text-[var(--nord3)] hover:text-[var(--aurora-red)] hover:bg-[var(--aurora-red)]/10 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto p-4">
                            <div v-if="isLoadingDetalle" class="flex flex-col items-center justify-center py-12 text-[var(--nord3)]">
                                <svg class="w-8 h-8 animate-spin mb-3 text-[var(--aurora-purple)]" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-[13px] font-medium">Cargando detalles...</p>
                            </div>
                            <div v-else-if="citasDelDia.length === 0" class="text-center py-12">
                                <div class="w-12 h-12 rounded-full bg-[var(--nord6)] flex items-center justify-center mx-auto mb-3 text-[var(--nord4)]">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                </div>
                                <p class="text-[14px] font-medium text-[var(--nord3)]">No hay citas registradas</p>
                            </div>
                            <div v-else class="space-y-3">
                                <div v-for="cita in citasDelDia" :key="cita.id" class="p-4 rounded-xl border border-[var(--nord4)] bg-white hover:border-[var(--aurora-purple)]/30 transition-colors shadow-sm">
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex flex-col">
                                            <span class="text-[12px] font-bold text-[var(--aurora-purple)]">
                                                {{ dayjs(cita.fecha_hora).tz("America/El_Salvador").format('hh:mm A') }}
                                            </span>
                                            <span class="text-[11px] font-medium px-2 py-0.5 rounded bg-[var(--nord6)] text-[var(--nord3)] mt-1 w-max">
                                                {{ cita.estado.toUpperCase() }}
                                            </span>
                                        </div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-white px-2 py-1 rounded"
                                              :class="cita.area?.nombre === 'Psicología' ? 'bg-[#5E81AC]' : (cita.area?.nombre === 'Medicina General' ? 'bg-[#A3BE8C]' : 'bg-[#B48EAD]')">
                                            {{ cita.area?.nombre || 'Área Desconocida' }}
                                        </span>
                                    </div>
                                    <div class="mt-3 pt-3 border-t border-[var(--nord4)]/50">
                                        <p class="text-[13px] font-semibold text-[var(--nord0)] mb-0.5">{{ cita.paciente?.nombre_completo || 'Paciente Anónimo' }}</p>
                                        <p class="text-[11px] text-[var(--nord3)] flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                            Atendido por: <span class="font-medium text-[var(--nord2)]">{{ cita.profesional?.nombre || 'Profesional Anónimo' }}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </AdminLayout>
</template>
