<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    users: Array,
    roles: Array,
    areas: Array,
});

const showForm = ref(false);
const editMode = ref(false);
const editId = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role_id: '',
    area_id: '',
    especialidad: '',
    numero_registro: '',
});

const isSysadminSelected = ref(false);

function onRoleChange() {
    const selectedRole = props.roles.find(r => r.id === form.role_id);
    isSysadminSelected.value = selectedRole?.slug === 'sysadmin';
    if (isSysadminSelected.value) {
        form.area_id = '';
        form.especialidad = '';
        form.numero_registro = '';
    }
}

function submitUser() {
    if (editMode.value) {
        form.put(`/admin/users/${editId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                showForm.value = false;
                editMode.value = false;
                editId.value = null;
            },
        });
    } else {
        form.post('/admin/users', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                showForm.value = false;
            },
        });
    }
}

function editUser(user) {
    editMode.value = true;
    editId.value = user.id;
    form.name = user.name;
    form.email = user.email;
    form.password = ''; // Cannot edit password
    form.role_id = user.roles[0]?.id || '';
    
    onRoleChange();

    if (user.profesional && !isSysadminSelected.value) {
        form.area_id = user.profesional.area_id || '';
        form.especialidad = user.profesional.especialidad || '';
        form.numero_registro = user.profesional.numero_registro || '';
    } else {
        form.area_id = '';
        form.especialidad = '';
        form.numero_registro = '';
    }

    showForm.value = true;
    // Scroll to form
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelForm() {
    showForm.value = false;
    editMode.value = false;
    editId.value = null;
    form.reset();
}

function deleteUser(id) {
    if (confirm('¿Estás seguro de que deseas eliminar este usuario del sistema?')) {
        router.delete(`/admin/users/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Gestión de Personal" />

    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex justify-between items-center bg-white p-6 shadow-lg rounded-2xl">
            <div>
                <h2 class="text-[22px] font-semibold text-[#1A1816] tracking-tight">Gestión de Personal</h2>
                <p class="text-sm text-gray-500 mt-1">Administra los accesos y perfiles del sistema PANDORA.</p>
            </div>
            <button 
                @click="showForm ? cancelForm() : (showForm = true)"
                class="bg-[#2F2B28] text-white py-2.5 px-5 rounded-xl text-[15px] font-medium hover:bg-[#1A1816] transition-colors"
            >
                {{ showForm ? 'Cancelar' : 'Nuevo Usuario' }}
            </button>
        </div>

        <!-- Form Section -->
        <div v-if="showForm" class="bg-white p-8 shadow-lg rounded-2xl border-t-4 border-[#1A1816]">
            <h3 class="text-lg font-medium text-[#1A1816] mb-6">{{ editMode ? 'Editar Usuario' : 'Datos del Nuevo Usuario' }}</h3>
            
            <form @submit.prevent="submitUser" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <!-- Basic Info -->
                    <div class="space-y-6">
                        <div class="relative">
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Nombre completo"
                                class="w-full border-0 border-b border-gray-300 px-1 py-2 text-[15px] text-gray-900 focus:border-[#1A1816] focus:ring-0 bg-transparent transition-colors placeholder:text-gray-400"
                            />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>
                        
                        <div class="relative">
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="Correo electrónico"
                                class="w-full border-0 border-b border-gray-300 px-1 py-2 text-[15px] text-gray-900 focus:border-[#1A1816] focus:ring-0 bg-transparent transition-colors placeholder:text-gray-400"
                            />
                            <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                        </div>

                        <div class="relative">
                            <input
                                v-model="form.password"
                                type="password"
                                :disabled="editMode"
                                :placeholder="editMode ? 'Contraseña bloqueada (Deuda Sprint 3)' : 'Contraseña (mínimo 14 caracteres, 1 mayúscula, 1 número, 1 símbolo)'"
                                :class="[
                                    'w-full border-0 border-b border-gray-300 px-1 py-2 text-[15px] focus:border-[#1A1816] focus:ring-0 bg-transparent transition-colors placeholder:text-gray-400',
                                    editMode ? 'text-gray-400 cursor-not-allowed bg-gray-50' : 'text-gray-900'
                                ]"
                            />
                            <p v-if="editMode" class="text-[11px] text-gray-500 mt-2 bg-gray-50 p-2 rounded border border-gray-200">
                                🔒 El cambio de contraseña está deshabilitado temporalmente (Deuda técnica Sprint 3) para proteger la integridad de los datos cifrados.
                            </p>
                            <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
                        </div>
                    </div>

                    <!-- Role & Professional Info -->
                    <div class="space-y-6">
                        <div class="relative">
                            <select
                                v-model="form.role_id"
                                @change="onRoleChange"
                                class="w-full border-0 border-b border-gray-300 px-1 py-2 text-[15px] text-gray-900 focus:border-[#1A1816] focus:ring-0 bg-transparent transition-colors"
                            >
                                <option value="" disabled>Seleccionar Rol...</option>
                                <option v-for="role in roles" :key="role.id" :value="role.id">
                                    {{ role.nombre }}
                                </option>
                            </select>
                            <p v-if="form.errors.role_id" class="text-red-500 text-xs mt-1">{{ form.errors.role_id }}</p>
                        </div>

                        <!-- Conditionally shown for non-sysadmin -->
                        <template v-if="form.role_id && !isSysadminSelected">
                            <div class="relative">
                                <select
                                    v-model="form.area_id"
                                    class="w-full border-0 border-b border-gray-300 px-1 py-2 text-[15px] text-gray-900 focus:border-[#1A1816] focus:ring-0 bg-transparent transition-colors"
                                >
                                    <option value="" disabled>Seleccionar Área Clínica...</option>
                                    <option v-for="area in areas" :key="area.id" :value="area.id">
                                        {{ area.nombre }}
                                    </option>
                                </select>
                                <p v-if="form.errors.area_id" class="text-red-500 text-xs mt-1">{{ form.errors.area_id }}</p>
                            </div>

                            <div class="relative">
                                <input
                                    v-model="form.especialidad"
                                    type="text"
                                    placeholder="Especialidad (Ej: Psicología Clínica)"
                                    class="w-full border-0 border-b border-gray-300 px-1 py-2 text-[15px] text-gray-900 focus:border-[#1A1816] focus:ring-0 bg-transparent transition-colors placeholder:text-gray-400"
                                />
                                <p v-if="form.errors.especialidad" class="text-red-500 text-xs mt-1">{{ form.errors.especialidad }}</p>
                            </div>

                            <div class="relative">
                                <input
                                    v-model="form.numero_registro"
                                    type="text"
                                    placeholder="Número de Registro / Colegiado"
                                    class="w-full border-0 border-b border-gray-300 px-1 py-2 text-[15px] text-gray-900 focus:border-[#1A1816] focus:ring-0 bg-transparent transition-colors placeholder:text-gray-400"
                                />
                                <p v-if="form.errors.numero_registro" class="text-red-500 text-xs mt-1">{{ form.errors.numero_registro }}</p>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-[#2F2B28] text-white py-2.5 px-8 rounded-xl text-[15px] font-medium hover:bg-[#1A1816] transition-colors disabled:opacity-70"
                    >
                        {{ editMode ? 'Actualizar Usuario' : 'Guardar Usuario' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white shadow-lg rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[14px]">
                    <thead class="bg-gray-50 border-b border-gray-100 text-gray-600 font-medium">
                        <tr>
                            <th class="py-4 px-6">Nombre</th>
                            <th class="py-4 px-6">Correo</th>
                            <th class="py-4 px-6">Rol</th>
                            <th class="py-4 px-6">Área / Especialidad</th>
                            <th class="py-4 px-6 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-6 font-medium text-gray-900">{{ user.name || '-' }}</td>
                            <td class="py-4 px-6 text-gray-600">{{ user.email }}</td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ user.roles[0]?.nombre || 'Sin rol' }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-gray-500">
                                <template v-if="user.profesional">
                                    <div class="text-gray-900">{{ user.areas[0]?.nombre || 'Sin área asignada' }}</div>
                                    <div class="text-xs text-gray-500">{{ user.profesional.especialidad }}</div>
                                </template>
                                <span v-else class="text-xs italic text-gray-400">N/A</span>
                            </td>
                            <td class="py-4 px-6 text-right space-x-3">
                                <button
                                    @click="editUser(user)"
                                    class="text-blue-500 hover:text-blue-700 transition-colors text-sm font-medium"
                                >
                                    Editar
                                </button>
                                <button
                                    v-if="user.id !== $page.props.auth.user.id"
                                    @click="deleteUser(user.id)"
                                    class="text-red-500 hover:text-red-700 transition-colors text-sm font-medium"
                                >
                                    Eliminar
                                </button>
                                <span v-else class="text-gray-400 text-sm font-medium italic">Tú</span>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td colspan="5" class="py-8 text-center text-gray-500">No hay personal registrado en el sistema.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
