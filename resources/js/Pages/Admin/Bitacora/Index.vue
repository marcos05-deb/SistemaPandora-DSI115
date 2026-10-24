<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    eventos: { type: Object, required: true },
    filtros: { type: Object, required: true },
    usuarios: { type: Array, default: () => [] },
    acciones: { type: Array, default: () => [] },
    orden: { type: String, default: 'más reciente primero' },
    export_max: { type: Number, default: 50000 },
});

const page = usePage();
const errorSummary = ref(null);
const expanded = ref(null);
const exporting = ref(false);
const exportError = ref('');
const exportSuccess = ref('');
const form = useForm({
    desde: props.filtros.desde,
    hasta: props.filtros.hasta,
    usuario: props.filtros.usuario ? String(props.filtros.usuario) : '',
    accion: props.filtros.accion ?? '',
    registro: props.filtros.registro ?? '',
    tipo: props.filtros.tipo ?? '',
});

const meta = computed(() => props.eventos.meta ?? {});
const rows = computed(() => props.eventos.data ?? []);
const canView = computed(() => Boolean(page.props.can?.bitacora));
const canExport = computed(() => Boolean(page.props.can?.exportarBitacora));

function applyFilters() {
    exportError.value = '';
    exportSuccess.value = '';
    form.get('/admin/bitacora', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onError: () => nextTick(() => errorSummary.value?.focus()),
    });
}

function clearFilters() {
    router.get('/admin/bitacora', {}, { preserveState: false, replace: true });
}

function exportQuery() {
    const params = new URLSearchParams();
    params.set('desde', form.desde);
    params.set('hasta', form.hasta);
    if (form.usuario) params.set('usuario', form.usuario);
    if (form.accion) params.set('accion', form.accion);
    if (form.registro) params.set('registro', form.registro);
    if (form.tipo) params.set('tipo', form.tipo);
    return params.toString();
}

