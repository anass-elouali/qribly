<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { Heart, House, LogIn, LogOut, MapPin, MessageCircle, User } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import echo from '@/echo'
import { initials } from '@/utils/user'
import QriblyLogo from '@/components/branding/QriblyLogo.vue'

const authStore = useAuthStore()
const chatStore = useChatStore()
const router = useRouter()

const profileMenuOpen = ref(false)
const profileMenuRef = ref<HTMLElement | null>(null)

function closeProfileMenu() {
  profileMenuOpen.value = false
}

function handleOutsideClick(event: MouseEvent) {
  if (profileMenuRef.value && !profileMenuRef.value.contains(event.target as Node)) {
    closeProfileMenu()
  }
}

function handleEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    closeProfileMenu()
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
  document.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
  document.removeEventListener('keydown', handleEscape)
})

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
  closeProfileMenu()
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

      <div class="flex items-center gap-1 font-body text-sm">
        <RouterLink
          :to="{ name: 'home' }"
          class="group relative flex items-center rounded-md p-2 text-ink/60 transition-colors hover:text-ink"
          exact-active-class="link-active !text-primary"
          aria-label="Accueil"
          title="Accueil"
        >
          <House :size="20" />
          <span
            class="pin-drop pointer-events-none absolute bottom-0.5 left-1/2 h-1.5 w-1.5 -translate-x-1/2 rotate-45 scale-0 rounded-[50%_50%_50%_0] bg-accent opacity-0 transition-all duration-300 ease-out group-[.link-active]:scale-100 group-[.link-active]:opacity-100"
          ></span>
        </RouterLink>

        <RouterLink
          :to="{ name: 'nearby' }"
          class="group relative flex items-center gap-1.5 rounded-md px-2.5 py-2 text-ink/60 transition-colors hover:text-ink"
          active-class="link-active !text-primary !font-semibold"
        >
          <MapPin :size="20" />
          Près de moi
          <span
            class="pin-drop pointer-events-none absolute bottom-0.5 left-1/2 h-1.5 w-1.5 -translate-x-1/2 rotate-45 scale-0 rounded-[50%_50%_50%_0] bg-accent opacity-0 transition-all duration-300 ease-out group-[.link-active]:scale-100 group-[.link-active]:opacity-100"
          ></span>
        </RouterLink>

        <template v-if="authStore.isAuthenticated">
          <RouterLink
            :to="{ name: 'conversations' }"
            class="group relative ml-2 flex items-center rounded-md p-2 text-ink/60 transition-colors hover:text-ink"
            active-class="link-active !text-primary"
            aria-label="Messages"
            title="Messages"
          >
            <MessageCircle :size="20" />
            <span
              v-if="chatStore.unreadCount > 0"
              class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 font-mono text-[0.6rem] font-bold text-ink"
            >
              {{ chatStore.unreadCount }}
            </span>
            <span
              class="pin-drop pointer-events-none absolute bottom-0.5 left-1/2 h-1.5 w-1.5 -translate-x-1/2 rotate-45 scale-0 rounded-[50%_50%_50%_0] bg-accent opacity-0 transition-all duration-300 ease-out group-[.link-active]:scale-100 group-[.link-active]:opacity-100"
            ></span>
          </RouterLink>

          <RouterLink
            :to="{ name: 'offer-create' }"
            class="ml-2 rounded-md bg-accent px-3.5 py-1.5 font-semibold text-ink transition-opacity hover:opacity-90"
          >
            Publier une annonce
          </RouterLink>

          <div v-if="authStore.user" ref="profileMenuRef" class="relative ml-2">
            <button
              type="button"
              class="flex h-8 w-8 items-center justify-center rounded-full border-2 bg-primary font-mono text-xs font-bold text-surface transition-colors"
              :class="profileMenuOpen ? 'border-accent' : 'border-transparent hover:border-accent'"
              aria-haspopup="true"
              :aria-expanded="profileMenuOpen"
              aria-label="Menu profil"
              @click="profileMenuOpen = !profileMenuOpen"
            >
              {{ initials(authStore.user.name) }}
            </button>

            <div
              v-if="profileMenuOpen"
              class="absolute top-[calc(100%+0.65rem)] right-0 z-30 w-60 rounded-xl border border-ink/10 bg-surface p-1 shadow-lg"
            >
              <div class="flex items-center gap-2.5 px-2.5 py-2">
                <span
                  class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary font-mono text-xs font-bold text-surface"
                >
                  {{ initials(authStore.user.name) }}
                </span>
                <div class="min-w-0">
                  <p class="truncate font-body text-sm font-semibold text-ink">{{ authStore.user.name }}</p>
                  <p class="truncate font-mono text-xs text-ink/45">{{ authStore.user.email }}</p>
                </div>
              </div>

              <hr class="my-1 border-ink/10" />

              <RouterLink
                :to="{ name: 'profile' }"
                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-ink transition-colors hover:bg-ground"
                @click="closeProfileMenu"
              >
                <User :size="16" class="text-ink/50" />
                Mon profil
              </RouterLink>

              <RouterLink
                :to="{ name: 'profile', query: { tab: 'favorites' } }"
                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-ink transition-colors hover:bg-ground"
                @click="closeProfileMenu"
              >
                <Heart :size="16" class="text-ink/50" />
                Mes favoris
              </RouterLink>

              <button
                type="button"
                class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm text-ink transition-colors hover:bg-ground"
                @click="handleLogout"
              >
                <LogOut :size="16" class="text-ink/50" />
                Déconnexion
              </button>
            </div>
          </div>
        </template>

        <template v-else>
          <RouterLink
            :to="{ name: 'login' }"
            class="group relative ml-2 flex items-center rounded-md p-2 text-ink/60 transition-colors hover:text-ink"
            active-class="link-active !text-primary"
            aria-label="Connexion"
            title="Connexion"
          >
            <LogIn :size="20" />
            <span
              class="pin-drop pointer-events-none absolute bottom-0.5 left-1/2 h-1.5 w-1.5 -translate-x-1/2 rotate-45 scale-0 rounded-[50%_50%_50%_0] bg-accent opacity-0 transition-all duration-300 ease-out group-[.link-active]:scale-100 group-[.link-active]:opacity-100"
            ></span>
          </RouterLink>

          <RouterLink
            :to="{ name: 'register' }"
            class="ml-2 rounded-md bg-accent px-3.5 py-1.5 font-semibold text-ink transition-opacity hover:opacity-90"
          >
            Inscription
          </RouterLink>
        </template>
      </div>
    </nav>
  </header>
</template>
