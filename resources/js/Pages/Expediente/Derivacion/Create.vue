<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    paciente: {
        type: Object,
        required: true
    },
    areas: {
        type: Array,
        required: true
    }
});

const form = useForm({
    area_id: ''
});

const isSubmitting = ref(false);

const submit = () => {
    isSubmitting.value = true;
    form.post(route('pacientes.derivar.store', props.paciente.codigo), {
        onFinish: () => { isSubmitting.value = false; }
    });
};
</script>

<template>
    <Head title="Derivar Paciente" />

    <div class="min-h-screen bg-nord-0 py-12 px-4 sm:px-6 lg:px-8 text-nord-4">
        <div class="max-w-3xl mx-auto">
            <!-- Header Section -->
            <div class="bg-nord-1 rounded-t-xl p-8 border-b border-nord-2 shadow-lg">
                <h1 class="text-3xl font-bold text-nord-8 tracking-tight">Derivar Paciente</h1>
                <p class="mt-2 text-nord-4 opacity-80">
                    Asigne el paciente a una nueva área clínica para iniciar su tratamiento.
                </p>
            </div>

            <!-- Form Section -->
            <div class="bg-nord-1 rounded-b-xl shadow-lg p-8">
                <!-- Patient Info Card -->
                <div class="bg-nord-2 rounded-lg p-6 mb-8 border border-nord-3 shadow-inner">
                    <h2 class="text-sm font-semibold text-nord-9 uppercase tracking-wider mb-4">Información del Paciente</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-nord-4 opacity-70">Nombre Completo</p>
                            <p class="text-lg font-medium text-nord-6">{{ paciente.nombre_completo }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-nord-4 opacity-70">Carnet</p>
                            <p class="text-lg font-medium text-nord-6">{{ paciente.carnet }}</p>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Area Selection -->
                    <div>
                        <label for="area_id" class="block text-sm font-medium text-nord-4 mb-2">
                            Área Clínica Destino
                        </label>
                        <div class="relative">
                            <select
                                id="area_id"
                                v-model="form.area_id"
                                :class="{'border-nord-11': form.errors.area_id, 'border-nord-3': !form.errors.area_id}"
                                class="block w-full bg-nord-0 border text-nord-4 rounded-lg px-4 py-3 focus:ring-2 focus:ring-nord-8 focus:border-nord-8 transition-colors duration-200 appearance-none cursor-pointer"
                                :disabled="isSubmitting"
                            >
                                <option value="" disabled selected>Seleccione un área...</option>
                                <option v-for="area in areas" :key="area.id" :value="area.id">
                                    {{ area.nombre }}
                                </option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-nord-4">
                                <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Error Message -->
                        <p v-if="form.errors.area_id" class="mt-2 text-sm text-nord-11 animate-pulse">
                            {{ form.errors.area_id }}
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-4 border-t border-nord-2 mt-8">
                        <a :href="route('pacientes.index')" class="px-6 py-2.5 text-sm font-medium text-nord-4 hover:text-nord-6 transition-colors duration-200">
                            Cancelar
                        </a>
                        <button
                            type="submit"
                            :disabled="isSubmitting || !form.area_id"
                            class="inline-flex justify-center px-6 py-2.5 text-sm font-medium rounded-lg text-nord-0 bg-nord-8 hover:bg-nord-9 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-nord-8 focus:ring-offset-nord-1 transition-all duration-200 shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed transform hover:-translate-y-0.5"
                        >
                            <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-nord-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isSubmitting ? 'Derivando...' : 'Confirmar Derivación' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