async function exportEvidence() {
    if (!canExport.value || exporting.value) return;
    exportError.value = '';
    exportSuccess.value = '';
    exporting.value = true;
    try {
        const response = await fetch(`/admin/bitacora/exportar?${exportQuery()}`, {
            headers: { Accept: 'text/csv', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (response.status === 403) {
            exportError.value = 'No tienes permiso para exportar la bitácora.';
            return;
        }
        if (response.status === 422) {
            const payload = await response.json().catch(() => ({}));
            const first = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            exportError.value = first || 'No se pudo exportar con esos filtros.';
            return;
        }
        if (!response.ok) {
            exportError.value = 'No fue posible generar el archivo de evidencia.';
            return;
        }
        const blob = await response.blob();
        const disposition = response.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^"]+)"?/i);
        const filename = match?.[1] || 'bitacora.csv';
        const sha = response.headers.get('X-Checksum-SHA256') || '';
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = filename;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(url);
        exportSuccess.value = sha
            ? `Evidencia descargada. SHA-256: ${sha}`
            : 'Evidencia descargada.';
    } catch {
        exportError.value = 'No se pudo conectar. Revisa tu conexión e inténtalo de nuevo.';
    } finally {
        exporting.value = false;
    }
}

function toggle(id) {
    expanded.value = expanded.value === id ? null : id;
}

function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('es-SV', { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(value));
}
</script>

<template>
    <Head title="Bitácora de auditoría" />

    <main class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <header class="space-y-1">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--frost3)]">Cumplimiento</p>
            <h1 class="text-2xl font-bold tracking-tight text-[var(--nord0)] sm:text-3xl">Bitácora de auditoría</h1>
            <p class="max-w-3xl text-sm leading-relaxed text-[var(--nord3)]">
                Consulta eventos del sistema con filtros combinables. Orden: {{ orden }}. Solo se muestran nombres de campos, nunca valores personales o clínicos.
            </p>
        </header>

        <section v-if="!canView" role="alert" class="rounded-xl border border-[var(--aurora-red)]/40 bg-[var(--aurora-red)]/10 p-4 text-sm text-[var(--nord0)]">
            No tienes permiso para consultar la bitácora.
        </section>

        <template v-else>
            <section aria-labelledby="bitacora-filters-title" class="rounded-2xl border border-[var(--nord4)] bg-white p-4 shadow-sm sm:p-6">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="bitacora-filters-title" class="text-lg font-semibold text-[var(--nord0)]">Filtros</h2>
                        <p class="mt-1 text-xs text-[var(--nord3)]">Período máximo 366 días. Por defecto: últimos 7 días.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="min-h-10 rounded-lg px-3 text-sm font-medium text-[var(--nord2)] underline decoration-[var(--nord4)] underline-offset-4 hover:text-[var(--nord0)]" @click="clearFilters">
                            Limpiar
                        </button>
                        <button
                            v-if="canExport"
                            type="button"
                            :disabled="exporting"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-[var(--nord4)] bg-white px-4 text-sm font-semibold text-[var(--nord1)] hover:bg-[var(--nord6)] disabled:cursor-not-allowed disabled:opacity-60"
                            @click="exportEvidence"
                        >
                            <svg v-if="exporting" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            {{ exporting ? 'Exportando…' : 'Exportar evidencia' }}
                        </button>
                    </div>
                </div>
                <p v-if="canExport" class="mb-3 text-xs text-[var(--nord3)]">
                    Se exportarán los filtros activos (total estimado en pantalla: {{ meta.total ?? 0 }}; máximo {{ export_max }}).
                </p>
                <p v-if="exportSuccess" role="status" class="mb-3 text-sm text-[var(--aurora-green)]">{{ exportSuccess }}</p>
                <p v-if="exportError" role="alert" class="mb-3 text-sm text-[var(--aurora-red)]">{{ exportError }}</p>

                <div v-if="Object.keys(form.errors).length" ref="errorSummary" role="alert" tabindex="-1" class="mb-4 rounded-xl border border-[var(--aurora-red)]/40 bg-[var(--aurora-red)]/10 p-3 text-sm text-[var(--nord0)]">
                    <h3 class="font-semibold">Revisa los filtros</h3>
                    <ul class="mt-1 list-inside list-disc"><li v-for="(message, field) in form.errors" :key="field">{{ message }}</li></ul>
                </div>

                <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="applyFilters">
                    <div>
                        <label for="bitacora-desde" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Desde</label>
                        <input id="bitacora-desde" v-model="form.desde" type="date" required class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)]" />
                        <p v-if="form.errors.desde" class="mt-1 text-sm text-[var(--aurora-red)]">{{ form.errors.desde }}</p>
                    </div>
                    <div>
                        <label for="bitacora-hasta" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Hasta</label>
                        <input id="bitacora-hasta" v-model="form.hasta" type="date" required class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)]" />
                        <p v-if="form.errors.hasta" class="mt-1 text-sm text-[var(--aurora-red)]">{{ form.errors.hasta }}</p>
                    </div>
                    <div>
                        <label for="bitacora-usuario" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Usuario</label>
                        <select id="bitacora-usuario" v-model="form.usuario" class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)]">
                            <option value="">Todos</option>
                            <option v-for="user in usuarios" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="bitacora-accion" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Acción</label>
                        <select id="bitacora-accion" v-model="form.accion" class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)]">
                            <option value="">Todas</option>
                            <option v-for="accion in acciones" :key="accion.id" :value="accion.id">{{ accion.etiqueta }}</option>
                        </select>
                        <p v-if="form.errors.accion" class="mt-1 text-sm text-[var(--aurora-red)]">{{ form.errors.accion }}</p>
                    </div>
                    <div>
                        <label for="bitacora-registro" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Registro afectado (id)</label>
                        <input id="bitacora-registro" v-model="form.registro" type="text" maxlength="64" placeholder="UUID u id" class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)]" />
                    </div>
                    <div>
                        <label for="bitacora-tipo" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Tipo de objeto</label>
                        <input id="bitacora-tipo" v-model="form.tipo" type="text" maxlength="191" placeholder="App\Models\Paciente" class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)]" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <button type="submit" :disabled="form.processing" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[var(--nord10)] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--nord9)] disabled:cursor-not-allowed disabled:opacity-60">
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            {{ form.processing ? 'Consultando…' : 'Aplicar filtros' }}
                        </button>
                    </div>
                </form>
            </section>

            <section aria-labelledby="bitacora-results-title" class="overflow-hidden rounded-2xl border border-[var(--nord4)] bg-white shadow-sm">
                <div class="flex flex-wrap items-end justify-between gap-2 border-b border-[var(--nord5)] px-4 py-3 sm:px-5">
                    <div>
                        <h2 id="bitacora-results-title" class="font-semibold text-[var(--nord0)]">Eventos</h2>
                        <p class="mt-0.5 text-xs text-[var(--nord3)]">
                            <template v-if="meta.total">Mostrando {{ meta.from }}–{{ meta.to }} de {{ meta.total }}</template>
                            <template v-else>Sin resultados para los filtros aplicados</template>
                        </p>
                    </div>
                    <p class="text-xs text-[var(--nord3)]">Orden: {{ orden }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] border-collapse text-left text-sm">
                        <caption class="sr-only">Eventos de auditoría ordenados del más reciente al más antiguo</caption>
                        <thead class="bg-[var(--nord6)] text-xs uppercase tracking-wide text-[var(--nord2)]">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-semibold">Fecha</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Usuario</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Acción</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Objeto</th>
                                <th scope="col" class="px-4 py-3 font-semibold">IP</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Ruta</th>
                                <th scope="col" class="px-4 py-3 font-semibold"><span class="sr-only">Detalle</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--nord5)]">
                            <template v-for="evento in rows" :key="evento.id">
                                <tr class="hover:bg-[var(--nord6)]/70">
                                    <td class="px-4 py-3 tabular-nums text-[var(--nord1)]">{{ formatDate(evento.fecha) }}</td>
                                    <td class="px-4 py-3 text-[var(--nord1)]">
                                        <span v-if="evento.usuario">{{ evento.usuario.nombre }}<span v-if="evento.usuario.rol" class="text-[var(--nord3)]"> · {{ evento.usuario.rol }}</span></span>
                                        <span v-else class="text-[var(--nord3)]">Sistema</span>
                                    </td>
                                    <td class="px-4 py-3 text-[var(--nord1)]">{{ evento.accion_etiqueta }}</td>
                                    <td class="px-4 py-3 text-[var(--nord1)]">
                                        <span class="font-medium">{{ evento.objeto_tipo }}</span>
                                        <span class="block text-xs text-[var(--nord3)]">{{ evento.objeto_id }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[var(--nord3)]">{{ evento.ip || '—' }}</td>
                                    <td class="max-w-[220px] truncate px-4 py-3 text-xs text-[var(--nord3)]" :title="evento.ruta">{{ evento.ruta || '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" class="text-sm font-medium text-[var(--frost3)] underline-offset-2 hover:underline" @click="toggle(evento.id)">
                                            {{ expanded === evento.id ? 'Ocultar' : 'Campos' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expanded === evento.id">
                                    <td colspan="7" class="bg-[var(--nord6)]/50 px-4 py-3 text-sm text-[var(--nord1)]">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--nord3)]">Campos tocados (solo nombres)</p>
                                        <ul v-if="evento.campos?.length" class="mt-2 flex flex-wrap gap-2">
                                            <li v-for="campo in evento.campos" :key="campo" class="rounded border border-[var(--nord4)] bg-white px-2 py-1 text-xs">{{ campo }}</li>
                                        </ul>
                                        <p v-else class="mt-2 text-xs text-[var(--nord3)]">Sin nombres de campo registrados.</p>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="rows.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-[var(--nord3)]">No hay eventos en el período seleccionado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="eventos.links || []" />
            </section>
        </template>
    </main>
</template>
