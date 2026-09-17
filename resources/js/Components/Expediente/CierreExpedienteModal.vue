<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    expediente: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['close']);

const form = useForm({
    motivo_cierre: '',
    resultado_final: '',
    confirmacion_irreversible: false,
});

const isSubmitting = ref(false);

watch(() => props.show, (newVal) => {
    if (newVal) {
        form.reset();
        form.clearErrors();
        isSubmitting.value = false;
    }
});

const close = () => {
    emit('close');
};

const submit = () => {
    if (!props.expediente) return;
    
    isSubmitting.value = true;
    form.post(`/expedientes/${props.expediente.id}/cerrar`, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            close();
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="close"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="relative z-10 inline-block align-bottom bg-[var(--surface)] rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-[var(--aurora-red)]">
                <div class="bg-[var(--surface)] px-4 pt-5 pb-4 sm:p-6 sm:pb-4 relative">
                    
                    <button @click="close" class="absolute top-4 right-4 text-[var(--nord3)] hover:text-[var(--nord0)] transition-colors">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-[var(--aurora-red)]/10 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-[var(--aurora-red)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-semibold text-[var(--nord0)]" id="modal-title">
                                Cierre Permanente de Expediente
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-[var(--nord3)]">
                                    Esta acción es irreversible y clausurará el registro clínico activo. Por favor, justifique detalladamente el motivo.
                                </p>
                            </div>

                            <form @submit.prevent="submit" class="mt-5 space-y-4 text-left">
                                <div>
                                    <label for="resultado_final" class="block text-sm font-medium text-[var(--nord0)] mb-1">
                                        Resultado final <span class="text-[var(--aurora-red)]">*</span>
                                    </label>
                                    <textarea
                                        id="resultado_final"
                                        v-model="form.resultado_final"
                                        rows="3"
                                        class="block w-full border text-[var(--nord0)] text-sm rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-[var(--aurora-red)] focus:border-[var(--aurora-red)] transition-colors duration-200"
                                        :class="{'border-[var(--aurora-red)] bg-red-50': form.errors.resultado_final, 'border-[var(--nord4)] bg-[var(--surface)]': !form.errors.resultado_final}"
                                        :disabled="isSubmitting"
                                        placeholder="Describa el resultado clínico final del proceso de atención..."
                                    ></textarea>
                                    <p v-if="form.errors.resultado_final" class="mt-1.5 text-xs text-[var(--aurora-red)]">
                                        {{ form.errors.resultado_final }}
                                    </p>
                                </div>
                                <div>
                                    <label for="motivo_cierre" class="block text-sm font-medium text-[var(--nord0)] mb-1">
                                        Motivo de Cierre Clínico <span class="text-[var(--aurora-red)]">*</span>
                                    </label>
                                    <textarea
                                        id="motivo_cierre"
                                        v-model="form.motivo_cierre"
                                        rows="3"
                                        class="block w-full border text-[var(--nord0)] text-sm rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-[var(--aurora-red)] focus:border-[var(--aurora-red)] transition-colors duration-200"
                                        :class="{'border-[var(--aurora-red)] bg-red-50': form.errors.motivo_cierre, 'border-[var(--nord4)] bg-[var(--surface)]': !form.errors.motivo_cierre}"
                                        :disabled="isSubmitting"
                                        placeholder="Describa la justificación (ej. alta médica, traslado, finalización de tratamiento)..."
                                    ></textarea>
                                    <p v-if="form.errors.motivo_cierre" class="mt-1.5 text-xs text-[var(--aurora-red)]">
                                        {{ form.errors.motivo_cierre }}
                                    </p>
                                </div>
                                <div class="flex items-start mt-4">
                                    <div class="flex items-center h-5">
                                        <input
                                            id="confirmacion_irreversible"
                                            v-model="form.confirmacion_irreversible"
                                            type="checkbox"
                                            class="w-4 h-4 text-[var(--aurora-red)] bg-[var(--surface)] border-[var(--nord4)] rounded focus:ring-[var(--aurora-red)] focus:ring-2"
                                            :class="{'border-[var(--aurora-red)]': form.errors.confirmacion_irreversible}"
                                        >
                                    </div>
                                    <label for="confirmacion_irreversible" class="ml-2 text-sm font-medium text-[var(--nord0)]">
                                        Entiendo que el cierre de este expediente es permanente e irreversible <span class="text-[var(--aurora-red)]">*</span>
                                    </label>
                                </div>
                                <p v-if="form.errors.confirmacion_irreversible" class="mt-1.5 text-xs text-[var(--aurora-red)]">
                                    {{ form.errors.confirmacion_irreversible }}
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="bg-[var(--surface-header)] px-4 py-3 sm:px-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 border-t border-[var(--nord4)]">
                    <button
                        type="button"
                        @click="close"
                        class="w-full sm:w-auto inline-flex justify-center items-center rounded-lg border border-[var(--nord4)] shadow-sm px-4 py-2 bg-[var(--surface)] text-sm font-medium text-[var(--nord1)] hover:bg-[var(--surface-subtle)] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--nord4)] transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="submit"
                        :disabled="isSubmitting || !form.motivo_cierre || !form.resultado_final || !form.confirmacion_irreversible"
                        class="w-full sm:w-auto inline-flex justify-center items-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-[var(--aurora-red)] text-sm font-medium text-white hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--aurora-red)] disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                    >
                        <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Confirmar Cierre
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
