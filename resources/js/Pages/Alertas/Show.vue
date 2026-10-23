<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import Modal from '@/Components/UI/Modal.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import { formatElSalvadorDateTime, formatLocalDate } from '@/utils/dates';

dayjs.extend(utc);
dayjs.extend(timezone);

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    alerta: { type: Object, default: null },
    can: { type: Object, default: null },
    responsableSeguimiento: { type: Object, default: null },
});

const page = usePage();
const pageError = ref('');
const activeModal = ref('');
const followUpErrorSummary = ref(null);
const modalErrorSummary = ref(null);
const closeTrigger = ref(null);
const discardTrigger = ref(null);
const closeInput = ref(null);
const discardInput = ref(null);
const permissions = computed(() => props.can ?? props.alerta?.can ?? { seguimiento: false, cerrar: false, descartar: false });
const today = dayjs().tz('America/El_Salvador').format('YYYY-MM-DD');
const maxReview = dayjs.tz(today, 'America/El_Salvador').add(365, 'day').format('YYYY-MM-DD');

const followUpForm = useForm({
    valoracion: '',
    accion_preventiva: '',
    responsable_profesional_id: props.responsableSeguimiento?.id ?? '',
    proxima_revision: '',
});
const closeForm = useForm({ resultado: '' });
const discardForm = useForm({ justificacion: '' });

const isTerminal = computed(() => ['cerrada', 'descartada'].includes(props.alerta?.estado));
const followUps = computed(() => [...(props.alerta?.seguimientos ?? [])].sort((a, b) => {
    const byDate = new Date(a.created_at ?? a.registrado_en).getTime() - new Date(b.created_at ?? b.registrado_en).getTime();
    return byDate || String(a.id).localeCompare(String(b.id));
}));
const nextReview = computed(() => props.alerta?.proxima_revision ?? null);
const isOverdue = computed(() => Boolean(nextReview.value && nextReview.value < today));
const rubricVersion = computed(() => props.alerta?.rubrica_version_numero ?? props.alerta?.version_rubrica?.numero ?? 'No registrada');
const rubricEffectiveDate = computed(() => props.alerta?.rubrica_vigente_desde ?? props.alerta?.version_rubrica?.vigente_desde ?? null);
const status = computed(() => ({
    activa: { label: 'Activa', variant: 'active' },
    en_seguimiento: { label: 'En seguimiento', variant: 'specialist' },
    cerrada: { label: 'Cerrada', variant: 'inactive' },
    descartada: { label: 'Descartada', variant: 'blocked' },
}[props.alerta?.estado] ?? { label: props.alerta?.estado ?? 'No disponible', variant: 'inactive' }));
const signalLabel = computed(() => props.alerta?.senal_label || props.alerta?.etiqueta_senal || ({
    ausencia_cita: 'Ausencia a cita',
    crisis_psicologica: 'Crisis psicológica',
    tendencia_suicida: 'Tendencia suicida',
}[props.alerta?.senal] ?? props.alerta?.senal ?? 'Señal no disponible'));
const canAct = computed(() => Boolean(permissions.value.seguimiento || permissions.value.cerrar || permissions.value.descartar));
const readOnly = computed(() => isTerminal.value || !canAct.value);
const modalTitle = computed(() => activeModal.value === 'cerrar' ? 'Cerrar alerta' : 'Descartar alerta');
const followUpErrors = computed(() => Object.entries(followUpForm.errors).map(([field, message]) => ({
    field,
    label: ({
        valoracion: 'Valoración',
        accion_preventiva: 'Acción preventiva',
        responsable_profesional_id: 'Responsable',
        proxima_revision: 'Próxima revisión',
    })[field] ?? field,
    message,
})));
const modalForm = computed(() => activeModal.value === 'cerrar' ? closeForm : discardForm);
const modalErrors = computed(() => Object.entries(modalForm.value.errors).map(([field, message]) => ({
    field,
    label: activeModal.value === 'cerrar' ? 'Resultado' : 'Justificación',
    message,
})));

function handleHttpException(response) {
    pageError.value = response.status === 403
        ? 'No tienes permiso para realizar esta acción.'
        : response.status === 404
            ? 'La alerta ya no está disponible.'
            : 'No fue posible guardar los cambios. Inténtalo de nuevo.';
    return false;
}

function handleNetworkError() {
    pageError.value = 'No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.';
}

