<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import FieldTooltip from '@/Components/UI/FieldTooltip.vue';
import VueDatePicker from '@vuepic/vue-datepicker';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    facultades: { type: Array, default: () => [] }
});

const currentStep = ref(1);
const totalSteps = 3;
const step1Touched = ref(false);
const step2Touched = ref(false);

const form = useForm({
    carnet: '', nombre_completo: '', direccion: '',
    carrera_id: '', sexo: '', estado_civil: '',
    fecha_nacimiento: '', profesion_ocupacion: '',
    fecha_primera_consulta: '', referido_por: '', llevado_por: '',
    motivo_consulta: '',
    padre_nombre: '', padre_telefono: '',
    madre_nombre: '', madre_telefono: '',
    responsable_parentesco: '', responsable_nombre: '',
    responsable_telefono: '', responsable_direccion: '',
});

const stepTitles = ['Datos del Estudiante', 'Familiares y Responsable', 'Revisar y Guardar'];

// Qué campos del step 1 faltan
const step1Missing = computed(() => {
    const missing = [];
    if (!form.carnet)            missing.push({ field: 'carnet',           label: 'Carnet' });
    if (!form.nombre_completo)   missing.push({ field: 'nombre_completo',  label: 'Nombre Completo' });
    if (!form.direccion)         missing.push({ field: 'direccion',        label: 'Dirección' });
    if (!form.carrera_id)        missing.push({ field: 'carrera_id',       label: 'Carrera' });
    if (!form.sexo)              missing.push({ field: 'sexo',             label: 'Sexo' });
    if (!form.estado_civil)      missing.push({ field: 'estado_civil',     label: 'Estado Civil' });
    if (!form.fecha_nacimiento)  missing.push({ field: 'fecha_nacimiento', label: 'Fecha de Nacimiento' });
    if (!form.motivo_consulta)   missing.push({ field: 'motivo_consulta',  label: 'Motivo de Consulta' });
    return missing;
});

// Qué campos del step 2 faltan
const step2Missing = computed(() => {
    const missing = [];
    if (!form.responsable_parentesco) missing.push({ field: 'responsable_parentesco', label: 'Parentesco del Responsable' });
    if (form.responsable_parentesco === 'Otro' && !form.responsable_nombre)
        missing.push({ field: 'responsable_nombre', label: 'Nombre del Responsable' });
    if (!form.responsable_telefono) missing.push({ field: 'responsable_telefono', label: 'Teléfono del Responsable' });
    if (!form.responsable_direccion) missing.push({ field: 'responsable_direccion', label: 'Dirección del Responsable' });
    return missing;
});

const step1Valid = computed(() => step1Missing.value.length === 0);
const step2Valid = computed(() => step2Missing.value.length === 0);
const errorKeys = computed(() => Object.keys(form.errors));

function maskCarnet(e) {
    let val = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    const letters = val.replace(/[^A-Z]/g, '').slice(0, 2);
    const digits = val.replace(/[^0-9]/g, '').slice(0, 5);
    form.carnet = letters + digits;
    form.clearErrors('carnet');
}

function maskPhone(field) {
    return (e) => {
        form[field] = e.target.value.replace(/\D/g, '').slice(0, 8);
        form.clearErrors(field);
    };
}

watch(() => form.errors, () => {
    if (Object.keys(form.errors).length > 0) currentStep.value = 1;
}, { deep: true });

watch(() => form.responsable_parentesco, (val) => {
    if (val === 'Padre' && form.padre_telefono) {
        form.responsable_telefono = form.padre_telefono;
        form.responsable_nombre = form.padre_nombre;
    } else if (val === 'Madre' && form.madre_telefono) {
        form.responsable_telefono = form.madre_telefono;
        form.responsable_nombre = form.madre_nombre;
    }
});

watch(() => form.padre_telefono, (val) => {
    if (form.responsable_parentesco === 'Padre') form.responsable_telefono = val;
});
watch(() => form.madre_telefono, (val) => {
    if (form.responsable_parentesco === 'Madre') form.responsable_telefono = val;
});

