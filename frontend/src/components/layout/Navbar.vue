<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import QriblyLogo from '@/components/branding/QriblyLogo.vue'

const authStore = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await authStore.logout()

  await router.push({
    name: 'home',
  })
}
</script>

<template>
  <header class="border-b border-ink/10 bg-surface">
    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6">
      <RouterLink :to="{ name: 'home' }" class="flex items-center" aria-label="Qribly, accueil">
        <QriblyLogo />
      </RouterLink>

      <div class="flex items-center gap-6 font-body text-sm">
        <RouterLink
          :to="{ name: 'home' }"
          class="text-ink/70 transition-colors hover:text-primary"
          active-class="font-semibold text-primary"
        >
          Accueil
        </RouterLink>

        <template v-if="authStore.isAuthenticated">
          <RouterLink
            :to="{ name: 'profile' }"
            class="text-ink/70 transition-colors hover:text-primary"
            active-class="font-semibold text-primary"
          >
            Profil
          </RouterLink>

          <button
            type="button"
            class="cursor-pointer rounded-md border border-ink/15 px-3 py-1.5 text-ink/70 transition-colors hover:border-primary hover:text-primary"
            @click="handleLogout"
          >
            Déconnexion
          </button>
        </template>

        <template v-else>
          <RouterLink
            :to="{ name: 'login' }"
            class="text-ink/70 transition-colors hover:text-primary"
            active-class="font-semibold text-primary"
          >
            Connexion
          </RouterLink>

          <RouterLink
            :to="{ name: 'register' }"
            class="rounded-md bg-accent px-3.5 py-1.5 font-semibold text-ink transition-opacity hover:opacity-90"
          >
            Inscription
          </RouterLink>
        </template>
      </div>
    </nav>
  </header>
</template>
