<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import FieldTooltip from '@/Components/UI/FieldTooltip.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';

defineOptions({
    layout: (props) => [props.layout === 'admin' ? AdminLayout : ClinicalLayout],
});

const props = defineProps({
    layout: { type: String, required: true },
    resultados: { type: Object, required: true },
});

const page = usePage();
const pageError = ref('');
const errorSummary = ref(null);
const form = useForm({
    desde: props.resultados.filtros.desde,
    hasta: props.resultados.filtros.hasta,
    area_id: props.resultados.filtros.area_id === null ? '' : String(props.resultados.filtros.area_id),
    indicadores: [...props.resultados.filtros.indicadores],
});

const selectedCatalog = computed(() => props.resultados.catalogo.filter((item) => props.resultados.filtros.indicadores.includes(item.id)));
const totals = computed(() => props.resultados.totales);
const rows = computed(() => props.resultados.filas);
const categories = computed(() => [...new Set(selectedCatalog.value.map((item) => item.categoria))]);
const formatter = new Intl.NumberFormat('es-SV', { maximumFractionDigits: 1 });

const categoryLabels = {
    atencion: 'Atención',
    asistencia: 'Citas y asistencia',
    expedientes: 'Expedientes',
    riesgo_preventivo: 'Riesgo preventivo',
};

function submitFilters() {
    pageError.value = '';
    form.get(route('indicadores.index'), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onError: () => nextTick(() => errorSummary.value?.focus()),
        onHttpException: (response) => {
            pageError.value = response.status === 403
                ? 'No tienes acceso a estos indicadores.'
                : 'No fue posible consultar los indicadores.';
        },
        onNetworkError: () => {
            pageError.value = 'No se pudo conectar. Revisa tu conexión e inténtalo de nuevo.';
        },
    });
}

function resetFilters() {
    router.get(route('indicadores.index'), {}, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    });
}

function indicatorRows(indicatorId) {
    return rows.value.filter((row) => row.indicador === indicatorId);
}

function indicatorTotal(indicatorId) {
    return totals.value[indicatorId];
}

function displayValue(item, unit = 'conteo') {
    if (item.suprimido) {
        return item.supresion === 'primaria' ? `< ${props.resultados.minimo_celda}` : 'Protegido';
    }

    const value = formatter.format(item.valor ?? 0);
    return unit === 'porcentaje' ? `${value}%` : value;
}

function barWidth(item, indicatorId) {
    if (item.suprimido || item.valor === null) return 0;
    const values = indicatorRows(indicatorId).map((row) => row.valor).filter((value) => value !== null);
    const max = Math.max(...values, 0);
    if (max <= 0) return 0;
    return Math.max(3, Math.round((item.valor / max) * 100));
}

function errorMessage(field) {
    return form.errors[field] || form.errors[`${field}.0`] || '';
}

function inputDescription(field) {
    return errorMessage(field) ? `${field}-error` : undefined;
}

function formatDate(value) {
    return new Intl.DateTimeFormat('es-SV', { dateStyle: 'medium' }).format(new Date(`${value}T12:00:00`));
}
</script>

