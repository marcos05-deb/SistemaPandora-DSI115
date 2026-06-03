<script setup>
import { ref, computed } from "vue";
import { Head, useForm } from "@inertiajs/vue3";

const showPassword = ref(false);

const form = useForm({
  email: "",
  password: "",
});

const hasErrors = computed(() => {
  return Object.keys(form.errors).length > 0;
});

function submitLogin() {
  form.post("/login", {
    preserveScroll: true,
    onFinish: () => {
      if (form.hasErrors) {
        form.reset("password");
      }
    },
  });
}
</script>

<template>
  <Head title="Iniciar Sesión" />

  <div
    class="min-h-screen bg-[var(--nord6)] bg-cover bg-center bg-no-repeat flex flex-col items-center justify-center p-4 sm:p-6 md:p-8 relative overflow-hidden font-sans"
    style="background-image: url('/images/background.png')"
  >
    <div class="w-full max-w-[400px] sm:max-w-[420px] md:max-w-[440px] z-10 space-y-4">
      <!-- Card 1: Login Form -->
      <div class="bg-white p-6 sm:p-8 pb-6 sm:pb-7 shadow-lg rounded-2xl border border-[var(--nord4)]">
        <h2
          class="text-xl sm:text-[22px] font-bold text-[var(--nord0)] text-center mb-6 sm:mb-8 tracking-tight"
        >
          Iniciar sesión en PANDORA
        </h2>

        <!-- Error general de sesión -->
        <div
          v-if="form.errors.session"
          class="mb-6 bg-transparent px-4 py-3 text-sm text-[var(--aurora-red)] border border-[var(--aurora-red)] rounded-md flex items-center gap-2"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          {{ form.errors.session }}
        </div>

        <!-- Aviso de rate limiting -->
        <div
          v-if="form.errors.throttle"
          class="mb-6 bg-transparent px-4 py-3 text-sm text-[var(--aurora-orange)] border border-[var(--aurora-orange)] rounded-md flex items-center gap-2"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          {{ form.errors.throttle }}
        </div>

        <form @submit.prevent="submitLogin" class="space-y-6">
          <!-- Email Field -->
          <div class="relative">
            <input
              id="login-email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              placeholder="Correo electrónico"
              class="w-full border-0 border-b-2 border-[var(--nord4)] px-1 py-2 text-[15px] text-[var(--nord0)] focus:border-[var(--nord10)] focus:ring-0 bg-transparent transition-colors placeholder:text-[var(--nord3)]"
              :class="{ 'border-[var(--aurora-red)] focus:border-[var(--aurora-red)]': form.errors.email }"
            />
            <p v-if="form.errors.email" class="mt-1 text-xs text-[var(--aurora-red)]">
              {{ form.errors.email }}
            </p>
          </div>

          <!-- Password Field -->
          <div class="relative">
            <input
              id="login-password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              placeholder="Contraseña"
              class="w-full border-0 border-b-2 border-[var(--nord4)] px-1 py-2 pr-10 text-[15px] text-[var(--nord0)] focus:border-[var(--nord10)] focus:ring-0 bg-transparent transition-colors placeholder:text-[var(--nord3)]"
              :class="{ 'border-[var(--aurora-red)] focus:border-[var(--aurora-red)]': form.errors.password }"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 flex items-center pr-2 text-[var(--nord3)] hover:text-[var(--nord0)] transition-colors"
              @click="showPassword = !showPassword"
            >
              <svg
                v-if="!showPassword"
                class="h-4 w-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                />
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                />
              </svg>
              <svg
                v-else
                class="h-4 w-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                />
              </svg>
            </button>
            <p v-if="form.errors.password" class="mt-1 text-xs text-[var(--aurora-red)]">
              {{ form.errors.password }}
            </p>
          </div>

          <div class="pt-2">
            <button
              type="submit"
              :disabled="form.processing"
              class="w-full bg-[var(--nord10)] text-white py-2.5 px-4 rounded-xl text-[15px] font-medium hover:bg-[var(--nord9)] transition-colors disabled:opacity-70 flex justify-center items-center shadow-sm"
            >
              <span v-if="form.processing" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24">
                  <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                    fill="none"
                  />
                  <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                  />
                </svg>
                Autenticando...
              </span>
              <span v-else>Iniciar Sesión</span>
            </button>
          </div>

          <div class="text-center">
            <a
              href="#"
              class="text-[13px] text-[var(--nord10)] hover:text-[var(--nord8)] hover:underline font-medium transition-colors"
              >¿Olvidó su contraseña?</a
            >
          </div>
        </form>
      </div>

      <!-- Card 2: Footer / Info -->
      <div class="bg-white p-4 sm:p-6 shadow-lg rounded-2xl text-center border border-[var(--nord4)]">
        <h3 class="text-[13px] sm:text-[14px] font-semibold text-[var(--nord0)] mb-1 tracking-tight">
          Desarrollado por Camilo, Eduardo y Marcos
        </h3>

        <div
          class="mt-3 sm:mt-4 flex flex-wrap items-center justify-center gap-x-3 text-[10px] sm:text-[11px] text-[var(--nord3)]"
        >
          <span>© PANDORA</span>
          <span>|</span>
          <a href="#" class="hover:text-[var(--nord0)] hover:underline transition-colors">GitHub</a>
          <span>|</span>
          <a href="#" class="hover:text-[var(--nord0)] hover:underline transition-colors">Licencia</a>
        </div>
      </div>
    </div>
  </div>
</template>
