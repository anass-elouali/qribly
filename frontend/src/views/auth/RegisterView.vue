<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { extractErrorMessage } from '@/utils/errors'

const authStore = useAuthStore()
const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref('')
const loading = ref(false)

async function handleRegister() {
  error.value = ''
  loading.value = true

  try {
    await authStore.register(name.value, email.value, password.value, passwordConfirmation.value)

    await router.push({
      name: 'home',
    })
  } catch (err) {
    error.value = extractErrorMessage(err, "Impossible de créer le compte.")
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="flex min-h-[calc(100vh-8rem)] items-center justify-center bg-ground px-4 py-12">
    <div class="w-full max-w-md rounded-2xl border border-ink/10 bg-surface p-8 shadow-lg">
      <h1 class="mb-2 font-display text-3xl font-bold text-ink">Rejoins Qribly</h1>

      <p class="mb-6 font-body text-ink/60">Trente secondes, pas plus.</p>

      <form class="space-y-5" @submit.prevent="handleRegister">
        <div>
          <label for="name" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
            Nom
          </label>

          <input
            id="name"
            v-model="name"
            type="text"
            autocomplete="name"
            required
            placeholder="Sara L."
            class="w-full rounded-lg border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <div>
          <label for="email" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
            Email
          </label>

          <input
            id="email"
            v-model="email"
            type="email"
            autocomplete="email"
            required
            placeholder="toi@example.com"
            class="w-full rounded-lg border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <div>
          <label for="password" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
            Mot de passe
          </label>

          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="new-password"
            required
            minlength="8"
            placeholder="8 caractères minimum"
            class="w-full rounded-lg border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <div>
          <label
            for="password-confirmation"
            class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase"
          >
            Confirmer le mot de passe
          </label>

          <input
            id="password-confirmation"
            v-model="passwordConfirmation"
            type="password"
            autocomplete="new-password"
            required
            minlength="8"
            placeholder="••••••••"
            class="w-full rounded-lg border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <p v-if="error" class="rounded-lg bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved">
          {{ error }}
        </p>

        <button
          type="submit"
          :disabled="loading"
          class="w-full rounded-lg bg-accent px-4 py-3 font-semibold text-ink transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {{ loading ? 'Création…' : 'Créer mon compte' }}
        </button>

        <p class="text-center text-sm text-ink/60">
          Déjà inscrit ?
          <RouterLink :to="{ name: 'login' }" class="font-semibold text-primary hover:underline">
            Se connecter
          </RouterLink>
        </p>
      </form>
    </div>
  </main>
</template>
