<script setup lang="ts">
import dayjs from 'dayjs'

import { useAuthStore } from '@/stores/auth'
import { initials } from '@/utils/user'

defineProps<{
  offersCount: number
  favoritesCount: number
  reservationsCount: number
}>()

const authStore = useAuthStore()
</script>

<template>
  <section
    v-if="authStore.user"
    class="mb-8 rounded-xl border border-ink/10 bg-surface p-6 sm:p-7"
  >
    <div
      class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between"
    >
      <div class="flex items-center gap-4">
        <span
          class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary font-display text-xl font-bold text-surface"
        >
          {{ initials(authStore.user.name) }}
        </span>

        <div class="min-w-0">
          <p
            class="font-mono text-[0.65rem] tracking-[0.2em] text-primary uppercase"
          >
            Mon profil
          </p>

          <h1
            class="mt-1 truncate font-display text-2xl font-bold text-ink"
          >
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
        class="inline-flex items-center justify-center rounded-md border border-ink/15 px-4 py-2 font-mono text-xs tracking-wide text-ink transition hover:border-primary hover:text-primary"
        >
        Modifier le profil
        </button>
    </div>

    <div
      class="mt-6 grid grid-cols-3 border-t border-ink/10 pt-6"
    >
      <div class="text-center sm:text-left">
        <p class="font-display text-xl font-bold text-ink">
          {{ offersCount }}
        </p>

        <p
          class="mt-1 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
          Annonces
        </p>
      </div>

      <div
        class="border-l border-ink/10 pl-4 text-center sm:text-left"
      >
        <p class="font-display text-xl font-bold text-ink">
          {{ favoritesCount }}
        </p>

        <p
          class="mt-1 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
          Favoris
        </p>
      </div>

      <div
        class="border-l border-ink/10 pl-4 text-center sm:text-left"
      >
        <p class="font-display text-xl font-bold text-ink">
          {{ reservationsCount }}
        </p>

        <p
          class="mt-1 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
          Réservations
        </p>
      </div>
    </div>
  </section>
</template>