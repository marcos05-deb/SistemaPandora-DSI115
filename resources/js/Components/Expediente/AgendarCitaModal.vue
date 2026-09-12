<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { watch, ref, computed } from 'vue';

const props = defineProps({
    show: Boolean,
    expediente: Object,
    consultaId: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['close']);

const page = usePage();

const form = useForm({
    consulta_id: '',
    fecha_hora: '',
    motivo: '',
});

const close = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};

const submit = () => {
    if (!props.expediente?.id || !form.consulta_id) return;

    form.post(`/expedientes/${props.expediente.id}/citas`, {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};

const minDateTime = ref('');
const maxDateTime = ref('');

const vieneDeConsulta = computed(() =>
    page.props.flash?.prompt_cita_expediente_id === props.expediente?.id
);

watch(() => props.show, (isOpen) => {
    if (isOpen) {
        form.consulta_id = props.consultaId
            || page.props.flash?.prompt_cita_consulta_id
            || '';

        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        minDateTime.value = now.toISOString().slice(0, 16);

        const nextYear = new Date();
        nextYear.setFullYear(nextYear.getFullYear() + 1);
        nextYear.setMinutes(nextYear.getMinutes() - nextYear.getTimezoneOffset());
        maxDateTime.value = nextYear.toISOString().slice(0, 16);
    }
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="close"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="relative z-10 inline-block align-bottom bg-[var(--surface)] rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-[var(--nord4)]">
                <form @submit.prevent="submit">
                    <div class="bg-[var(--surface-header)] px-6 py-4 border-b border-[var(--nord4)]">
                        <h3 class="text-[16px] font-semibold text-[var(--nord0)] flex items-center gap-2" id="modal-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--nord8)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Agendar Próxima Cita
                        </h3>
                        <p class="text-[12px] text-[var(--nord3)] mt-1 ml-7">
                            La cita quedará vinculada a la consulta activa del expediente.
                        </p>
                    </div>

                    <div class="bg-[var(--surface)] px-6 py-5 space-y-5">
                        <div v-if="vieneDeConsulta" class="bg-[var(--aurora-green)]/10 border border-[var(--aurora-green)]/20 rounded-lg p-3 flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--aurora-green)] mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <h4 class="text-[13px] font-bold text-[var(--nord0)]">Consulta guardada exitosamente</h4>
                                <p class="text-[12px] text-[var(--nord3)] mt-0.5">¿Desea agendar la próxima cita de seguimiento desde esta consulta?</p>
                            </div>
                        </div>

                        <div v-if="!form.consulta_id" class="rounded-lg border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/5 px-3 py-2.5">
                            <p class="text-[12px] text-[var(--aurora-red)] font-medium">
                                No hay una consulta activa para vincular. Registre una consulta antes de agendar.
                            </p>
                        </div>
                        <div v-else class="rounded-lg border border-[var(--nord4)] bg-[var(--surface-subtle)] px-3 py-2.5">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-[var(--nord3)]">Consulta origen</p>
                            <p class="text-[12px] text-[var(--nord0)] font-mono mt-0.5">{{ form.consulta_id.substring(0, 8) }}…</p>
                        </div>
                        <p v-if="form.errors.consulta_id" class="text-[var(--aurora-red)] text-[12px] font-medium">{{ form.errors.consulta_id }}</p>

                        <div>
                            <label for="fecha_hora" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                                Fecha y Hora <span class="text-[var(--aurora-red)]">*</span>
                            </label>
                            <input 
                                type="datetime-local" 
                                id="fecha_hora" 
                                v-model="form.fecha_hora"
                                :min="minDateTime"
                                :max="maxDateTime"
                                class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[14px] text-[var(--nord0)] focus:outline-none focus:ring-2 focus:ring-[var(--nord8)]/30 transition-all"
                                :class="form.errors.fecha_hora ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord8)]'"
                                required
                            >
                            <p v-if="form.errors.fecha_hora" class="text-[var(--aurora-red)] text-[12px] mt-1.5 font-medium flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ form.errors.fecha_hora }}
                            </p>
                        </div>
                        
                        <div>
                            <label for="motivo" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                                Motivo de la cita <span class="text-[var(--aurora-red)]">*</span>
                            </label>
                            <textarea 
                                id="motivo" 
                                v-model="form.motivo"
                                rows="3"
                                placeholder="Ej: Seguimiento de tratamiento, revisión de exámenes..."
                                class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[14px] text-[var(--nord0)] focus:outline-none focus:ring-2 focus:ring-[var(--nord8)]/30 transition-all"
                                :class="form.errors.motivo ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord8)]'"
                                required
                            ></textarea>
                            <p v-if="form.errors.motivo" class="text-[var(--aurora-red)] text-[12px] mt-1.5 font-medium flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ form.errors.motivo }}
                            </p>
                        </div>
                    </div>

                    <div class="bg-[var(--surface-subtle)] px-6 py-4 flex items-center justify-end gap-3 border-t border-[var(--nord4)]">
                        <button 
                            type="button" 
                            @click="close"
                            class="px-4 py-2 text-[13px] font-medium text-[var(--nord3)] hover:bg-[var(--nord6)] rounded-lg transition-colors border border-transparent hover:border-[var(--nord4)]"
                        >
                            {{ vieneDeConsulta ? 'Omitir' : 'Cancelar' }}
                        </button>
                        <button 
                            type="submit" 
                            :disabled="form.processing || !form.consulta_id"
                            class="px-5 py-2 bg-[var(--nord8)] hover:bg-[var(--nord9)] text-white text-[13px] font-medium rounded-lg shadow-sm transition-colors flex items-center gap-2 disabled:opacity-75 disabled:cursor-not-allowed"
                        >
                            <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Agendar Cita
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