<template>
    <Head title="Indicadores institucionales" />

    <main class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <header class="space-y-1">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--frost3)]">Analítica institucional</p>
            <h1 class="text-2xl font-bold tracking-tight text-[var(--nord0)] sm:text-3xl">Indicadores de bienestar</h1>
            <p class="max-w-3xl text-sm leading-relaxed text-[var(--nord3)]">
                Consulta tendencias agregadas. Los resultados no incluyen pacientes, identificadores ni notas clínicas.
            </p>
        </header>

        <section aria-labelledby="filters-title" class="rounded-2xl border border-[var(--nord4)] bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="filters-title" class="text-lg font-semibold text-[var(--nord0)]">Filtros</h2>
                    <p class="mt-1 text-xs text-[var(--nord3)]">Período máximo: 366 días. Se usan días calendario de El Salvador.</p>
                </div>
                <button type="button" class="min-h-10 rounded-lg px-3 text-sm font-medium text-[var(--nord2)] underline decoration-[var(--nord4)] underline-offset-4 hover:text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--frost3)]" @click="resetFilters">
                    Restablecer
                </button>
            </div>

            <div v-if="Object.keys(form.errors).length" ref="errorSummary" role="alert" tabindex="-1" class="mb-4 rounded-xl border border-[var(--aurora-red)]/40 bg-[var(--aurora-red)]/10 p-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--aurora-red)]">
                <h3 class="font-semibold">Revisa los filtros</h3>
                <ul class="mt-1 list-inside list-disc">
                    <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                </ul>
            </div>

            <div v-if="pageError" role="alert" class="mb-4 rounded-xl border border-[var(--aurora-red)]/40 bg-[var(--aurora-red)]/10 p-3 text-sm text-[var(--nord0)]">
                {{ pageError }}
            </div>

            <form class="space-y-5" @submit.prevent="submitFilters">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="indicator-from" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Desde</label>
                        <input id="indicator-from" v-model="form.desde" type="date" required :aria-invalid="Boolean(form.errors.desde)" :aria-describedby="inputDescription('desde')" class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--frost3)]" />
                        <p v-if="form.errors.desde" id="desde-error" class="mt-1 text-sm text-[var(--aurora-red)]">{{ form.errors.desde }}</p>
                    </div>
                    <div>
                        <label for="indicator-through" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Hasta</label>
                        <input id="indicator-through" v-model="form.hasta" type="date" required :aria-invalid="Boolean(form.errors.hasta)" :aria-describedby="inputDescription('hasta')" class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--frost3)]" />
                        <p v-if="form.errors.hasta" id="hasta-error" class="mt-1 text-sm text-[var(--aurora-red)]">{{ form.errors.hasta }}</p>
                    </div>
                    <div>
                        <label for="indicator-area" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Área</label>
                        <select id="indicator-area" v-model="form.area_id" :aria-invalid="Boolean(form.errors.area_id)" :aria-describedby="inputDescription('area_id')" class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--frost3)]">
                        <option value="">{{ resultados.areas_disponibles.length > 1 ? 'Todas las áreas permitidas' : resultados.areas_disponibles[0]?.nombre || 'Área' }}</option>
                        <option v-for="area in resultados.areas_disponibles" :key="area.id" :value="String(area.id)">{{ area.nombre }}</option>
                        </select>
                        <p v-if="form.errors.area_id" id="area_id-error" class="mt-1 text-sm text-[var(--aurora-red)]">{{ form.errors.area_id }}</p>
                    </div>
                </div>

                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-[var(--nord1)]">Indicadores</legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label v-for="item in resultados.catalogo" :key="item.id" class="flex min-h-11 cursor-pointer items-start gap-2 rounded-lg border border-[var(--nord4)] px-3 py-2.5 text-sm text-[var(--nord1)] transition hover:bg-[var(--nord6)] focus-within:outline focus-within:outline-2 focus-within:outline-[var(--frost3)]">
                            <input v-model="form.indicadores" type="checkbox" :value="item.id" class="mt-0.5 rounded border-[var(--nord3)] text-[var(--frost3)] focus:ring-[var(--frost3)]" />
                            <span class="leading-snug">{{ item.etiqueta }}</span>
                        </label>
                    </div>
                    <p v-if="errorMessage('indicadores')" id="indicadores-error" class="mt-1 text-sm text-[var(--aurora-red)]">{{ errorMessage('indicadores') }}</p>
                </fieldset>

                <div class="flex flex-wrap items-center gap-3 border-t border-[var(--nord5)] pt-4">
                    <button type="submit" :disabled="form.processing" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[var(--nord10)] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--nord9)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:cursor-not-allowed disabled:opacity-60">
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                        {{ form.processing ? 'Calculando…' : 'Aplicar filtros' }}
                    </button>
                    <span v-if="form.processing" role="status" class="text-sm text-[var(--nord3)]">Actualizando resultados</span>
                    <span class="text-xs text-[var(--nord3)]">{{ formatDate(resultados.filtros.desde) }} — {{ formatDate(resultados.filtros.hasta) }}</span>
                </div>
            </form>
        </section>

        <section aria-labelledby="results-title" aria-live="polite" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 id="results-title" class="text-xl font-semibold text-[var(--nord0)]">Resultados agregados</h2>
                    <p class="mt-1 text-xs text-[var(--nord3)]">Los conteos menores de {{ resultados.minimo_celda }} se ocultan para proteger grupos pequeños.</p>
                </div>
                <span class="text-xs text-[var(--nord3)]">{{ resultados.areas.length }} {{ resultados.areas.length === 1 ? 'área' : 'áreas' }} en el alcance</span>
            </div>

            <div v-if="resultados.sin_datos" class="rounded-2xl border border-[var(--nord4)] bg-white p-8 text-center shadow-sm">
                <p class="text-lg font-semibold text-[var(--nord1)]">Sin datos en el período</p>
                <p class="mt-1 text-sm text-[var(--nord3)]">Prueba otro rango de fechas o área.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="item in selectedCatalog" :key="item.id" class="rounded-xl border border-[var(--nord4)] bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-[var(--nord3)]">{{ categoryLabels[item.categoria] || item.categoria }}</p>
                            <h3 class="mt-1 text-sm font-semibold text-[var(--nord1)]">{{ item.etiqueta }}</h3>
                        </div>
                        <FieldTooltip :text="`${item.definicion} Fórmula: ${item.formula}. Fuente: ${item.fuente}.`" />
                    </div>
                    <p class="mt-4 text-2xl font-bold tabular-nums text-[var(--nord0)]">
                        {{ displayValue(indicatorTotal(item.id), indicatorTotal(item.id).unidad) }}
                    </p>
                    <p v-if="indicatorTotal(item.id).suprimido" class="mt-1 text-xs text-[var(--nord3)]">
                        {{ indicatorTotal(item.id).supresion === 'primaria' ? `Valor menor que ${resultados.minimo_celda}` : 'Total protegido por supresión complementaria' }}
                    </p>
                    <p v-else class="mt-1 text-xs text-[var(--nord3)]">{{ item.definicion }}</p>
                </article>
            </div>

            <div v-for="category in categories" :key="category" class="space-y-4">
                <h3 class="pt-2 text-lg font-semibold text-[var(--nord1)]">{{ categoryLabels[category] || category }}</h3>
                <section v-for="item in selectedCatalog.filter((entry) => entry.categoria === category)" :key="item.id" class="overflow-hidden rounded-2xl border border-[var(--nord4)] bg-white shadow-sm">
                    <div class="border-b border-[var(--nord5)] px-4 py-3 sm:px-5">
                        <h4 class="font-semibold text-[var(--nord0)]">{{ item.etiqueta }}</h4>
                        <p class="mt-0.5 text-xs text-[var(--nord3)]">{{ item.definicion }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] border-collapse text-left text-sm">
                            <caption class="sr-only">{{ item.etiqueta }} por área{{ item.id === 'alertas_generadas' ? ' y señal' : '' }}</caption>
                            <thead class="bg-[var(--nord6)] text-xs uppercase tracking-wide text-[var(--nord2)]">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-semibold">Área</th>
                                    <th v-if="item.id === 'alertas_generadas'" scope="col" class="px-4 py-3 font-semibold">Señal</th>
                                    <th scope="col" class="px-4 py-3 text-right font-semibold">{{ item.id === 'tasa_asistencia' ? 'Tasa' : 'Total' }}</th>
                                    <th scope="col" class="w-40 px-4 py-3 font-semibold"><span class="sr-only">Visualización</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--nord5)]">
                                <tr v-for="row in indicatorRows(item.id)" :key="`${row.area_id}-${row.segmento || 'all'}`" class="hover:bg-[var(--nord6)]/70">
                                    <th scope="row" class="px-4 py-3 font-medium text-[var(--nord1)]">{{ row.area }}</th>
                                    <td v-if="item.id === 'alertas_generadas'" class="px-4 py-3 text-[var(--nord2)]">{{ row.segmento_etiqueta || row.segmento }}</td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums text-[var(--nord0)]">
                                        <span v-if="row.suprimido" :title="row.supresion === 'primaria' ? `Conteo menor que ${resultados.minimo_celda}` : 'Valor oculto para impedir reconstrucción por diferencia'">
                                            {{ displayValue(row, indicatorTotal(item.id).unidad) }}
                                            <span class="sr-only">{{ row.supresion === 'primaria' ? `Menor que ${resultados.minimo_celda}` : 'protegido por supresión complementaria' }}</span>
                                        </span>
                                        <template v-else>{{ displayValue(row, indicatorTotal(item.id).unidad) }}</template>
                                    </td>
                                    <td class="px-4 py-3">
                                        <svg v-if="!row.suprimido" class="h-3 w-full" viewBox="0 0 100 12" preserveAspectRatio="none" role="img" :aria-label="`${row.area}: ${displayValue(row, indicatorTotal(item.id).unidad)}`">
                                            <title>{{ row.area }}: {{ displayValue(row, indicatorTotal(item.id).unidad) }}</title>
                                            <rect x="0" y="2" width="100" height="8" rx="4" fill="var(--nord5)" />
                                            <rect x="0" y="2" :width="barWidth(row, item.id)" height="8" rx="4" fill="var(--frost3)" />
                                        </svg>
                                        <span v-else class="text-xs text-[var(--nord3)]">Valor protegido</span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t border-[var(--nord4)] bg-[var(--nord6)]">
                                <tr>
                                    <th scope="row" :colspan="item.id === 'alertas_generadas' ? 2 : 1" class="px-4 py-3 text-sm font-semibold text-[var(--nord0)]">Total del alcance</th>
                                    <td class="px-4 py-3 text-right text-sm font-bold tabular-nums text-[var(--nord0)]">
                                        {{ displayValue(indicatorTotal(item.id), indicatorTotal(item.id).unidad) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-[var(--nord3)]">
                                        <span v-if="indicatorTotal(item.id).suprimido">{{ indicatorTotal(item.id).supresion === 'primaria' ? 'Supresión primaria' : 'Supresión complementaria' }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="px-4 pb-3 text-xs text-[var(--nord3)] sm:px-5">{{ item.formula }} · Fuente: {{ item.fuente }}. Una celda oculta no se incluye en las barras.</p>
                </section>
            </div>
        </section>
    </main>
</template>