function focusField(name) {
    currentStep.value = name.startsWith('responsable_') || name.startsWith('padre_') || name.startsWith('madre_') ? 2 : 1;
    nextTick(() => {
        const el = document.getElementById('field-' + name);
        if (el) { el.focus(); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    });
}

function nextStep() {
    if (currentStep.value === 1) {
        step1Touched.value = true;
        if (!step1Valid.value) {
            // Scroll al primer campo faltante
            nextTick(() => {
                const firstMissing = step1Missing.value[0];
                if (firstMissing) {
                    const el = document.getElementById('field-' + firstMissing.field);
                    if (el) { el.focus(); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                }
            });
            return;
        }
    }
    if (currentStep.value === 2) {
        step2Touched.value = true;
        if (!step2Valid.value) {
            nextTick(() => {
                const firstMissing = step2Missing.value[0];
                if (firstMissing) {
                    const el = document.getElementById('field-' + firstMissing.field);
                    if (el) { el.focus(); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                }
            });
            return;
        }
    }
    if (currentStep.value < totalSteps) currentStep.value++;
}
function prevStep() { if (currentStep.value > 1) currentStep.value--; }

function submit() {
    form.post('/pacientes', {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            if (form.hasErrors) currentStep.value = 1;
        },
    });
}
</script>

<template>
    <Head title="Registrar Paciente" />

    <div class="max-w-4xl mx-auto space-y-6 pb-10">
        <Breadcrumbs :items="[
            { label: 'Panel Clínico', href: '/dashboard' },
            { label: 'Pacientes', href: '/pacientes' },
            { label: 'Registrar Nuevo Paciente' },
        ]" />

        <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] stepper-card">
            <!-- Stepper Header -->
            <div class="stepper-bar">
                <div class="stepper-track">
                    <!-- Línea de fondo (gris) -->
                    <div class="stepper-line-bg"></div>
                    <!-- Línea de progreso (coloreada) -->
                    <div class="stepper-line-progress" :style="{ width: ((currentStep - 1) / (totalSteps - 1) * 100) + '%' }"></div>
                    <!-- Círculos de los pasos -->
                    <div class="stepper-steps">
                        <div v-for="step in totalSteps" :key="step" class="stepper-step">
                            <div :class="[
                                'stepper-circle',
                                step < currentStep ? 'stepper-done' :
                                step === currentStep ? 'stepper-active' :
                                'stepper-pending'
                            ]">
                                <svg v-if="step < currentStep" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                <span v-else class="stepper-num">{{ step }}</span>
                            </div>
                            <div class="stepper-label" :class="step === currentStep ? 'stepper-label-active' : step < currentStep ? 'stepper-label-done' : 'stepper-label-pending'">
                                {{ stepTitles[step - 1] }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="card-form">
                <!-- Validation Error Banner (Server) -->
                <div v-if="errorKeys.length > 0" class="mb-6 px-4 py-3 rounded-xl border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/5">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="h-5 w-5 shrink-0 text-[var(--aurora-red)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span class="text-[13px] font-semibold text-[var(--aurora-red)]">Corrige {{ errorKeys.length }} error(es):</span>
                    </div>
                    <ul class="list-disc pl-9 space-y-0.5">
                        <li v-for="key in errorKeys" :key="key" class="text-[12px] text-[var(--aurora-red)]">
                            <button type="button" class="hover:underline text-left cursor-pointer" @click="focusField(key)">{{ form.errors[key] }}</button>
                        </li>
                    </ul>
                </div>

                <!-- Validation Banner (Cliente - Step 1) -->
                <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                    <div v-if="step1Touched && !step1Valid && currentStep === 1"
                        class="mb-5 px-4 py-3 rounded-xl border border-[var(--aurora-orange)]/40 bg-[var(--aurora-orange)]/6 flex gap-3">
                        <svg class="h-5 w-5 shrink-0 text-[var(--aurora-orange)] mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <div>
                            <p class="text-[13px] font-semibold text-[var(--aurora-orange)] mb-1">Completa los campos obligatorios:</p>
                            <ul class="space-y-0.5">
                                <li v-for="m in step1Missing" :key="m.field" class="text-[12px] text-[var(--aurora-orange)]">
                                    <button type="button" class="hover:underline text-left" @click="focusField(m.field)">
                                        → {{ m.label }}
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </Transition>

                <!-- Validation Banner (Cliente - Step 2) -->
                <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                    <div v-if="step2Touched && !step2Valid && currentStep === 2"
                        class="mb-5 px-4 py-3 rounded-xl border border-[var(--aurora-orange)]/40 bg-[var(--aurora-orange)]/6 flex gap-3">
                        <svg class="h-5 w-5 shrink-0 text-[var(--aurora-orange)] mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <div>
                            <p class="text-[13px] font-semibold text-[var(--aurora-orange)] mb-1">Completa los campos obligatorios:</p>
                            <ul class="space-y-0.5">
                                <li v-for="m in step2Missing" :key="m.field" class="text-[12px] text-[var(--aurora-orange)]">
                                    <button type="button" class="hover:underline text-left" @click="focusField(m.field)">
                                        → {{ m.label }}
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </Transition>

                <!-- ===== STEP 1 ===== -->
                <div v-show="currentStep === 1" class="animate-fade-in">
                    <div class="mb-5">
                        <h3 class="text-[14px] font-semibold text-[var(--nord0)] flex items-center gap-2">
                            <svg class="h-4 w-4 text-[var(--nord10)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            Información del Estudiante
                        </h3>
                        <p class="text-[12px] text-[var(--nord3)] mt-1">Todos los marcados con <span class="field-required" title="Campo obligatorio">*</span> son obligatorios</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                        <div>
                            <label for="field-carnet" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Carnet <span class="field-required" title="Campo obligatorio">*</span> <FieldTooltip text="2 letras mayúsculas + 5 dígitos. Ejemplo: AB12345" /></label>
                            <input id="field-carnet" v-model="form.carnet" type="text" @input="maskCarnet" maxlength="7" placeholder="Ej: AB12345" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] font-mono uppercase tracking-wider text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.carnet ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            <p v-if="form.errors.carnet" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.carnet }}</p>
                        </div>

                        <div>
                            <label for="field-nombre_completo" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre Completo <span class="field-required" title="Campo obligatorio">*</span> <FieldTooltip text="Nombre tal como aparece en el carnet estudiantil o DUI" /></label>
                            <input id="field-nombre_completo" v-model="form.nombre_completo" type="text" maxlength="255" placeholder="Ej: María José López García" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.nombre_completo ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            <p v-if="form.errors.nombre_completo" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.nombre_completo }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label for="field-direccion" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Dirección <span class="field-required" title="Campo obligatorio">*</span> <FieldTooltip text="Dirección de residencia actual del estudiante. Incluye colonia, calle, número y municipio" /></label>
                            <textarea id="field-direccion" v-model="form.direccion" rows="2" maxlength="500" placeholder="Ej: Colonia El Roble, Calle Principal #42, San Salvador" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all resize-none" :class="form.errors.direccion ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            <p v-if="form.errors.direccion" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.direccion }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label for="field-motivo_consulta" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Motivo de Consulta <span class="field-required" title="Campo obligatorio">*</span> <FieldTooltip text="Describe brevemente el problema, síntoma o situación por la cual el estudiante busca ayuda" /></label>
                            <textarea id="field-motivo_consulta" v-model="form.motivo_consulta" rows="3" placeholder="Describa el motivo por el cual el estudiante acude a consulta" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all resize-none" :class="form.errors.motivo_consulta ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            <p v-if="form.errors.motivo_consulta" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.motivo_consulta }}</p>
                        </div>

                        <div>
                            <label for="field-carrera_id" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Carrera <span class="field-required" title="Campo obligatorio">*</span> <FieldTooltip text="Selecciona la facultad y carrera que el estudiante cursa actualmente" /></label>
                            <select id="field-carrera_id" v-model="form.carrera_id" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.carrera_id ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required>
                                <option value="" disabled>Seleccione una carrera</option>
                                <optgroup v-for="f in facultades" :key="f.id" :label="f.nombre">
                                    <option v-for="c in f.carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                                </optgroup>
                            </select>
                            <p v-if="form.errors.carrera_id" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.carrera_id }}</p>
                        </div>

                        <div>
                            <label for="field-fecha_nacimiento" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Fecha Nacimiento <span class="field-required" title="Campo obligatorio">*</span> <FieldTooltip text="Fecha real de nacimiento. No puede ser una fecha futura" /></label>
                            <VueDatePicker v-model="form.fecha_nacimiento" :format="'dd/MM/yyyy'" model-type="yyyy-MM-dd" :enable-time-picker="false" auto-apply :max-date="new Date()" required>
                                <template #dp-input="{ value }">
                                    <input id="field-fecha_nacimiento" type="text" :value="value" readonly class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all cursor-pointer" :class="form.errors.fecha_nacimiento ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" placeholder="Seleccionar fecha" />
                                </template>
                            </VueDatePicker>
                            <p v-if="form.errors.fecha_nacimiento" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.fecha_nacimiento }}</p>
                        </div>

                        <div>
                            <label for="field-sexo" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Sexo <span class="field-required" title="Campo obligatorio">*</span> <FieldTooltip text="Sexo biológico según documento de identidad oficial" /></label>
                            <select id="field-sexo" v-model="form.sexo" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.sexo ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required>
                                <option value="" disabled>Seleccionar</option>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                                <option value="Otro">Otro</option>
                            </select>
                            <p v-if="form.errors.sexo" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.sexo }}</p>
                        </div>

                        <div>
                            <label for="field-estado_civil" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Estado Civil <span class="field-required" title="Campo obligatorio">*</span> <FieldTooltip text="Estado civil actual del estudiante al momento del registro" /></label>
                            <select id="field-estado_civil" v-model="form.estado_civil" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.estado_civil ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required>
                                <option value="" disabled>Seleccionar</option>
                                <option value="Soltero">Soltero</option>
                                <option value="Casado">Casado</option>
                                <option value="Divorciado">Divorciado</option>
                                <option value="Viudo">Viudo</option>
                                <option value="Unión Libre">Unión Libre</option>
                            </select>
                            <p v-if="form.errors.estado_civil" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.estado_civil }}</p>
                        </div>

                        <div>
                            <label for="field-profesion_ocupacion" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Profesión / Ocupación <FieldTooltip text="Si el estudiante trabaja además de estudiar, indica su ocupación actual" /></label>
                            <input id="field-profesion_ocupacion" v-model="form.profesion_ocupacion" type="text" maxlength="255" placeholder="Ej: Estudiante, Ingeniero, Docente" class="w-full bg-[var(--surface)] border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[var(--frost3)]/20 outline-none transition-all" />
                        </div>

                        <div>
                            <label for="field-fecha_primera_consulta" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Fecha Primera Consulta <FieldTooltip text="Fecha en la que el estudiante acudió a consulta por primera vez. No puede ser futura" /></label>
                            <VueDatePicker v-model="form.fecha_primera_consulta" :format="'dd/MM/yyyy'" model-type="yyyy-MM-dd" :enable-time-picker="false" auto-apply :max-date="new Date()">
                                <template #dp-input="{ value }">
                                    <input id="field-fecha_primera_consulta" type="text" :value="value" readonly class="w-full bg-[var(--surface)] border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[var(--frost3)]/20 outline-none transition-all cursor-pointer" placeholder="Seleccionar fecha" />
                                </template>
                            </VueDatePicker>
                        </div>

                        <div>
                            <label for="field-referido_por" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Referido Por <FieldTooltip text="Nombre de la persona, médico o institución que refirió al estudiante a consulta" /></label>
                            <input id="field-referido_por" v-model="form.referido_por" type="text" maxlength="255" placeholder="Ej: Dr. Carlos Méndez - Clínica Universitaria" class="w-full bg-[var(--surface)] border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[var(--frost3)]/20 outline-none transition-all" />
                        </div>

                        <div>
                            <label for="field-llevado_por" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Acompañado Por <FieldTooltip text="Persona (familiar, amigo, tutor) que acompañó al estudiante a su primera consulta" /></label>
                            <input id="field-llevado_por" v-model="form.llevado_por" type="text" maxlength="255" placeholder="Ej: Familiar o amigo que acompañó al estudiante" class="w-full bg-[var(--surface)] border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[var(--frost3)]/20 outline-none transition-all" />
                        </div>
                    </div>
                </div>

                <!-- ===== STEP 2 ===== -->
                <div v-show="currentStep === 2" class="animate-fade-in">
                    <div class="mb-5">
                        <h3 class="text-[14px] font-semibold text-[var(--nord0)] flex items-center gap-2">
                            <svg class="h-4 w-4 text-[var(--aurora-orange)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            Familiares y Contactos
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4 mb-6">
                        <!-- Padre -->
                        <div class="space-y-4 p-4 rounded-lg border border-[var(--nord4)] bg-[var(--surface-subtle)]" :class="{ 'ring-2 ring-[var(--frost4)]/30': form.responsable_parentesco === 'Padre' }">
                            <h4 class="text-[12px] font-semibold text-[var(--nord3)] uppercase tracking-wider flex items-center gap-2">
                                Datos del Padre
                                <span v-if="form.responsable_parentesco === 'Padre'" class="text-[10px] bg-[var(--frost4)]/10 text-[var(--frost4)] px-2 py-0.5 rounded-full font-normal normal-case">Responsable</span>
                            </h4>
                            <div>
                                <label for="field-padre_nombre" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre <FieldTooltip text="Nombre completo del padre del estudiante" /></label>
                                <input id="field-padre_nombre" v-model="form.padre_nombre" type="text" maxlength="255" placeholder="Ej: José Antonio López" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.padre_nombre ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" />
                            </div>
                            <div>
                                <label for="field-padre_telefono" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Teléfono <FieldTooltip text="8 dígitos numéricos, sin guiones. Ej: 70001234" /></label>
                                <input id="field-padre_telefono" v-model="form.padre_telefono" type="tel" @input="maskPhone('padre_telefono')" maxlength="8" placeholder="Ej: 70001234" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] font-mono text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.padre_telefono ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" />
                            </div>
                        </div>

                        <!-- Madre -->
                        <div class="space-y-4 p-4 rounded-lg border border-[var(--nord4)] bg-[var(--surface-subtle)]" :class="{ 'ring-2 ring-[var(--frost4)]/30': form.responsable_parentesco === 'Madre' }">
                            <h4 class="text-[12px] font-semibold text-[var(--nord3)] uppercase tracking-wider flex items-center gap-2">
                                Datos de la Madre
                                <span v-if="form.responsable_parentesco === 'Madre'" class="text-[10px] bg-[var(--frost4)]/10 text-[var(--frost4)] px-2 py-0.5 rounded-full font-normal normal-case">Responsable</span>
                            </h4>
                            <div>
                                <label for="field-madre_nombre" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre <FieldTooltip text="Nombre completo de la madre del estudiante" /></label>
                                <input id="field-madre_nombre" v-model="form.madre_nombre" type="text" maxlength="255" placeholder="Ej: María Elena García de López" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.madre_nombre ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" />
                            </div>
                            <div>
                                <label for="field-madre_telefono" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Teléfono <FieldTooltip text="8 dígitos numéricos, sin guiones. Ej: 70005678" /></label>
                                <input id="field-madre_telefono" v-model="form.madre_telefono" type="tel" @input="maskPhone('madre_telefono')" maxlength="8" placeholder="Ej: 70005678" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] font-mono text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.madre_telefono ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" />
                            </div>
                        </div>
                    </div>

                    <div class="p-4 rounded-lg border border-[var(--nord4)]">
                        <h4 class="text-[12px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-4 flex items-center gap-2">
                            Datos del Responsable Legal
                            <span class="text-[10px] bg-[var(--aurora-red)]/10 text-[var(--aurora-red)] px-2 py-0.5 rounded-full font-normal normal-case">Obligatorio</span>
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                            <div>
                                <label for="field-responsable_parentesco" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Parentesco <span class="field-required" title="Campo obligatorio">*</span> <FieldTooltip text="Relación del responsable legal con el estudiante: padre, madre u otro tutor" /></label>
                                <select id="field-responsable_parentesco" v-model="form.responsable_parentesco" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.responsable_parentesco ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required>
                                    <option value="" disabled>Seleccionar</option>
                                    <option value="Padre">Padre</option>
                                    <option value="Madre">Madre</option>
                                    <option value="Otro">Otro Responsable / Tutor</option>
                                </select>
                            </div>

                            <div v-if="form.responsable_parentesco === 'Otro'">
                                <label for="field-responsable_nombre" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre <span class="field-required" title="Campo obligatorio">*</span> <FieldTooltip text="Nombre completo de la persona responsable del estudiante. Solo si parentesco es 'Otro'" /></label>
                                <input id="field-responsable_nombre" v-model="form.responsable_nombre" type="text" maxlength="255" placeholder="Ej: Ana Patricia Torres" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.responsable_nombre ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            </div>

                            <div class="md:col-span-2" v-if="form.responsable_parentesco && form.responsable_parentesco !== 'Otro'">
                                <div class="text-[11px] text-[var(--frost4)] flex items-center gap-1.5 bg-[var(--frost4)]/5 px-3 py-2 rounded-lg mb-3">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span>El teléfono del {{ form.responsable_parentesco === 'Padre' ? 'padre' : 'madre' }} se usará como contacto del responsable</span>
                                </div>
                            </div>

                            <div v-if="form.responsable_parentesco === 'Otro'">
                                <label for="field-responsable_telefono" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Teléfono <span class="field-required" title="Campo obligatorio">*</span> <FieldTooltip text="8 dígitos numéricos, sin guiones. Es el teléfono principal de contacto de emergencia" /></label>
                                <input id="field-responsable_telefono" v-model="form.responsable_telefono" type="tel" @input="maskPhone('responsable_telefono')" maxlength="8" placeholder="Ej: 70009012" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] font-mono text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.responsable_telefono ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            </div>

                            <div class="md:col-span-2">
                                <label for="field-responsable_direccion" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Dirección Completa <span class="field-required" title="Campo obligatorio">*</span> <FieldTooltip text="Dirección de residencia del responsable legal. Puede ser la misma del estudiante" /></label>
                                <textarea id="field-responsable_direccion" v-model="form.responsable_direccion" rows="2" maxlength="500" placeholder="Ej: Misma dirección del estudiante o una diferente" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all resize-none" :class="form.errors.responsable_direccion ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== STEP 3 ===== -->
                <div v-show="currentStep === 3" class="animate-fade-in">
                    <div class="mb-5">
                        <h3 class="text-[14px] font-semibold text-[var(--nord0)] flex items-center gap-2">
                            <svg class="h-4 w-4 text-[var(--aurora-green)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Revisar Expediente
                        </h3>
                        <p class="text-[12px] text-[var(--nord3)] mt-1">Verifica que toda la información sea correcta antes de guardar. Los datos serán cifrados.</p>
                    </div>

                    <div class="space-y-4">
                        <div class="bg-[var(--surface-subtle)] rounded-lg border border-[var(--nord4)] p-4">
                            <h4 class="text-[12px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-3">Datos del Estudiante</h4>
                            <div class="grid grid-cols-2 gap-3 text-[13px]">
                                <div><span class="text-[var(--nord3)]">Carnet:</span> <strong class="text-[var(--nord0)]">{{ form.carnet || '—' }}</strong></div>
                                <div><span class="text-[var(--nord3)]">Nombre:</span> <strong class="text-[var(--nord0)]">{{ form.nombre_completo || '—' }}</strong></div>
                                <div class="col-span-2"><span class="text-[var(--nord3)]">Dirección:</span> <strong class="text-[var(--nord0)]">{{ form.direccion || '—' }}</strong></div>
                                <div><span class="text-[var(--nord3)]">Sexo:</span> <strong class="text-[var(--nord0)]">{{ form.sexo || '—' }}</strong></div>
                                <div><span class="text-[var(--nord3)]">Estado Civil:</span> <strong class="text-[var(--nord0)]">{{ form.estado_civil || '—' }}</strong></div>
                                <div><span class="text-[var(--nord3)]">Fecha Nac.:</span> <strong class="text-[var(--nord0)]">{{ form.fecha_nacimiento || '—' }}</strong></div>
                                <div v-if="form.profesion_ocupacion"><span class="text-[var(--nord3)]">Ocupación:</span> <strong class="text-[var(--nord0)]">{{ form.profesion_ocupacion }}</strong></div>
                                <div class="col-span-2" v-if="form.motivo_consulta"><span class="text-[var(--nord3)]">Motivo:</span> <strong class="text-[var(--nord0)] block mt-1">{{ form.motivo_consulta }}</strong></div>
                            </div>
                        </div>

                        <div class="bg-[var(--surface-subtle)] rounded-lg border border-[var(--nord4)] p-4">
                            <h4 class="text-[12px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-3">Responsable Legal</h4>
                            <div class="grid grid-cols-2 gap-3 text-[13px]">
                                <div><span class="text-[var(--nord3)]">Parentesco:</span> <strong class="text-[var(--nord0)]">{{ form.responsable_parentesco || '—' }}</strong></div>
                                <div v-if="form.responsable_nombre"><span class="text-[var(--nord3)]">Nombre:</span> <strong class="text-[var(--nord0)]">{{ form.responsable_nombre }}</strong></div>
                                <div><span class="text-[var(--nord3)]">Teléfono:</span> <strong class="text-[var(--nord0)]">{{ form.responsable_telefono || '—' }}</strong></div>
                                <div class="col-span-2"><span class="text-[var(--nord3)]">Dirección:</span> <strong class="text-[var(--nord0)]">{{ form.responsable_direccion || '—' }}</strong></div>
                            </div>
                        </div>

                        <div v-if="form.padre_nombre || form.madre_nombre" class="bg-[var(--surface-subtle)] rounded-lg border border-[var(--nord4)] p-4">
                            <h4 class="text-[12px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-3">Padres</h4>
                            <div class="grid grid-cols-2 gap-3 text-[13px]">
                                <div v-if="form.padre_nombre"><span class="text-[var(--nord3)]">Padre:</span> <strong class="text-[var(--nord0)]">{{ form.padre_nombre }}</strong></div>
                                <div v-if="form.padre_telefono"><span class="text-[var(--nord3)]">Tel. Padre:</span> <strong class="text-[var(--nord0)]">{{ form.padre_telefono }}</strong></div>
                                <div v-if="form.madre_nombre"><span class="text-[var(--nord3)]">Madre:</span> <strong class="text-[var(--nord0)]">{{ form.madre_nombre }}</strong></div>
                                <div v-if="form.madre_telefono"><span class="text-[var(--nord3)]">Tel. Madre:</span> <strong class="text-[var(--nord0)]">{{ form.madre_telefono }}</strong></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex justify-between items-center pt-6 mt-6 border-t border-[var(--nord4)]">
                    <button v-if="currentStep > 1" type="button" @click="prevStep"
                        class="flex items-center gap-1.5 px-4 py-2.5 text-[13px] font-medium text-[var(--nord3)] hover:text-[var(--nord0)] border border-[var(--nord4)] hover:border-[var(--nord3)] rounded-xl transition-all hover:-translate-y-0.5 hover:shadow-sm bg-[var(--surface)]">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        Anterior
                    </button>
                    <div v-else />

                    <!-- Indicador de campos faltantes al lado del botón -->
                    <div class="flex items-center gap-3">
                        <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100">
                            <span v-if="(step1Touched && !step1Valid && currentStep === 1) || (step2Touched && !step2Valid && currentStep === 2)"
                                class="text-[11px] font-medium text-[var(--aurora-orange)] flex items-center gap-1">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ currentStep === 1 ? step1Missing.length : step2Missing.length }} campo(s) pendiente(s)
                            </span>
                        </Transition>

                        <button v-if="currentStep < totalSteps" type="button" @click="nextStep"
                            class="group relative inline-flex items-center gap-1.5 overflow-hidden px-5 py-2.5 text-[13px] font-semibold rounded-xl text-white shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0"
                            :style="step1Valid || currentStep !== 1 ? 'background: linear-gradient(135deg, var(--nord10) 0%, var(--nord9) 100%);' : 'background: linear-gradient(135deg, var(--nord10) 0%, var(--nord9) 100%);'">
                            <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/15 to-transparent group-hover:translate-x-full transition-transform duration-500"></span>
                            Siguiente
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>

                        <button v-else type="submit" :disabled="form.processing"
                            class="group relative inline-flex items-center gap-2 overflow-hidden px-6 py-2.5 text-[13px] font-semibold rounded-xl text-white shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-60 disabled:cursor-not-allowed disabled:transform-none"
                            style="background: linear-gradient(135deg, var(--aurora-green) 0%, #7DAE63 100%);">
                            <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/15 to-transparent group-hover:translate-x-full transition-transform duration-500"></span>
                            <svg v-if="form.processing" class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            {{ form.processing ? 'Guardando...' : 'Guardar y Cifrar Expediente' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.stepper-card {
    padding: 0;
    overflow: hidden;
}

/* ── Stepper Bar ── */
.stepper-bar {
    padding: 1.75rem 2rem 1.5rem;
    background: linear-gradient(135deg, color-mix(in srgb, var(--nord6) 60%, var(--nord5)) 0%, var(--nord6) 100%);
    border-bottom: 1px solid var(--nord4);
}

.stepper-track {
    position: relative;
    display: flex;
    align-items: flex-start;
}

/* Línea gris de fondo */
.stepper-line-bg {
    position: absolute;
    top: 18px;
    left: 18px;
    right: 18px;
    height: 3px;
    background: var(--nord4);
    border-radius: 999px;
    z-index: 0;
}

/* Línea de progreso coloreada */
.stepper-line-progress {
    position: absolute;
    top: 18px;
    left: 18px;
    height: 3px;
    background: linear-gradient(90deg, var(--aurora-green), var(--nord10));
    border-radius: 999px;
    z-index: 1;
    transition: width 0.45s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Contenedor de los pasos */
.stepper-steps {
    position: relative;
    z-index: 2;
    display: flex;
    width: 100%;
    justify-content: space-between;
}

.stepper-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.6rem;
    flex: 1;
}

/* Círculos */
.stepper-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.10);
}

.stepper-done {
    background: var(--aurora-green);
    color: #fff;
    box-shadow: 0 2px 12px color-mix(in srgb, var(--aurora-green) 35%, transparent);
}

.stepper-active {
    background: var(--nord10);
    color: #fff;
    box-shadow: 0 0 0 5px color-mix(in srgb, var(--nord10) 18%, transparent),
                0 2px 12px color-mix(in srgb, var(--nord10) 35%, transparent);
    transform: scale(1.08);
}

.stepper-pending {
    background: var(--nord5);
    color: var(--nord3);
    border: 2px solid var(--nord4);
}

.stepper-num {
    line-height: 1;
}

/* Etiquetas */
.stepper-label {
    font-size: 11px;
    font-weight: 500;
    white-space: nowrap;
    text-align: center;
    letter-spacing: 0.01em;
    transition: color 0.3s;
}

.stepper-label-active {
    color: var(--nord0);
    font-weight: 600;
}

.stepper-label-done {
    color: var(--aurora-green);
}

.stepper-label-pending {
    color: var(--nord3);
    opacity: 0.7;
}

/* Formulario */
.card-form {
    padding: 1.75rem;
}
</style>
