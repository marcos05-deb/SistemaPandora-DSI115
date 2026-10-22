<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import { formatElSalvadorDateTime } from '@/utils/dates';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    alerts: { type: Object, default: () => ({ data: [], links: [] }) },
});

const page = usePage();
const isLoading = ref(false);
const requestError = ref('');
const selectedState = ref(new URLSearchParams(page.url.split('?')[1] ?? '').get('estado') ?? '');
const alertRows = computed(() => props.alerts?.data ?? []);
const paginationLinks = computed(() => props.alerts?.links ?? []);
const flashError = computed(() => page.props.flash?.variant === 'error' ? page.props.flash.message : '');
const errorMessage = computed(() => requestError.value || flashError.value);
const hasPermission = computed(() => Boolean(page.props.clinicalPermissions?.alertas));

watch(() => page.url, (url) => {
    selectedState.value = new URLSearchParams(url.split('?')[1] ?? '').get('estado') ?? '';
    requestError.value = '';
});

const signalLabels = {
    ausencia_cita: 'Ausencia a cita',
    crisis_psicologica: 'Crisis psicológica',
    tendencia_suicida: 'Tendencia suicida',
};

function signalLabel(signal) {
    return signalLabels[signal] ?? signal;
}

function statusInfo(status) {
    return {
        activa: { label: 'Activa', variant: 'active' },
        en_seguimiento: { label: 'En seguimiento', variant: 'specialist' },
        cerrada: { label: 'Cerrada', variant: 'inactive' },
        descartada: { label: 'Descartada', variant: 'blocked' },
    }[status] ?? { label: status, variant: 'inactive' };
}

function pageLabel(link, index) {
    if (index === 0) return 'Anterior';
    if (index === paginationLinks.value.length - 1) return 'Siguiente';
    return link.label;
}

function visit(url, data = {}) {
    isLoading.value = true;
    requestError.value = '';
    router.get(url, data, {
        preserveScroll: true,
        preserveState: true,
        onError: () => { requestError.value = 'No fue posible cargar las alertas. Inténtalo de nuevo.'; },
        onFinish: () => { isLoading.value = false; },
    });
}

function applyFilter() {
    visit('/alertas', selectedState.value ? { estado: selectedState.value } : {});
}

function refreshAlerts() {
    visit(page.url);
}
</script>

