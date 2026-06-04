<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    facultades: {
        type: Array,
        default: () => []
    }
});

const form = useForm({
    // Paciente
    carnet: '',
    nombre_completo: '',
    direccion: '',
    carrera_id: '',
    sexo: '',
    estado_civil: '',
    fecha_nacimiento: '',
    profesion_ocupacion: '',
    fecha_primera_consulta: '',
    referido_por: '',
    llevado_por: '',
    motivo_consulta: '',
    
    // Familiares
    padre_nombre: '',
    padre_telefono: '',
    madre_nombre: '',
    madre_telefono: '',

    // Responsable Principal
    responsable_parentesco: '',
    responsable_nombre: '',
    responsable_telefono: '',
    responsable_direccion: '',
});

function submit() {
    form.post('/pacientes');
}
</script>

<template>
    <Head title="Registrar Paciente" />

    <div class="max-w-4xl mx-auto space-y-6 pb-10">
        <!-- Header Section -->
        <div class="flex justify-between items-center bg-white py-[14px] px-[18px] shadow-sm border border-[var(--nord4)] rounded-[10px]">
            <div>
                <h1 class="text-[16px] font-medium text-[var(--nord0)] tracking-tight flex items-center gap-2">
                    <Link href="/dashboard" class="text-[var(--nord3)] hover:text-[var(--nord0)] transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    Registrar Nuevo Paciente
                </h1>
                <p class="text-[12px] text-[var(--nord3)] mt-0.5 ml-7">
                    Ingresa los datos personales y familiares. Toda la información será protegida con cifrado de grado médico.
                </p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            
            <!-- Datos Personales -->
            <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
                <div class="px-6 py-4 border-b border-[var(--nord4)] bg-[var(--nord6)]">
                    <h2 class="text-[14px] font-medium text-[var(--nord0)] flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--nord10)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Datos del Estudiante / Paciente
                    </h2>
                </div>
                
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="carnet" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">
                            Carnet Estudiantil <span class="text-[var(--aurora-red)]">*</span>
                        </label>
                        <input id="carnet" v-model="form.carnet" type="text" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" required />
                        <div v-if="form.errors.carnet" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.carnet }}</div>
                    </div>

                    <div>
                        <label for="nombre_completo" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">
                            Nombre Completo <span class="text-[var(--aurora-red)]">*</span>
                        </label>
                        <input id="nombre_completo" v-model="form.nombre_completo" type="text" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" required />
                        <div v-if="form.errors.nombre_completo" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.nombre_completo }}</div>
                    </div>

                    <div class="md:col-span-2">
                        <label for="direccion" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">
                            Dirección de Residencia <span class="text-[var(--aurora-red)]">*</span>
                        </label>
                        <textarea id="direccion" v-model="form.direccion" rows="2" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none resize-none" required></textarea>
                        <div v-if="form.errors.direccion" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.direccion }}</div>
                    </div>

                    <div class="md:col-span-2">
                        <label for="motivo_consulta" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">
                            Motivo de Consulta <span class="text-[var(--aurora-red)]">*</span>
                        </label>
                        <textarea id="motivo_consulta" v-model="form.motivo_consulta" rows="3" placeholder="Describa el motivo por el cual el estudiante acude a consulta" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none resize-none" required></textarea>
                        <div v-if="form.errors.motivo_consulta" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.motivo_consulta }}</div>
                    </div>

                    <div>
                        <label for="carrera_id" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">
                            Carrera <span class="text-[var(--aurora-red)]">*</span>
                        </label>
                        <select id="carrera_id" v-model="form.carrera_id" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" required>
                            <option value="" disabled>Seleccione una carrera</option>
                            <optgroup v-for="facultad in facultades" :key="facultad.id" :label="facultad.nombre">
                                <option v-for="carrera in facultad.carreras" :key="carrera.id" :value="carrera.id">
                                    {{ carrera.nombre }}
                                </option>
                            </optgroup>
                        </select>
                        <div v-if="form.errors.carrera_id" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.carrera_id }}</div>
                    </div>

                    <div>
                        <label for="fecha_nacimiento" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">
                            Fecha de Nacimiento <span class="text-[var(--aurora-red)]">*</span>
                        </label>
                        <input id="fecha_nacimiento" v-model="form.fecha_nacimiento" type="date" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" required />
                        <div v-if="form.errors.fecha_nacimiento" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.fecha_nacimiento }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="sexo" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">
                                Sexo <span class="text-[var(--aurora-red)]">*</span>
                            </label>
                            <select id="sexo" v-model="form.sexo" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" required>
                                <option value="" disabled>Seleccionar</option>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                                <option value="Otro">Otro</option>
                            </select>
                            <div v-if="form.errors.sexo" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.sexo }}</div>
                        </div>
                        <div>
                            <label for="estado_civil" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">
                                Estado Civil <span class="text-[var(--aurora-red)]">*</span>
                            </label>
                            <select id="estado_civil" v-model="form.estado_civil" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" required>
                                <option value="" disabled>Seleccionar</option>
                                <option value="Soltero">Soltero</option>
                                <option value="Casado">Casado</option>
                                <option value="Divorciado">Divorciado</option>
                                <option value="Viudo">Viudo</option>
                                <option value="Unión Libre">Unión Libre</option>
                            </select>
                            <div v-if="form.errors.estado_civil" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.estado_civil }}</div>
                        </div>
                    </div>

                    <div>
                        <label for="profesion_ocupacion" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">Profesión / Ocupación (si aplica)</label>
                        <input id="profesion_ocupacion" v-model="form.profesion_ocupacion" type="text" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" />
                        <div v-if="form.errors.profesion_ocupacion" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.profesion_ocupacion }}</div>
                    </div>

                    <div>
                        <label for="fecha_primera_consulta" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">Fecha de Primera Consulta</label>
                        <input id="fecha_primera_consulta" v-model="form.fecha_primera_consulta" type="date" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" />
                        <div v-if="form.errors.fecha_primera_consulta" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.fecha_primera_consulta }}</div>
                    </div>

                    <div>
                        <label for="referido_por" class="flex items-center text-[13px] font-medium text-[var(--nord0)] mb-1">
                            Referido Por
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1.5 text-[var(--nord3)] cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Quien le dijo al estudiante que fuera a consulta">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </label>
                        <input id="referido_por" v-model="form.referido_por" type="text" placeholder="Ej: Dr. García - Clínica General" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" />
                        <div v-if="form.errors.referido_por" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.referido_por }}</div>
                    </div>

                    <div>
                        <label for="llevado_por" class="flex items-center text-[13px] font-medium text-[var(--nord0)] mb-1">
                            Acompañado Por
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1.5 text-[var(--nord3)] cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Quien acompañó al estudiante a esa primera consulta">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </label>
                        <input id="llevado_por" v-model="form.llevado_por" type="text" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" />
                        <div v-if="form.errors.llevado_por" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.llevado_por }}</div>
                    </div>
                </div>
            </div>

            <!-- Familiares y Contactos -->
            <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
                <div class="px-6 py-4 border-b border-[var(--nord4)] bg-[var(--nord6)]">
                    <h2 class="text-[14px] font-medium text-[var(--nord0)] flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--aurora-orange)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Familiares y Responsable Principal
                    </h2>
                </div>

                <div class="p-6 space-y-8">
                    <!-- Padre / Madre -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-6">
                        <!-- Padre -->
                        <div class="space-y-4">
                            <h3 class="text-[13px] font-semibold text-[var(--nord3)] uppercase tracking-wider">Datos del Padre</h3>
                            <div>
                                <label for="padre_nombre" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">Nombre</label>
                                <input id="padre_nombre" v-model="form.padre_nombre" type="text" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" />
                                <div v-if="form.errors.padre_nombre" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.padre_nombre }}</div>
                            </div>
                            <div>
                                <label for="padre_telefono" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">Teléfono (Opcional)</label>
                                <input id="padre_telefono" v-model="form.padre_telefono" type="tel" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" />
                                <div v-if="form.errors.padre_telefono" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.padre_telefono }}</div>
                            </div>
                        </div>

                        <!-- Madre -->
                        <div class="space-y-4">
                            <h3 class="text-[13px] font-semibold text-[var(--nord3)] uppercase tracking-wider">Datos de la Madre</h3>
                            <div>
                                <label for="madre_nombre" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">Nombre</label>
                                <input id="madre_nombre" v-model="form.madre_nombre" type="text" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" />
                                <div v-if="form.errors.madre_nombre" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.madre_nombre }}</div>
                            </div>
                            <div>
                                <label for="madre_telefono" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">Teléfono (Opcional)</label>
                                <input id="madre_telefono" v-model="form.madre_telefono" type="tel" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" />
                                <div v-if="form.errors.madre_telefono" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.madre_telefono }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-[var(--nord4)]"></div>

                    <!-- Responsable Principal -->
                    <div class="space-y-4">
                        <h3 class="text-[13px] font-semibold text-[var(--nord3)] uppercase tracking-wider flex items-center gap-2">
                            Datos del Responsable Legal
                            <span class="text-[10px] bg-[var(--nord6)] px-2 py-0.5 rounded text-[var(--nord3)] font-normal normal-case">Obligatorio</span>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="responsable_parentesco" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">
                                    Parentesco del Responsable <span class="text-[var(--aurora-red)]">*</span>
                                </label>
                                <select id="responsable_parentesco" v-model="form.responsable_parentesco" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" required>
                                    <option value="" disabled>Seleccionar</option>
                                    <option value="Padre">Padre</option>
                                    <option value="Madre">Madre</option>
                                    <option value="Otro">Otro Responsable / Tutor</option>
                                </select>
                                <div v-if="form.errors.responsable_parentesco" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.responsable_parentesco }}</div>
                            </div>

                            <div v-if="form.responsable_parentesco === 'Otro'">
                                <label for="responsable_nombre" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">
                                    Nombre del Responsable <span class="text-[var(--aurora-red)]">*</span>
                                </label>
                                <input id="responsable_nombre" v-model="form.responsable_nombre" type="text" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" required />
                                <div v-if="form.errors.responsable_nombre" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.responsable_nombre }}</div>
                            </div>

                            <div :class="{'md:col-span-2': form.responsable_parentesco !== 'Otro'}">
                                <label for="responsable_telefono" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">
                                    Teléfono de Contacto (Responsable) <span class="text-[var(--aurora-red)]">*</span>
                                </label>
                                <input id="responsable_telefono" v-model="form.responsable_telefono" type="tel" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none" required />
                                <div v-if="form.errors.responsable_telefono" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.responsable_telefono }}</div>
                            </div>

                            <div class="md:col-span-2">
                                <label for="responsable_direccion" class="block text-[13px] font-medium text-[var(--nord0)] mb-1">
                                    Dirección Completa (Responsable) <span class="text-[var(--aurora-red)]">*</span>
                                </label>
                                <textarea id="responsable_direccion" v-model="form.responsable_direccion" rows="2" class="w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] focus:ring-[rgba(129,161,193,0.2)] focus:border-[var(--frost3)] transition-colors outline-none resize-none" required></textarea>
                                <div v-if="form.errors.responsable_direccion" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.responsable_direccion }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Action -->
            <div class="flex justify-end pt-4 pb-12">
                <button 
                    type="submit" 
                    :disabled="form.processing"
                    class="bg-[var(--nord10)] hover:bg-[var(--nord9)] text-white font-medium py-2.5 px-8 rounded-lg shadow-sm transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                    <span v-if="form.processing" class="inline-block animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                    Guardar y Cifrar Expediente
                </button>
            </div>
            
        </form>
    </div>
</template>
