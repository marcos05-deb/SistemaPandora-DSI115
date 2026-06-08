<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import DangerButton from '@/Components/UI/DangerButton.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Modal from '@/Components/UI/Modal.vue';

const props = defineProps({
    userLabel: String,
    navigation: Array,
    total: Number,
    users: Array,
});

const usersList = ref(props.users.map((u) => ({ ...u })));
const localNotice = ref(null);

const showCreate = ref(false);
const showEdit = ref(false);
const showDelete = ref(false);
const selectedUser = ref(null);

const emptyForm = () => ({
    name: '',
    email: '',
    role: 'Especialista',
    status: 'Activo',
});

const userForm = ref(emptyForm());

const roleOptions = [
    { value: 'Director', variant: 'director' },
    { value: 'Especialista', variant: 'specialist' },
    { value: 'Recepción', variant: 'reception' },
];

const totalDisplay = computed(() => usersList.value.length);

function roleVariant(role) {
    return roleOptions.find((r) => r.value === role)?.variant ?? 'specialist';
}

function statusVariant(status) {
    return status === 'Activo' ? 'active' : 'blocked';
}

function notify(message, variant = 'success') {
    localNotice.value = { message, variant };
    setTimeout(() => {
        localNotice.value = null;
    }, 4000);
}

function openCreate() {
    userForm.value = emptyForm();
    showCreate.value = true;
}

function saveCreate() {
    if (!userForm.value.name.trim() || !userForm.value.email.trim()) {
        notify('Complete nombre y correo.', 'warning');
        return;
    }
    const nextId = usersList.value.reduce((max, u) => Math.max(max, u.id), 0) + 1;
    usersList.value.push({
        id: nextId,
        name: userForm.value.name.trim(),
        email: userForm.value.email.trim(),
        role: userForm.value.role,
        roleVariant: roleVariant(userForm.value.role),
        status: userForm.value.status,
        statusVariant: statusVariant(userForm.value.status),
    });
    showCreate.value = false;
    notify('Usuario creado (demo, solo en esta sesión).');
}

function openEdit(user) {
    selectedUser.value = user;
    userForm.value = {
        name: user.name,
        email: user.email,
        role: user.role,
        status: user.status,
    };
    showEdit.value = true;
}

function saveEdit() {
    const user = usersList.value.find((u) => u.id === selectedUser.value?.id);
    if (!user) return;
    user.name = userForm.value.name.trim();
    user.email = userForm.value.email.trim();
    user.role = userForm.value.role;
    user.roleVariant = roleVariant(user.role);
    user.status = userForm.value.status;
    user.statusVariant = statusVariant(user.status);
    showEdit.value = false;
    notify('Cambios guardados (demo).');
}

function openDelete(user) {
    selectedUser.value = user;
    showDelete.value = true;
}

function confirmDelete() {
    usersList.value = usersList.value.filter((u) => u.id !== selectedUser.value?.id);
    showDelete.value = false;
    notify('Usuario eliminado de la lista (demo).', 'info');
}

defineOptions({
    layout: (h, page) =>
        h(AuthenticatedLayout, {
            userLabel: page.props.userLabel,
            navigation: page.props.navigation,
        }, () => page),
});
</script>