<template>
    <Head title="Alertas clínicas" />

    <div class="space-y-5">
        <Breadcrumbs :items="[
            { label: 'Panel clínico', href: '/dashboard' },
            { label: 'Alertas' },
        ]" />

        <section v-if="!hasPermission" aria-labelledby="denied-heading" class="rounded-xl border border-[var(--nord4)] bg-white p-6 shadow-sm">
            <h1 id="denied-heading" class="text-xl font-semibold tracking-tight text-[var(--nord0)]">Acceso no disponible</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-[var(--nord3)]">
                No tienes permiso para consultar las alertas clínicas.
            </p>
        </section>

        <template v-else>
            <header class="flex flex-col gap-4 rounded-xl border border-[var(--nord4)] bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight text-[var(--nord0)]">Alertas clínicas</h1>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-[var(--nord3)]">
                        Revisa señales por paciente, junto con el cálculo y el umbral de la versión aplicada.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-[var(--nord4)] px-4 py-2 text-sm font-semibold text-[var(--nord10)] transition-colors hover:border-[var(--nord10)] hover:bg-[var(--surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:cursor-wait disabled:opacity-60"
                    :disabled="isLoading"
                    @click="refreshAlerts"
                >
                    {{ isLoading ? 'Actualizando…' : 'Actualizar' }}
                </button>
            </header>

            <section v-if="errorMessage" role="alert" class="flex flex-col gap-3 rounded-lg border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-[var(--nord0)]">{{ errorMessage }}</p>
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm font-semibold text-[var(--nord10)] hover:bg-[var(--surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:opacity-60"
                    :disabled="isLoading"
                    @click="refreshAlerts"
                >
                    Reintentar
                </button>
            </section>

            <template v-else>
                <section aria-label="Filtrar alertas" class="flex flex-col gap-3 rounded-xl border border-[var(--nord4)] bg-white p-4 shadow-sm sm:flex-row sm:items-end sm:justify-between">
                    <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="applyFilter">
                        <div class="flex flex-col gap-1.5">
                            <label for="alert-state" class="text-sm font-medium text-[var(--nord1)]">Estado de la alerta</label>
                            <select
                                id="alert-state"
                                v-model="selectedState"
                                class="min-h-11 rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
                            >
                                <option value="">Todos los estados</option>
                                <option value="activa">Activa</option>
                                <option value="en_seguimiento">En seguimiento</option>
                                <option value="cerrada">Cerrada</option>
                                <option value="descartada">Descartada</option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[var(--nord10)] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[var(--nord9)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:cursor-wait disabled:opacity-60"
                            :disabled="isLoading"
                        >
                            Aplicar filtro
                        </button>
                    </form>
                    <p v-if="isLoading" role="status" aria-live="polite" class="text-sm text-[var(--nord3)]">Cargando alertas…</p>
                </section>

                <section aria-labelledby="alerts-heading" :aria-busy="isLoading" class="overflow-hidden rounded-xl border border-[var(--nord4)] bg-white shadow-sm">
                    <div class="border-b border-[var(--nord4)] bg-[var(--surface-header)] px-5 py-4">
                        <h2 id="alerts-heading" class="text-base font-semibold text-[var(--nord0)]">Alertas registradas</h2>
                    </div>

                    <div v-if="alertRows.length === 0" class="px-5 py-12 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[var(--surface-subtle)] text-[var(--nord10)]" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-[var(--nord0)]">No hay alertas para mostrar</h3>
                        <p class="mx-auto mt-1 max-w-md text-sm leading-relaxed text-[var(--nord3)]">
                            {{ selectedState ? 'No se encontraron alertas con el estado seleccionado.' : 'Las alertas disponibles aparecerán aquí.' }}
                        </p>
                    </div>

                    <ol v-else class="divide-y divide-[var(--nord5)]">
                        <li v-for="alert in alertRows" :key="alert.id" class="p-4 sm:p-5">
                            <article class="space-y-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-base font-semibold text-[var(--nord0)]">{{ signalLabel(alert.senal) }}</h3>
                                            <StatusBadge :label="statusInfo(alert.estado).label" :variant="statusInfo(alert.estado).variant" />
                                        </div>
                                        <p class="mt-1 text-sm text-[var(--nord3)]">Señal detectada</p>
                                    </div>
                                    <Link
                                        :href="`/pacientes/${encodeURIComponent(alert.paciente_carnet)}`"
                                        class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-[var(--nord4)] px-4 py-2 text-sm font-semibold text-[var(--nord10)] transition-colors hover:border-[var(--nord10)] hover:bg-[var(--surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
                                        :aria-label="`Abrir expediente del paciente ${alert.paciente_carnet}`"
                                    >
                                        Paciente {{ alert.paciente_carnet }}
                                    </Link>
                                    <Link
                                        :href="`/alertas/${encodeURIComponent(alert.id)}`"
                                        class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-[var(--nord10)] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[var(--nord9)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
                                        :aria-label="`Revisar alerta de ${signalLabel(alert.senal)} para el paciente ${alert.paciente_carnet}`"
                                    >
                                        Revisar alerta
                                    </Link>
                                </div>

                                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 rounded-lg bg-[var(--nord6)]/70 p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <dt class="text-[var(--nord3)]">Valor calculado</dt>
                                        <dd class="mt-1 font-semibold text-[var(--nord0)]">{{ alert.valor_calculado }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[var(--nord3)]">Umbral aplicado</dt>
                                        <dd class="mt-1 font-semibold text-[var(--nord0)]">{{ alert.umbral_aplicado }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[var(--nord3)]">Versión de rúbrica</dt>
                                        <dd class="mt-1 font-semibold text-[var(--nord0)]">{{ alert.rubrica_version_numero }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[var(--nord3)]">Generada</dt>
                                        <dd class="mt-1 font-semibold text-[var(--nord0)]">{{ formatElSalvadorDateTime(alert.fecha_generacion) }}</dd>
                                    </div>
                                </dl>
                            </article>
                        </li>
                    </ol>

                    <nav v-if="paginationLinks.length > 3" aria-label="Paginación de alertas" class="flex flex-wrap justify-center gap-2 border-t border-[var(--nord4)] p-4">
                        <template v-for="(link, index) in paginationLinks" :key="`${index}-${link.label}`">
                            <button
                                v-if="link.url"
                                type="button"
                                :aria-current="link.active ? 'page' : undefined"
                                :aria-label="`Ir a ${pageLabel(link, index)}`"
                                class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:cursor-wait disabled:opacity-60"
                                :class="link.active ? 'border-[var(--nord10)] bg-[var(--nord10)] text-white' : 'border-[var(--nord4)] text-[var(--nord1)] hover:bg-[var(--surface-subtle)]'"
                                :disabled="isLoading"
                                @click="visit(link.url)"
                            >
                                {{ pageLabel(link, index) }}
                            </button>
                            <span v-else class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-[var(--nord4)] px-3 text-sm text-[var(--nord3)]" aria-disabled="true">
                                {{ pageLabel(link, index) }}
                            </span>
                        </template>
                    </nav>
                </section>
            </template>
        </template>
    </div>
</template>
