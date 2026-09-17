<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    paciente: {
        type: Object,
        default: null
    },
    areas: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close']);

const form = useForm({
    area_id: '',
    motivo_derivacion: '',
});

const isSubmitting = ref(false);

watch(() => props.show, (newVal) => {
    if (newVal) {
        form.reset();
        form.area_id = '';
        form.motivo_derivacion = '';
        form.clearErrors();
        isSubmitting.value = false;
    }
});

const close = () => {
    emit('close');
};

const areaSeleccionada = () => {
    if (!form.area_id) return null;
    return props.areas.find((area) => String(area.id) === String(form.area_id)) ?? null;
};

const submit = () => {
    if (!props.paciente) return;
    
    isSubmitting.value = true;
    form.post(`/pacientes/${props.paciente.codigo}/derivar`, {
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
            <!-- Background overlay (fixed clicking issues and white blur) -->
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="close"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div class="relative z-10 inline-block align-bottom bg-[var(--surface)] rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-[var(--nord4)]">
                <div class="bg-[var(--surface)] px-4 pt-5 pb-4 sm:p-6 sm:pb-4 relative">
                    
                    <button @click="close" class="absolute top-4 right-4 text-[var(--nord3)] hover:text-[var(--nord0)] transition-colors">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-[var(--nord8)]/10 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-[var(--nord8)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-semibold text-[var(--nord0)]" id="modal-title">
                                Derivar Paciente a Área
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-[var(--nord3)]">
                                    Seleccione el área clínica y registre el motivo para derivar a
                                    <span v-if="paciente" class="font-semibold text-[var(--nord0)]">{{ paciente.nombre_completo }}</span>.
                                </p>
                            </div>

                            <form @submit.prevent="submit" class="mt-5 space-y-4">
                                <div>
                                    <label for="area_id" class="block text-sm font-medium text-[var(--nord0)] mb-1">
                                        Área Destino <span class="text-[var(--aurora-red)]">*</span>
                                    </label>
                                    <select
                                        id="area_id"
                                        v-model="form.area_id"
                                        class="block w-full border text-[var(--nord0)] text-sm rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-[var(--nord8)] focus:border-[var(--nord8)] transition-colors duration-200"
                                        :class="{'border-[var(--aurora-red)] bg-red-50': form.errors.area_id, 'border-[var(--nord4)] bg-[var(--surface)]': !form.errors.area_id}"
                                        :disabled="isSubmitting"
                                    >
                                        <option value="" disabled>Seleccione un área...</option>
                                        <option v-for="area in areas" :key="area.id" :value="area.id">
                                            {{ area.nombre }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.area_id" class="mt-1.5 text-xs text-[var(--aurora-red)]">
                                        {{ form.errors.area_id }}
                                    </p>
                                </div>

                                <div>
                                    <label for="motivo_derivacion" class="block text-sm font-medium text-[var(--nord0)] mb-1">
                                        Motivo de derivación <span class="text-[var(--aurora-red)]">*</span>
                                    </label>
                                    <textarea
                                        id="motivo_derivacion"
                                        v-model="form.motivo_derivacion"
                                        rows="3"
                                        maxlength="1000"
                                        placeholder="Describa el motivo clínico o psicosocial de la derivación (mín. 10 caracteres)"
                                        class="block w-full border text-[var(--nord0)] text-sm rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-[var(--nord8)] focus:border-[var(--nord8)] transition-colors duration-200 resize-none"
                                        :class="{'border-[var(--aurora-red)] bg-red-50': form.errors.motivo_derivacion, 'border-[var(--nord4)] bg-[var(--surface)]': !form.errors.motivo_derivacion}"
                                        :disabled="isSubmitting"
                                    />
                                    <p v-if="form.errors.motivo_derivacion" class="mt-1.5 text-xs text-[var(--aurora-red)]">
                                        {{ form.errors.motivo_derivacion }}
                                    </p>
                                </div>

                                <div
                                    v-if="paciente && areaSeleccionada()"
                                    class="rounded-lg border border-[var(--nord4)] bg-[var(--surface-subtle)] px-3 py-2.5 text-left"
                                >
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[var(--nord3)] mb-1">
                                        Confirmación
                                    </p>
                                    <p class="text-sm text-[var(--nord0)]">
                                        Se derivará a <span class="font-semibold">{{ paciente.nombre_completo }}</span>
                                        hacia <span class="font-semibold">{{ areaSeleccionada().nombre }}</span>.
                                    </p>
                                </div>
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
                        :disabled="isSubmitting || !form.area_id || !form.motivo_derivacion"
                        class="w-full sm:w-auto inline-flex justify-center items-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-[var(--nord8)] text-sm font-medium text-white hover:bg-[var(--nord9)] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--nord8)] disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                    >
                        <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Confirmar Derivación
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