<template>
    <Head title="Gestión de Usuarios" />

    <div class="max-w-5xl">
        <div
            v-if="localNotice"
            :class="[
                'mb-4 rounded-lg border px-4 py-3 text-sm',
                localNotice.variant === 'warning'
                    ? 'bg-[var(--aurora-yellow)]/5 border-[var(--aurora-yellow)] text-[var(--aurora-orange)]'
                    : localNotice.variant === 'info'
                      ? 'bg-[var(--frost3)]/5 border-[var(--frost3)] text-[var(--frost4)]'
                      : 'bg-[var(--aurora-green)]/5 border-[var(--aurora-green)] text-[var(--aurora-green)]',
            ]"
        >
            {{ localNotice.message }}
        </div>

        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-[var(--nord0)]">Gestión de Usuarios y Roles</h1>
                <p class="text-sm text-[var(--nord3)] mt-1">
                    Administre los usuarios del sistema y asigne roles según el cargo
                </p>
            </div>
            <div class="flex items-center gap-4">
                <PrimaryButton type="button" @click="openCreate">+ Nuevo Usuario</PrimaryButton>
                <span class="text-sm text-[var(--nord3)] whitespace-nowrap">
                    Total: {{ totalDisplay }} usuarios
                </span>
            </div>
        </div>

        <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-[var(--surface-header)]">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider">Nombre</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider">Rol</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--nord5)]">
                    <tr v-for="user in usersList" :key="user.id" class="hover:bg-[var(--nord6)] transition-colors">
                        <td class="px-6 py-4 text-sm text-[var(--nord0)]">{{ user.name }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--nord3)]">{{ user.email }}</td>
                        <td class="px-6 py-4">
                            <StatusBadge :label="user.role" :variant="user.roleVariant" />
                        </td>
                        <td class="px-6 py-4">
                            <StatusBadge :label="user.status" :variant="user.statusVariant" />
                        </td>
                        <td class="px-6 py-4 text-sm space-x-3">
                    <button
                        type="button"
                        class="text-[var(--frost4)] hover:text-[#4C6A8D] font-medium transition-colors"
                        @click="openEdit(user)"
                    >
                        Editar
                    </button>
                    <button
                        type="button"
                        class="text-[var(--aurora-red)] hover:text-[#A05058] font-medium transition-colors"
                        @click="openDelete(user)"
                    >
                        Eliminar
                    </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Modal :show="showCreate" title="Nuevo usuario" @close="showCreate = false">
        <div class="space-y-4">
            <div>
                <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre</label>
                <input v-model="userForm.name" type="text" class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors" />
            </div>
            <div>
                <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Correo</label>
                <input v-model="userForm.email" type="email" class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Rol</label>
                    <select v-model="userForm.role" class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors">
                        <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.value }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Estado</label>
                    <select v-model="userForm.status" class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors">
                        <option value="Activo">Activo</option>
                        <option value="Bloqueado">Bloqueado</option>
                    </select>
                </div>
            </div>
        </div>
        <template #footer>
            <SecondaryButton type="button" @click="showCreate = false">Cancelar</SecondaryButton>
            <PrimaryButton type="button" @click="saveCreate">Guardar</PrimaryButton>
        </template>
    </Modal>

    <Modal :show="showEdit" title="Editar usuario" @close="showEdit = false">
        <div class="space-y-4">
            <div>
                <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre</label>
                <input v-model="userForm.name" type="text" class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors" />
            </div>
            <div>
                <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Correo</label>
                <input v-model="userForm.email" type="email" class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Rol</label>
                    <select v-model="userForm.role" class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors">
                        <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.value }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Estado</label>
                    <select v-model="userForm.status" class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors">
                        <option value="Activo">Activo</option>
                        <option value="Bloqueado">Bloqueado</option>
                    </select>
                </div>
            </div>
        </div>
        <template #footer>
            <SecondaryButton type="button" @click="showEdit = false">Cancelar</SecondaryButton>
            <PrimaryButton type="button" @click="saveEdit">Guardar cambios</PrimaryButton>
        </template>
    </Modal>

    <Modal :show="showDelete" title="Eliminar usuario" @close="showDelete = false">
        <p class="text-sm text-[var(--nord3)]">
            ¿Eliminar a <strong>{{ selectedUser?.name }}</strong>? Acción demostrativa; no afecta la base de datos.
        </p>
        <template #footer>
            <SecondaryButton type="button" @click="showDelete = false">Cancelar</SecondaryButton>
            <DangerButton type="button" @click="confirmDelete">Eliminar</DangerButton>
        </template>
    </Modal>
</template>
