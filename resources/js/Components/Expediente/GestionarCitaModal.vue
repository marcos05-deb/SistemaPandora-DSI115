<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch, ref, computed } from 'vue';

const props = defineProps({
    show: Boolean,
    mode: {
        type: String,
        required: true, // 'reprogramar' | 'cancelar'
    },
    cita: {
        type: Object,
        required: true,
    },
    expediente: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    fecha_hora: '',
    motivo_cancelacion: '',
    motivo_reprogramacion: '',
    acordada_con_paciente: false,
});

const close = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};

const submit = () => {
    const url = `/expedientes/${props.expediente.id}/citas/${props.cita.id}/${props.mode}`;
    
    form.patch(url, {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};

// Configuración de límites para el input de fecha y hora
const minDateTime = ref('');
const maxDateTime = ref('');

watch(() => props.show, (isOpen) => {
    if (isOpen) {
        if (props.mode === 'reprogramar') {
            // Establecer fecha/hora mínima a ahora
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            minDateTime.value = now.toISOString().slice(0, 16);
            
            // Máximo a 1 año vista
            const nextYear = new Date();
            nextYear.setFullYear(nextYear.getFullYear() + 1);
            nextYear.setMinutes(nextYear.getMinutes() - nextYear.getTimezoneOffset());
            maxDateTime.value = nextYear.toISOString().slice(0, 16);
        }
    }
});

const title = computed(() => {
    return props.mode === 'reprogramar' ? 'Reprogramar Cita' : 'Cancelar Cita';
});

const description = computed(() => {
    if (props.mode === 'reprogramar') {
        return 'Indique el motivo del cambio y la nueva fecha acordada con el paciente. El motivo clínico original se conserva.';
    }
    return 'Por favor, indique el motivo por el cual se cancela esta cita.';
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Overlay -->
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="close"></div>

            <!-- Modal Panel -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="relative z-10 inline-block align-bottom bg-[var(--surface)] rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-[var(--nord4)]">
                <form @submit.prevent="submit">
                    <!-- Header -->
                    <div class="bg-[var(--surface-header)] px-6 py-4 border-b border-[var(--nord4)]">
                        <h3 class="text-[16px] font-semibold flex items-center gap-2" :class="mode === 'cancelar' ? 'text-[var(--aurora-red)]' : 'text-[var(--nord0)]'" id="modal-title">
                            <svg v-if="mode === 'reprogramar'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--nord8)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--aurora-red)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ title }}
                        </h3>
                        <p class="text-[12px] text-[var(--nord3)] mt-1 ml-7">
                            {{ description }}
                        </p>
                    </div>

                    <!-- Body -->
                    <div class="bg-[var(--surface)] px-6 py-5 space-y-5">
                        <div v-if="mode === 'reprogramar'">
                            <label for="fecha_hora" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                                Nueva Fecha y Hora <span class="text-[var(--aurora-red)]">*</span>
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

                            <label for="motivo_reprogramacion" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5 mt-4">
                                Motivo de reprogramación <span class="text-[var(--aurora-red)]">*</span>
                            </label>
                            <textarea 
                                id="motivo_reprogramacion" 
                                v-model="form.motivo_reprogramacion"
                                rows="3"
                                placeholder="Ej: Paciente solicitó cambio de horario por examen..."
                                class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[14px] text-[var(--nord0)] focus:outline-none focus:ring-2 focus:ring-[var(--nord8)]/30 transition-all"
                                :class="form.errors.motivo_reprogramacion ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord8)]'"
                                required
                            ></textarea>
                            <p v-if="form.errors.motivo_reprogramacion" class="text-[var(--aurora-red)] text-[12px] mt-1.5 font-medium flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ form.errors.motivo_reprogramacion }}
                            </p>

                            <div class="flex items-start gap-2 mt-4">
                                <input
                                    id="acordada_con_paciente"
                                    v-model="form.acordada_con_paciente"
                                    type="checkbox"
                                    class="mt-0.5 w-4 h-4 rounded border-[var(--nord4)] text-[var(--nord8)] focus:ring-[var(--nord8)]"
                                >
                                <label for="acordada_con_paciente" class="text-[13px] text-[var(--nord0)]">
                                    Confirmo que la nueva fecha y hora fueron <span class="font-semibold">acordadas con el paciente</span>
                                    <span class="text-[var(--aurora-red)]">*</span>
                                </label>
                            </div>
                            <p v-if="form.errors.acordada_con_paciente" class="text-[var(--aurora-red)] text-[12px] mt-1.5 font-medium">
                                {{ form.errors.acordada_con_paciente }}
                            </p>
                        </div>
                        
                        <div v-if="mode === 'cancelar'">
                            <label for="motivo_cancelacion" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                                Motivo de cancelación <span class="text-[var(--aurora-red)]">*</span>
                            </label>
                            <textarea 
                                id="motivo_cancelacion" 
                                v-model="form.motivo_cancelacion"
                                rows="3"
                                placeholder="Ej: Paciente solicitó cancelación, error al agendar..."
                                class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[14px] text-[var(--nord0)] focus:outline-none focus:ring-2 focus:ring-[var(--aurora-red)]/30 transition-all"
                                :class="form.errors.motivo_cancelacion ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--aurora-red)]'"
                                required
                            ></textarea>
                            <p v-if="form.errors.motivo_cancelacion" class="text-[var(--aurora-red)] text-[12px] mt-1.5 font-medium flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ form.errors.motivo_cancelacion }}
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="bg-[var(--surface-subtle)] px-6 py-4 flex items-center justify-end gap-3 border-t border-[var(--nord4)]">
                        <button 
                            type="button" 
                            @click="close"
                            class="px-4 py-2 text-[13px] font-medium text-[var(--nord3)] hover:bg-[var(--nord6)] rounded-lg transition-colors border border-transparent hover:border-[var(--nord4)]"
                        >
                            Cerrar
                        </button>
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="px-5 py-2 text-white text-[13px] font-medium rounded-lg shadow-sm transition-colors flex items-center gap-2 disabled:opacity-75 disabled:cursor-not-allowed"
                            :class="mode === 'cancelar' ? 'bg-[var(--aurora-red)] hover:bg-red-600' : 'bg-[var(--nord8)] hover:bg-[var(--nord9)]'"
                        >
                            <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ mode === 'reprogramar' ? 'Guardar Cambios' : 'Confirmar Cancelación' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
