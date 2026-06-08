<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    facultades: { type: Array, default: () => [] }
});

const currentStep = ref(1);
const totalSteps = 3;

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

const step1Valid = computed(() => form.carnet && form.nombre_completo && form.direccion && form.carrera_id && form.sexo && form.estado_civil && form.fecha_nacimiento && form.motivo_consulta);
const step2Valid = computed(() => form.responsable_parentesco && (form.responsable_parentesco !== 'Otro' || form.responsable_nombre) && form.responsable_telefono && form.responsable_direccion);
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

function focusField(name) {
    currentStep.value = name.startsWith('responsable_') || name.startsWith('padre_') || name.startsWith('madre_') ? 2 : 1;
    nextTick(() => {
        const el = document.getElementById('field-' + name);
        if (el) { el.focus(); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    });
}

function nextStep() { if (currentStep.value < totalSteps) currentStep.value++; }
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

        <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] p-6">
            <!-- Stepper Header -->
            <div class="flex items-center justify-between mb-8">
                <div v-for="step in totalSteps" :key="step" class="flex items-center flex-1" :class="{ 'opacity-50': step > currentStep }">
                    <div class="relative">
                        <div :class="[
                            'w-9 h-9 rounded-full flex items-center justify-center text-[13px] font-bold transition-all duration-300',
                            step < currentStep ? 'bg-[var(--aurora-green)] text-white' :
                            step === currentStep ? 'bg-[var(--nord10)] text-white ring-4 ring-[var(--nord10)]/10' :
                            'bg-[var(--surface-subtle)] text-[var(--nord3)]'
                        ]">
                            <svg v-if="step < currentStep" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                            <span v-else>{{ step }}</span>
                        </div>
                        <div class="mt-2 text-center absolute -left-2 right-0">
                            <p :class="['text-[11px] font-medium whitespace-nowrap', step === currentStep ? 'text-[var(--nord0)]' : 'text-[var(--nord3)]']">{{ stepTitles[step - 1] }}</p>
                        </div>
                    </div>
                    <div v-if="step < totalSteps" class="flex-1 mx-3" :class="step < currentStep ? 'bg-[var(--aurora-green)]' : 'bg-[var(--nord4)]'" style="height: 2px; margin-top: -24px;" />
                </div>
            </div>

            <form @submit.prevent="submit">
                <!-- Validation Error Banner -->
                <div v-if="errorKeys.length > 0" class="mb-6 px-4 py-3 rounded-lg border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/5">
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
                            <label for="field-carnet" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Carnet <span class="field-required" title="Campo obligatorio">*</span></label>
                            <input id="field-carnet" v-model="form.carnet" type="text" @input="maskCarnet" maxlength="7" placeholder="Ej: AB12345" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] font-mono uppercase tracking-wider text-[var(--nord0)] focus:ring-2 outline-none transition-all placeholder:text-[var(--nord3)]" :class="form.errors.carnet ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            <p v-if="form.errors.carnet" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.carnet }}</p>
                        </div>

                        <div>
                            <label for="field-nombre_completo" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre Completo <span class="field-required" title="Campo obligatorio">*</span></label>
                            <input id="field-nombre_completo" v-model="form.nombre_completo" type="text" maxlength="255" placeholder="Ej: María José López García" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all placeholder:text-[var(--nord3)]" :class="form.errors.nombre_completo ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            <p v-if="form.errors.nombre_completo" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.nombre_completo }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label for="field-direccion" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Dirección <span class="field-required" title="Campo obligatorio">*</span></label>
                            <textarea id="field-direccion" v-model="form.direccion" rows="2" maxlength="500" placeholder="Ej: Colonia El Roble, Calle Principal #42, San Salvador" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all resize-none placeholder:text-[var(--nord3)]" :class="form.errors.direccion ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            <p v-if="form.errors.direccion" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.direccion }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label for="field-motivo_consulta" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Motivo de Consulta <span class="field-required" title="Campo obligatorio">*</span></label>
                            <textarea id="field-motivo_consulta" v-model="form.motivo_consulta" rows="3" placeholder="Describa el motivo por el cual el estudiante acude a consulta" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all resize-none placeholder:text-[var(--nord3)]" :class="form.errors.motivo_consulta ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            <p v-if="form.errors.motivo_consulta" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.motivo_consulta }}</p>
                        </div>

                        <div>
                            <label for="field-carrera_id" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Carrera <span class="field-required" title="Campo obligatorio">*</span></label>
                            <select id="field-carrera_id" v-model="form.carrera_id" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.carrera_id ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required>
                                <option value="" disabled>Seleccione una carrera</option>
                                <optgroup v-for="f in facultades" :key="f.id" :label="f.nombre">
                                    <option v-for="c in f.carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                                </optgroup>
                            </select>
                            <p v-if="form.errors.carrera_id" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.carrera_id }}</p>
                        </div>

                        <div>
                            <label for="field-fecha_nacimiento" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Fecha Nacimiento <span class="field-required" title="Campo obligatorio">*</span></label>
                            <input id="field-fecha_nacimiento" v-model="form.fecha_nacimiento" type="date" :max="new Date().toISOString().split('T')[0]" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.fecha_nacimiento ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            <p v-if="form.errors.fecha_nacimiento" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.fecha_nacimiento }}</p>
                        </div>

                        <div>
                            <label for="field-sexo" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Sexo <span class="field-required" title="Campo obligatorio">*</span></label>
                            <select id="field-sexo" v-model="form.sexo" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.sexo ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required>
                                <option value="" disabled>Seleccionar</option>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                                <option value="Otro">Otro</option>
                            </select>
                            <p v-if="form.errors.sexo" class="form-field-error text-[11px] text-[var(--aurora-red)] mt-1 flex items-center gap-1"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ form.errors.sexo }}</p>
                        </div>

                        <div>
                            <label for="field-estado_civil" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Estado Civil <span class="field-required" title="Campo obligatorio">*</span></label>
                            <select id="field-estado_civil" v-model="form.estado_civil" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.estado_civil ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required>
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
                            <label for="field-profesion_ocupacion" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Profesión / Ocupación</label>
                            <input id="field-profesion_ocupacion" v-model="form.profesion_ocupacion" type="text" maxlength="255" placeholder="Ej: Estudiante, Ingeniero, Docente" class="w-full bg-white border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[var(--frost3)]/20 outline-none transition-all" />
                        </div>

                        <div>
                            <label for="field-fecha_primera_consulta" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Fecha Primera Consulta</label>
                            <input id="field-fecha_primera_consulta" v-model="form.fecha_primera_consulta" type="date" :max="new Date().toISOString().split('T')[0]" class="w-full bg-white border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[var(--frost3)]/20 outline-none transition-all" />
                        </div>

                        <div>
                            <label for="field-referido_por" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Referido Por</label>
                            <input id="field-referido_por" v-model="form.referido_por" type="text" maxlength="255" placeholder="Ej: Dr. Carlos Méndez - Clínica Universitaria" class="w-full bg-white border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[var(--frost3)]/20 outline-none transition-all placeholder:text-[var(--nord3)]" />
                        </div>

                        <div>
                            <label for="field-llevado_por" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Acompañado Por</label>
                            <input id="field-llevado_por" v-model="form.llevado_por" type="text" maxlength="255" placeholder="Ej: Familiar o amigo que acompañó al estudiante" class="w-full bg-white border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[var(--frost3)]/20 outline-none transition-all placeholder:text-[var(--nord3)]" />
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
                        <div class="space-y-4 p-4 rounded-lg border border-[var(--nord4)] bg-[var(--surface-subtle)]">
                            <h4 class="text-[12px] font-semibold text-[var(--nord3)] uppercase tracking-wider">Datos del Padre</h4>
                            <div>
                                <label for="field-padre_nombre" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre</label>
                                <input id="field-padre_nombre" v-model="form.padre_nombre" type="text" maxlength="255" placeholder="Ej: José Antonio López" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.padre_nombre ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" />
                            </div>
                            <div>
                                <label for="field-padre_telefono" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Teléfono</label>
                                <input id="field-padre_telefono" v-model="form.padre_telefono" type="tel" @input="maskPhone('padre_telefono')" maxlength="8" placeholder="Ej: 70001234" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] font-mono text-[var(--nord0)] focus:ring-2 outline-none transition-all placeholder:text-[var(--nord3)]" :class="form.errors.padre_telefono ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" />
                            </div>
                        </div>

                        <div class="space-y-4 p-4 rounded-lg border border-[var(--nord4)] bg-[var(--surface-subtle)]">
                            <h4 class="text-[12px] font-semibold text-[var(--nord3)] uppercase tracking-wider">Datos de la Madre</h4>
                            <div>
                                <label for="field-madre_nombre" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre</label>
                                <input id="field-madre_nombre" v-model="form.madre_nombre" type="text" maxlength="255" placeholder="Ej: María Elena García de López" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.madre_nombre ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" />
                            </div>
                            <div>
                                <label for="field-madre_telefono" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Teléfono</label>
                                <input id="field-madre_telefono" v-model="form.madre_telefono" type="tel" @input="maskPhone('madre_telefono')" maxlength="8" placeholder="Ej: 70005678" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] font-mono text-[var(--nord0)] focus:ring-2 outline-none transition-all placeholder:text-[var(--nord3)]" :class="form.errors.madre_telefono ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" />
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
                                <label for="field-responsable_parentesco" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Parentesco <span class="field-required" title="Campo obligatorio">*</span></label>
                                <select id="field-responsable_parentesco" v-model="form.responsable_parentesco" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.responsable_parentesco ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required>
                                    <option value="" disabled>Seleccionar</option>
                                    <option value="Padre">Padre</option>
                                    <option value="Madre">Madre</option>
                                    <option value="Otro">Otro Responsable / Tutor</option>
                                </select>
                            </div>

                            <div v-if="form.responsable_parentesco === 'Otro'">
                                <label for="field-responsable_nombre" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre <span class="field-required" title="Campo obligatorio">*</span></label>
                                <input id="field-responsable_nombre" v-model="form.responsable_nombre" type="text" maxlength="255" placeholder="Ej: Ana Patricia Torres" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all" :class="form.errors.responsable_nombre ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            </div>

                            <div :class="{ 'md:col-span-2': form.responsable_parentesco !== 'Otro' }">
                                <label for="field-responsable_telefono" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Teléfono <span class="field-required" title="Campo obligatorio">*</span></label>
                                <input id="field-responsable_telefono" v-model="form.responsable_telefono" type="tel" @input="maskPhone('responsable_telefono')" maxlength="8" placeholder="Ej: 70009012" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] font-mono text-[var(--nord0)] focus:ring-2 outline-none transition-all placeholder:text-[var(--nord3)]" :class="form.errors.responsable_telefono ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
                            </div>

                            <div class="md:col-span-2">
                                <label for="field-responsable_direccion" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Dirección Completa <span class="field-required" title="Campo obligatorio">*</span></label>
                                <textarea id="field-responsable_direccion" v-model="form.responsable_direccion" rows="2" maxlength="500" placeholder="Ej: Misma dirección del estudiante o una diferente" class="w-full bg-white border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] focus:ring-2 outline-none transition-all resize-none" :class="form.errors.responsable_direccion ? 'border-[var(--aurora-red)] focus:ring-[var(--aurora-red)]/20' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-[var(--frost3)]/20'" required />
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
                    <button v-if="currentStep > 1" type="button" @click="prevStep" class="flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-[var(--nord3)] hover:text-[var(--nord0)] border border-[var(--nord4)] hover:border-[var(--nord3)] rounded-lg transition-all">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg> Anterior
                    </button>
                    <div v-else />

                    <button v-if="currentStep < totalSteps" type="button" @click="nextStep" :disabled="currentStep === 1 ? !step1Valid : !step2Valid"
                        class="flex items-center gap-1.5 px-5 py-2 text-[13px] font-medium rounded-lg text-white transition-all shadow-sm"
                        :class="(currentStep === 1 && step1Valid) || (currentStep === 2 && step2Valid) ? 'bg-[var(--nord10)] hover:bg-[var(--nord9)] hover:shadow-md' : 'bg-[var(--nord4)] cursor-not-allowed'">
                        Siguiente <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </button>

                    <button v-else type="submit" :disabled="form.processing"
                        class="flex items-center gap-2 px-6 py-2 text-[13px] font-medium rounded-lg text-white bg-[var(--nord10)] hover:bg-[var(--nord9)] hover:shadow-md transition-all shadow-sm disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg v-if="form.processing" class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        {{ form.processing ? 'Guardando...' : 'Guardar y Cifrar Expediente' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
