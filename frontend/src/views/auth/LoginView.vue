<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { extractErrorMessage } from '@/utils/errors'

const authStore = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function handleLogin() {
  if (loading.value) return

  error.value = ''
  loading.value = true

  try {
    await authStore.login(email.value, password.value)

    await router.push({
      name: 'home',
    })
  } catch (err) {
    error.value = extractErrorMessage(err, 'Email ou mot de passe incorrect.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="flex min-h-[calc(100vh-8rem)] items-center justify-center bg-ground px-4 py-12">
    <div class="w-full max-w-md rounded-2xl border border-ink/10 bg-surface p-8 shadow-lg">
      <h1 class="mb-2 font-display text-3xl font-bold text-ink">Content de te revoir</h1>

      <p class="mb-6 font-body text-ink/60">Connecte-toi à ton compte Qribly.</p>

      <form class="space-y-5" @submit.prevent="handleLogin">
        <div>
          <label
            for="email"
            class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase"
          >
            Email
          </label>

          <input
            id="email"
            v-model="email"
            type="email"
            autocomplete="email"
            required
            :disabled="loading"
            placeholder="toi@example.com"
            class="w-full rounded-lg border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <div>
          <label
            for="password"
            class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase"
          >
            Mot de passe
          </label>

          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
            :disabled="loading"
            placeholder="••••••••"
            class="w-full rounded-lg border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <p
          v-if="error"
          class="rounded-lg bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved"
          role="alert"
        >
          {{ error }}
        </p>

        <button
          type="submit"
          :disabled="loading"
          class="w-full rounded-lg bg-primary px-4 py-3 font-semibold text-surface transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {{ loading ? 'Connexion…' : error ? 'Réessayer' : 'Se connecter' }}
        </button>

        <p class="text-center text-sm text-ink/60">
          Pas de compte ?
          <RouterLink :to="{ name: 'register' }" class="font-semibold text-primary hover:underline">
            S'inscrire
          </RouterLink>
        </p>
      </form>
    </div>
  </main>
</template>
