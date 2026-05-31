<script setup>
import { ref, computed } from "vue";
import { Head, useForm } from "@inertiajs/vue3";

// Layout removido para diseño a pantalla completa personalizado
// defineOptions({ layout: GuestLayout });

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

  <!-- Oracle-style background -->
  <div
    class="min-h-screen bg-[#F0EBE1] bg-cover bg-center bg-no-repeat flex flex-col items-center justify-center p-4 relative overflow-hidden font-sans"
    style="background-image: url(&quot;/images/background.png&quot;)"
  >
    <div class="w-full max-w-[400px] z-10 space-y-4">
      <!-- Card 1: Login Form -->
      <div class="bg-white p-8 pb-7 shadow-lg rounded-2xl">
        <h2
          class="text-[22px] font-semibold text-[#1A1816] text-center mb-8 tracking-tight"
        >
          Iniciar sesión en PANDORA
        </h2>

        <!-- Error general de sesión -->
        <div
          v-if="form.errors.session"
          class="mb-6 bg-red-50 px-4 py-3 text-sm text-red-700 border-l-4 border-red-500 rounded-r-md"
        >
          {{ form.errors.session }}
        </div>

        <!-- Aviso de rate limiting -->
        <div
          v-if="form.errors.throttle"
          class="mb-6 bg-orange-50 px-4 py-3 text-sm text-orange-700 border-l-4 border-orange-500 rounded-r-md"
        >
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
              class="w-full border-0 border-b border-gray-300 px-1 py-2 text-[15px] text-gray-900 focus:border-[#1A1816] focus:ring-0 bg-transparent transition-colors placeholder:text-gray-400"
              :class="{ 'border-red-500': form.errors.email }"
            />
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">
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
              class="w-full border-0 border-b border-gray-300 px-1 py-2 pr-10 text-[15px] text-gray-900 focus:border-[#1A1816] focus:ring-0 bg-transparent transition-colors placeholder:text-gray-400"
              :class="{ 'border-red-500': form.errors.password }"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 hover:text-gray-700 transition-colors"
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
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">
              {{ form.errors.password }}
            </p>
          </div>

          <div class="pt-2">
            <button
              type="submit"
              :disabled="form.processing"
              class="w-full bg-[#2F2B28] text-white py-2.5 px-4 rounded-xl text-[15px] font-medium hover:bg-[#1A1816] transition-colors disabled:opacity-70 flex justify-center items-center"
            >
              <span v-if="form.processing" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
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
              <span v-else>Siguiente</span>
            </button>
          </div>

          <div class="text-center">
            <a
              href="#"
              class="text-[13px] text-[#00758F] hover:underline font-medium"
              >¿Olvidó su usuario o contraseña?</a
            >
          </div>
        </form>
      </div>

      <!-- Card 2: Footer / Info -->
      <div class="bg-white p-6 shadow-lg rounded-2xl text-center">
        <h3 class="text-[15px] font-medium text-gray-700 mb-1 tracking-tight">
          Desarrollado por Camilo, Eduardo y Marcos
        </h3>

        <div
          class="mt-4 flex flex-wrap items-center justify-center gap-x-3 text-[11px] text-gray-500"
        >
          <span>© PANDORA</span>
          <span>|</span>
          <a href="#" class="hover:underline">GitHub</a>
          <span>|</span>
          <a href="#" class="hover:underline">Licencia</a>
        </div>
      </div>
    </div>
  </div>
</template>
