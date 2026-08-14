<script setup lang="ts">
import { onBeforeUnmount, onMounted, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import echo from '@/echo'
import QriblyLogo from '@/components/branding/QriblyLogo.vue'

const authStore = useAuthStore()
const chatStore = useChatStore()
const router = useRouter()

let subscribedUserId: number | null = null

function unsubscribeFromInbox() {
  if (subscribedUserId) {
    echo.leave(`user.${subscribedUserId}`)
    subscribedUserId = null
  }
}

function subscribeToInbox() {
  const userId = authStore.user?.id
  if (!userId || userId === subscribedUserId) {
    return
  }

  unsubscribeFromInbox()
  subscribedUserId = userId

  // Any message sent to this user, in any conversation, refreshes the list
  // so the unread badge and previews stay live without a page reload.
  echo.private(`user.${userId}`).listen('MessageSent', () => {
    chatStore.load(true)
  })
}

onMounted(() => {
  if (authStore.isAuthenticated) {
    chatStore.load()
    subscribeToInbox()
  }
})

watch(
  () => authStore.isAuthenticated,
  (isAuthenticated) => {
    if (isAuthenticated) {
      chatStore.load()
      subscribeToInbox()
    } else {
      unsubscribeFromInbox()
    }
  },
)

onBeforeUnmount(unsubscribeFromInbox)

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
        <QriblyLogo class="w-32" :animated="false" :show-tagline="false" />
      </RouterLink>

      <div class="flex items-center gap-6 font-body text-sm">
        <RouterLink
          :to="{ name: 'home' }"
          class="text-ink/70 transition-colors hover:text-primary"
          active-class="font-semibold text-primary"
        >
          Accueil
        </RouterLink>

        <RouterLink
          :to="{ name: 'nearby' }"
          class="text-ink/70 transition-colors hover:text-primary"
          active-class="font-semibold text-primary"
        >
          Près de moi
        </RouterLink>

        <template v-if="authStore.isAuthenticated">
          <RouterLink
            :to="{ name: 'offer-create' }"
            class="rounded-md bg-accent px-3.5 py-1.5 font-semibold text-ink transition-opacity hover:opacity-90"
          >
            Publier une annonce
          </RouterLink>

          <RouterLink
            :to="{ name: 'conversations' }"
            class="flex items-center gap-1.5 text-ink/70 transition-colors hover:text-primary"
            active-class="font-semibold text-primary"
          >
            Messages
            <span
              v-if="chatStore.unreadCount > 0"
              class="flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 font-mono text-[0.65rem] font-bold text-ink"
            >
              {{ chatStore.unreadCount }}
            </span>
          </RouterLink>

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