function focusFollowUpErrors() {
    nextTick(() => followUpErrorSummary.value?.focus());
}

function focusModalErrors() {
    nextTick(() => modalErrorSummary.value?.focus());
}

function submitFollowUp() {
    if (!props.alerta || !permissions.value.seguimiento || isTerminal.value) return;
    pageError.value = '';
    followUpForm.post(`/alertas/${encodeURIComponent(props.alerta.id)}/seguimientos`, {
        preserveScroll: true,
        onSuccess: () => followUpForm.reset(),
        onError: focusFollowUpErrors,
        onHttpException: handleHttpException,
        onNetworkError: handleNetworkError,
    });
}

function openModal(action, event) {
    if (isTerminal.value || !permissions.value[action] || followUpForm.processing || closeForm.processing || discardForm.processing) return;
    if (action === 'cerrar') {
        closeTrigger.value = event.currentTarget;
        closeForm.reset();
        closeForm.clearErrors();
    } else {
        discardTrigger.value = event.currentTarget;
        discardForm.reset();
        discardForm.clearErrors();
    }
    pageError.value = '';
    activeModal.value = action;
}

function closeModal() {
    if (closeForm.processing || discardForm.processing) return;
    const closing = activeModal.value;
    activeModal.value = '';
    if (closing === 'cerrar') closeForm.reset();
    if (closing === 'descartar') discardForm.reset();
}

function submitClose() {
    if (!props.alerta || !permissions.value.cerrar || isTerminal.value || closeForm.processing) return;
    pageError.value = '';
    closeForm.post(`/alertas/${encodeURIComponent(props.alerta.id)}/cerrar`, {
        preserveScroll: true,
        onSuccess: () => { closeForm.reset(); activeModal.value = ''; },
        onError: focusModalErrors,
        onHttpException: handleHttpException,
        onNetworkError: handleNetworkError,
    });
}

function submitDiscard() {
    if (!props.alerta || !permissions.value.descartar || isTerminal.value || discardForm.processing) return;
    pageError.value = '';
    discardForm.post(`/alertas/${encodeURIComponent(props.alerta.id)}/descartar`, {
        preserveScroll: true,
        onSuccess: () => { discardForm.reset(); activeModal.value = ''; },
        onError: focusModalErrors,
        onHttpException: handleHttpException,
        onNetworkError: handleNetworkError,
    });
}

