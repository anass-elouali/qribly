<script setup lang="ts">
import dayjs from 'dayjs'
import { reactive, ref } from 'vue'

import { useAuthStore } from '@/stores/auth'
import { extractErrorMessage } from '@/utils/errors'
import { initials } from '@/utils/user'

defineProps<{
  offersCount: number
  favoritesCount: number
  reservationsCount: number
}>()

const authStore = useAuthStore()
const editing = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')
const form = reactive({
  name: '',
  email: '',
})

function openEditor() {
  if (!authStore.user) return

  form.name = authStore.user.name
  form.email = authStore.user.email
  error.value = ''
  success.value = ''
  editing.value = true
}

function closeEditor() {
  if (saving.value) return

  editing.value = false
  error.value = ''
}

async function saveProfile() {
  if (saving.value) return

  const name = form.name.trim()
  const email = form.email.trim()

  if (!name || !email) {
    error.value = 'Le nom et l’adresse e-mail sont obligatoires.'
    return
  }

  saving.value = true
  error.value = ''
  success.value = ''

  try {
    await authStore.updateProfile(name, email)
    editing.value = false
    success.value = 'Ton profil a été mis à jour.'
  } catch (exception) {
    error.value = extractErrorMessage(exception, 'Impossible de modifier ton profil.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section v-if="authStore.user" class="mb-8 rounded-xl border border-ink/10 bg-surface p-6 sm:p-7">
    <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-4">
        <span
          class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary font-display text-xl font-bold text-surface"
        >
          {{ initials(authStore.user.name) }}
        </span>

        <div class="min-w-0">
          <p class="font-mono text-[0.65rem] tracking-[0.2em] text-primary uppercase">Mon profil</p>

          <h1 class="mt-1 truncate font-display text-2xl font-bold text-ink">
            {{ authStore.user.name }}
          </h1>

          <p class="mt-1 truncate font-mono text-xs text-ink/50">
            {{ authStore.user.email }}
          </p>

          <p class="mt-1 font-mono text-xs text-ink/40">
            Membre depuis
            {{ dayjs(authStore.user.created_at).format('MMMM YYYY') }}
          </p>
        </div>
      </div>

      <button
        type="button"
        :disabled="saving"
        class="inline-flex items-center justify-center rounded-md border border-ink/15 px-4 py-2 font-mono text-xs tracking-wide text-ink transition hover:border-primary hover:text-primary"
        :aria-expanded="editing"
        @click="editing ? closeEditor() : openEditor()"
      >
        {{ editing ? 'Fermer' : 'Modifier le profil' }}
      </button>
    </div>

    <form
      v-if="editing"
      class="mt-6 rounded-xl border border-ink/10 bg-ground/60 p-4 sm:p-5"
      @submit.prevent="saveProfile"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="font-body text-sm font-semibold text-ink">
          Nom
          <input
            v-model="form.name"
            type="text"
            autocomplete="name"
            required
            maxlength="255"
            class="mt-2 h-11 w-full rounded-lg border border-ink/15 bg-surface px-3 font-body text-sm font-normal text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
          />
        </label>

        <label class="font-body text-sm font-semibold text-ink">
          Adresse e-mail
          <input
            v-model="form.email"
            type="email"
            autocomplete="email"
            required
            maxlength="255"
            class="mt-2 h-11 w-full rounded-lg border border-ink/15 bg-surface px-3 font-body text-sm font-normal text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
          />
        </label>
      </div>

      <p v-if="error" class="mt-3 font-body text-sm text-status-reserved" role="alert">
        {{ error }}
      </p>

      <div class="mt-4 flex flex-wrap justify-end gap-2">
        <button
          type="button"
          :disabled="saving"
          class="rounded-lg border border-ink/15 px-4 py-2.5 font-body text-sm font-semibold text-ink transition hover:border-ink/35 disabled:opacity-50"
          @click="closeEditor"
        >
          Annuler
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="rounded-lg bg-primary px-4 py-2.5 font-body text-sm font-semibold text-surface transition hover:opacity-90 disabled:cursor-wait disabled:opacity-60"
        >
          {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
      </div>
    </form>

    <p
      v-else-if="success"
      class="mt-5 rounded-lg border border-status-active/20 bg-status-active/5 px-4 py-3 font-body text-sm text-status-active"
      role="status"
      aria-live="polite"
    >
      {{ success }}
    </p>

    <div class="mt-6 grid grid-cols-3 border-t border-ink/10 pt-6">
      <div class="text-center sm:text-left">
        <p class="font-display text-xl font-bold text-ink">
          {{ offersCount }}
        </p>

        <p class="mt-1 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">Annonces</p>
      </div>

      <div class="border-l border-ink/10 pl-4 text-center sm:text-left">
        <p class="font-display text-xl font-bold text-ink">
          {{ favoritesCount }}
        </p>

        <p class="mt-1 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">Favoris</p>
      </div>

      <div class="border-l border-ink/10 pl-4 text-center sm:text-left">
        <p class="font-display text-xl font-bold text-ink">
          {{ reservationsCount }}
        </p>

        <p class="mt-1 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">
          Réservations
        </p>
      </div>
    </div>
  </section>
</template>