function onModalKeydown(event) {
    if (!activeModal.value) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        closeModal();
        return;
    }
    if (event.key !== 'Tab') return;

    const dialog = document.querySelector('[role="dialog"]');
    const focusable = dialog?.querySelectorAll('button:not(:disabled), textarea:not(:disabled)');
    if (!focusable?.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(activeModal, (action, previousAction) => {
    if (action) {
        document.addEventListener('keydown', onModalKeydown);
        nextTick(() => (action === 'cerrar' ? closeInput.value : discardInput.value)?.focus());
    } else if (previousAction) {
        document.removeEventListener('keydown', onModalKeydown);
        nextTick(() => (previousAction === 'cerrar' ? closeTrigger.value : discardTrigger.value)?.focus());
    }
});

onUnmounted(() => document.removeEventListener('keydown', onModalKeydown));
</script>

<template>
    <Head :title="alerta ? `Alerta ${alerta.id}` : 'Alerta no disponible'" />

    <div class="space-y-5">
        <Breadcrumbs :items="[
            { label: 'Panel clínico', href: '/dashboard' },
            { label: 'Alertas', href: '/alertas' },
            { label: alerta ? `Alerta ${alerta.id}` : 'Alerta no disponible' },
        ]" />

        <section v-if="!alerta" aria-labelledby="unavailable-heading" class="rounded-xl border border-[var(--nord4)] bg-white p-6 shadow-sm">
            <h1 id="unavailable-heading" class="text-xl font-semibold tracking-tight text-[var(--nord0)]">Alerta no disponible</h1>
            <p class="mt-2 text-sm leading-relaxed text-[var(--nord3)]">No fue posible cargar la información de esta alerta.</p>
            <Link href="/alertas" class="mt-4 inline-flex min-h-11 items-center rounded-lg border border-[var(--nord4)] px-4 py-2 text-sm font-semibold text-[var(--nord10)] hover:bg-[var(--surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]">
                Volver a alertas
            </Link>
        </section>

        <template v-else>
            <header class="flex flex-col gap-4 rounded-xl border border-[var(--nord4)] bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold tracking-tight text-[var(--nord0)]">{{ signalLabel }}</h1>
                        <StatusBadge :label="status.label" :variant="status.variant" />
                    </div>
                    <p class="mt-2 text-sm text-[var(--nord3)]">Alerta generada {{ formatElSalvadorDateTime(alerta.fecha_generacion) }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="`/pacientes/${encodeURIComponent(alerta.paciente_carnet)}`"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--nord4)] px-4 py-2 text-sm font-semibold text-[var(--nord10)] hover:bg-[var(--surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
                        :aria-label="`Abrir expediente del paciente ${alerta.paciente_carnet}`"
                    >
                        Paciente {{ alerta.paciente_carnet }}
                    </Link>
                    <Link href="/alertas" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--nord4)] px-4 py-2 text-sm font-semibold text-[var(--nord10)] hover:bg-[var(--surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]">
                        Volver a alertas
                    </Link>
                </div>
            </header>

            <section v-if="pageError" role="alert" class="rounded-lg border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/10 px-4 py-3 text-sm text-[var(--nord0)]">
                {{ pageError }}
            </section>

            <section aria-labelledby="origin-heading" class="overflow-hidden rounded-xl border border-[var(--nord4)] bg-white shadow-sm">
                <div class="border-b border-[var(--nord4)] bg-[var(--surface-header)] px-5 py-4">
                    <h2 id="origin-heading" class="text-base font-semibold text-[var(--nord0)]">Origen de la alerta</h2>
                    <p class="mt-1 text-sm text-[var(--nord3)]">Valores conservados de la versión de rúbrica que generó esta alerta.</p>
                </div>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-sm text-[var(--nord3)]">Señal</dt>
                        <dd class="mt-1 font-semibold text-[var(--nord0)]">{{ signalLabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[var(--nord3)]">Valor calculado</dt>
                        <dd class="mt-1 font-semibold text-[var(--nord0)]">{{ alerta.valor_calculado }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[var(--nord3)]">Umbral aplicado</dt>
                        <dd class="mt-1 font-semibold text-[var(--nord0)]">{{ alerta.umbral_aplicado }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[var(--nord3)]">Versión de rúbrica</dt>
                        <dd class="mt-1 font-semibold text-[var(--nord0)]">Versión {{ rubricVersion }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[var(--nord3)]">Vigente desde</dt>
                        <dd class="mt-1 font-semibold text-[var(--nord0)]">{{ formatLocalDate(rubricEffectiveDate) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[var(--nord3)]">Responsable de la alerta</dt>
                        <dd class="mt-1 font-semibold text-[var(--nord0)]">{{ alerta.responsable?.nombre || 'No registrado' }}</dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="review-heading" :class="[
                'rounded-xl border p-5 shadow-sm',
                isOverdue ? 'border-[var(--aurora-red)]/40 bg-[var(--aurora-red)]/10' : 'border-[var(--nord4)] bg-white',
            ]">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="review-heading" class="text-base font-semibold text-[var(--nord0)]">Próxima revisión</h2>
                        <p class="mt-1 text-sm text-[var(--nord3)]">{{ nextReview ? formatLocalDate(nextReview) : 'No hay una revisión programada.' }}</p>
                    </div>
                    <p v-if="isOverdue" role="status" class="font-semibold text-[var(--aurora-red)]">La fecha de revisión está vencida.</p>
                    <p v-else-if="nextReview" class="text-sm font-medium text-[var(--nord10)]">Fecha programada</p>
                </div>
            </section>

            <section aria-labelledby="timeline-heading" class="overflow-hidden rounded-xl border border-[var(--nord4)] bg-white shadow-sm">
                <div class="border-b border-[var(--nord4)] bg-[var(--surface-header)] px-5 py-4">
                    <h2 id="timeline-heading" class="text-base font-semibold text-[var(--nord0)]">Seguimientos preventivos</h2>
                    <p class="mt-1 text-sm text-[var(--nord3)]">Registros cronológicos; cada seguimiento se conserva como historial.</p>
                </div>
                <p v-if="followUps.length === 0" class="px-5 py-8 text-sm text-[var(--nord3)]">Aún no se han registrado seguimientos.</p>
                <ol v-else class="divide-y divide-[var(--nord5)]">
                    <li v-for="followUp in followUps" :key="followUp.id" class="p-5">
                        <article class="space-y-3">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <h3 class="font-semibold text-[var(--nord0)]">Seguimiento preventivo</h3>
                                <time class="text-sm text-[var(--nord3)]" :datetime="followUp.created_at || followUp.registrado_en">{{ formatElSalvadorDateTime(followUp.created_at || followUp.registrado_en) }}</time>
                            </div>
                            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-[var(--nord3)]">Valoración</dt>
                                    <dd class="mt-1 whitespace-pre-line break-words text-sm leading-relaxed text-[var(--nord0)]">{{ followUp.valoracion }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-[var(--nord3)]">Acción preventiva</dt>
                                    <dd class="mt-1 whitespace-pre-line break-words text-sm leading-relaxed text-[var(--nord0)]">{{ followUp.accion_preventiva }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-[var(--nord3)]">Responsable</dt>
                                    <dd class="mt-1 text-sm text-[var(--nord0)]">{{ followUp.responsable?.nombre || 'No registrado' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-[var(--nord3)]">Registrado por</dt>
                                    <dd class="mt-1 text-sm text-[var(--nord0)]">{{ followUp.registrado_por?.nombre || 'No registrado' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-[var(--nord3)]">Próxima revisión</dt>
                                    <dd class="mt-1 text-sm font-semibold text-[var(--nord0)]">{{ formatLocalDate(followUp.proxima_revision) }}</dd>
                                </div>
                            </dl>
                        </article>
                    </li>
                </ol>
            </section>

            <section v-if="alerta.estado === 'cerrada' || alerta.estado === 'descartada'" aria-labelledby="resolution-heading" class="rounded-xl border border-[var(--nord4)] bg-white p-5 shadow-sm">
                <h2 id="resolution-heading" class="text-base font-semibold text-[var(--nord0)]">Resultado de la alerta</h2>
                <p v-if="alerta.estado === 'cerrada'" class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-[var(--nord1)]">{{ alerta.resultado_cierre }}</p>
                <p v-else class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-[var(--nord1)]">Justificación del descarte: {{ alerta.justificacion_descarte }}</p>
                <p class="mt-3 text-sm text-[var(--nord3)]">
                    {{ alerta.estado === 'cerrada' ? 'Cerrada' : 'Descartada' }}
                    {{ formatElSalvadorDateTime(alerta.cerrada_en || alerta.descartada_en) }}
                </p>
            </section>

            <section v-if="readOnly" aria-label="Estado de solo lectura" class="rounded-lg border border-[var(--nord4)] bg-[var(--surface-subtle)] px-4 py-3 text-sm text-[var(--nord1)]">
                <template v-if="isTerminal">Esta alerta tiene un estado terminal y solo puede consultarse.</template>
                <template v-else>No tienes permisos para registrar seguimientos ni cambiar el estado de esta alerta.</template>
            </section>

            <section v-if="permissions.seguimiento && !isTerminal" aria-labelledby="follow-up-heading" class="overflow-hidden rounded-xl border border-[var(--nord4)] bg-white shadow-sm">
                <div class="border-b border-[var(--nord4)] bg-[var(--surface-header)] px-5 py-4">
                    <h2 id="follow-up-heading" class="text-base font-semibold text-[var(--nord0)]">Registrar seguimiento</h2>
                    <p class="mt-1 text-sm text-[var(--nord3)]">El registro será permanente y aparecerá en la línea de tiempo.</p>
                </div>
                <form class="space-y-4 p-5" @submit.prevent="submitFollowUp">
                    <div v-if="followUpErrors.length" ref="followUpErrorSummary" role="alert" tabindex="-1" class="rounded-lg border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/10 p-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--aurora-red)]">
                        <p class="font-semibold">Revisa los campos marcados:</p>
                        <ul class="mt-1 list-inside list-disc">
                            <li v-for="error in followUpErrors" :key="error.field">{{ error.label }}: {{ error.message }}</li>
                        </ul>
                    </div>

                    <div>
                <label for="valoracion" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Valoración <span class="text-[var(--aurora-red)]">*</span></label>
                        <textarea
                            id="valoracion"
                            v-model="followUpForm.valoracion"
                            rows="3"
                            required
                            minlength="10"
                            maxlength="5000"
                            :disabled="followUpForm.processing"
                            :aria-invalid="Boolean(followUpForm.errors.valoracion)"
                            :aria-describedby="followUpForm.errors.valoracion ? 'valoracion-error' : undefined"
                            class="block min-h-24 w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:opacity-60"
                            :class="followUpForm.errors.valoracion ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                        />
                        <p v-if="followUpForm.errors.valoracion" id="valoracion-error" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ followUpForm.errors.valoracion }}</p>
                    </div>

                    <div>
                        <label for="accion_preventiva" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Acción preventiva <span class="text-[var(--aurora-red)]">*</span></label>
                        <textarea
                            id="accion_preventiva"
                            v-model="followUpForm.accion_preventiva"
                            rows="3"
                            required
                            minlength="10"
                            maxlength="5000"
                            :disabled="followUpForm.processing"
                            :aria-invalid="Boolean(followUpForm.errors.accion_preventiva)"
                            :aria-describedby="followUpForm.errors.accion_preventiva ? 'accion-preventiva-error' : undefined"
                            class="block min-h-24 w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:opacity-60"
                            :class="followUpForm.errors.accion_preventiva ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                        />
                        <p v-if="followUpForm.errors.accion_preventiva" id="accion-preventiva-error" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ followUpForm.errors.accion_preventiva }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div role="group" aria-labelledby="responsable-label">
                            <span id="responsable-label" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Responsable</span>
                            <p class="flex min-h-11 items-center rounded-lg border border-[var(--nord4)] bg-[var(--nord6)] px-3 text-sm text-[var(--nord0)]">{{ responsableSeguimiento?.nombre || page.props.auth?.user?.name || 'Usuario autenticado' }}</p>
                            <p class="mt-1 text-xs text-[var(--nord3)]">Se asigna automáticamente a la persona autenticada.</p>
                            <p v-if="followUpForm.errors.responsable_profesional_id" id="responsable-error" role="alert" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ followUpForm.errors.responsable_profesional_id }}</p>
                            <input type="hidden" :value="followUpForm.responsable_profesional_id" />
                        </div>
                        <div>
                            <label for="proxima_revision" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Próxima revisión <span class="text-[var(--aurora-red)]">*</span></label>
                            <input
                                id="proxima_revision"
                                v-model="followUpForm.proxima_revision"
                                type="date"
                                required
                                :min="today"
                                :max="maxReview"
                                :disabled="followUpForm.processing"
                                :aria-invalid="Boolean(followUpForm.errors.proxima_revision)"
                                :aria-describedby="followUpForm.errors.proxima_revision ? 'proxima-revision-error' : undefined"
                                class="block min-h-11 w-full rounded-lg border bg-white px-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:opacity-60"
                                :class="followUpForm.errors.proxima_revision ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                            />
                            <p v-if="followUpForm.errors.proxima_revision" id="proxima-revision-error" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ followUpForm.errors.proxima_revision }}</p>
                            <p class="mt-1 text-xs text-[var(--nord3)]">Selecciona una fecha entre hoy y los próximos 365 días.</p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="submit"
                            class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[var(--nord10)] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[var(--nord9)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:cursor-wait disabled:opacity-60"
                            :disabled="followUpForm.processing"
                        >
                            <svg v-if="followUpForm.processing" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            {{ followUpForm.processing ? 'Guardando seguimiento…' : 'Guardar seguimiento' }}
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="!isTerminal && (permissions.cerrar || permissions.descartar)" aria-label="Acciones de alerta" class="flex flex-col gap-3 rounded-xl border border-[var(--nord4)] bg-white p-5 shadow-sm sm:flex-row sm:justify-end">
                <button
                    v-if="permissions.cerrar"
                    ref="closeTrigger"
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--nord10)] px-4 py-2 text-sm font-semibold text-[var(--nord10)] hover:bg-[var(--surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:cursor-wait disabled:opacity-60"
                    :disabled="followUpForm.processing || closeForm.processing || discardForm.processing"
                    @click="openModal('cerrar', $event)"
                >
                    Cerrar alerta
                </button>
                <button
                    v-if="permissions.descartar"
                    ref="discardTrigger"
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--aurora-red)]/50 px-4 py-2 text-sm font-semibold text-[var(--aurora-red)] hover:bg-[var(--aurora-red)]/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--aurora-red)] disabled:cursor-wait disabled:opacity-60"
                    :disabled="followUpForm.processing || closeForm.processing || discardForm.processing"
                    @click="openModal('descartar', $event)"
                >
                    Descartar alerta
                </button>
            </section>
        </template>
    </div>

    <Modal :show="Boolean(activeModal)" :title="modalTitle" @close="closeModal">
        <template v-if="activeModal === 'cerrar'">
            <p class="mb-4 text-sm leading-relaxed text-[var(--nord3)]">El cierre es permanente. Registra el resultado de la atención antes de confirmar.</p>
            <form id="close-alert-form" class="space-y-3" @submit.prevent="submitClose">
                <div v-if="modalErrors.length" ref="modalErrorSummary" role="alert" tabindex="-1" class="rounded-lg border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/10 p-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--aurora-red)]">
                    <p class="font-semibold">Revisa el campo marcado:</p>
                    <ul class="mt-1 list-inside list-disc"><li v-for="error in modalErrors" :key="error.field">{{ error.label }}: {{ error.message }}</li></ul>
                </div>
                <div>
                    <label for="resultado" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Resultado <span class="text-[var(--aurora-red)]">*</span></label>
                    <textarea
                        id="resultado"
                        ref="closeInput"
                        v-model="closeForm.resultado"
                        rows="4"
                        required
                        minlength="10"
                        :disabled="closeForm.processing"
                        :aria-invalid="Boolean(closeForm.errors.resultado)"
                        :aria-describedby="closeForm.errors.resultado ? 'resultado-error' : undefined"
                        class="block w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:opacity-60"
                        :class="closeForm.errors.resultado ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                    />
                    <p v-if="closeForm.errors.resultado" id="resultado-error" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ closeForm.errors.resultado }}</p>
                </div>
            </form>
        </template>
        <template v-else-if="activeModal === 'descartar'">
            <p class="mb-4 text-sm leading-relaxed text-[var(--nord3)]">Explica por qué consideras que esta alerta es un posible falso positivo antes de descartarla.</p>
            <form id="discard-alert-form" class="space-y-3" @submit.prevent="submitDiscard">
                <div v-if="modalErrors.length" ref="modalErrorSummary" role="alert" tabindex="-1" class="rounded-lg border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/10 p-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--aurora-red)]">
                    <p class="font-semibold">Revisa el campo marcado:</p>
                    <ul class="mt-1 list-inside list-disc"><li v-for="error in modalErrors" :key="error.field">{{ error.label }}: {{ error.message }}</li></ul>
                </div>
                <div>
                    <label for="justificacion" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Justificación del descarte <span class="text-[var(--aurora-red)]">*</span></label>
                    <textarea
                        id="justificacion"
                        ref="discardInput"
                        v-model="discardForm.justificacion"
                        rows="4"
                        required
                        minlength="10"
                        :disabled="discardForm.processing"
                        :aria-invalid="Boolean(discardForm.errors.justificacion)"
                        :aria-describedby="discardForm.errors.justificacion ? 'justificacion-error' : undefined"
                        class="block w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:opacity-60"
                        :class="discardForm.errors.justificacion ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                    />
                    <p v-if="discardForm.errors.justificacion" id="justificacion-error" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ discardForm.errors.justificacion }}</p>
                </div>
            </form>
        </template>
        <template #footer>
            <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--nord4)] px-4 py-2 text-sm font-semibold text-[var(--nord1)] hover:bg-[var(--surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:opacity-60" :disabled="closeForm.processing || discardForm.processing" @click="closeModal">
                Cancelar
            </button>
            <button
                v-if="activeModal === 'cerrar'"
                type="submit"
                form="close-alert-form"
                class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[var(--nord10)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--nord9)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:cursor-wait disabled:opacity-60"
                :disabled="closeForm.processing"
            >
                <svg v-if="closeForm.processing" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                {{ closeForm.processing ? 'Cerrando…' : 'Confirmar cierre' }}
            </button>
            <button
                v-else
                type="submit"
                form="discard-alert-form"
                class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[var(--aurora-red)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--aurora-red)] disabled:cursor-wait disabled:opacity-60"
                :disabled="discardForm.processing"
            >
                <svg v-if="discardForm.processing" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                {{ discardForm.processing ? 'Descartando…' : 'Confirmar descarte' }}
            </button>
        </template>
    </Modal>
</template>
