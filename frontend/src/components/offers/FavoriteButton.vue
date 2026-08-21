<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Heart } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useFavoritesStore } from '@/stores/favorites'
import { extractErrorMessage } from '@/utils/errors'

const props = withDefaults(
  defineProps<{
    offerId: number
    size?: number
  }>(),
  {
    size: 18,
  },
)

const authStore = useAuthStore()
const favoritesStore = useFavoritesStore()
const error = ref('')

onMounted(() => {
  if (authStore.isAuthenticated) {
    favoritesStore.load()
  }
})

async function toggle() {
  if (favoritesStore.isPending(props.offerId)) return

  error.value = ''

  try {
    await favoritesStore.toggle(props.offerId)
  } catch (exception) {
    error.value = extractErrorMessage(exception, 'Impossible de modifier ce favori. Réessaie.')
  }
}
</script>

<template>
  <span v-if="authStore.isAuthenticated" class="inline-flex">
    <span class="relative inline-flex">
      <button
        type="button"
        class="flex items-center justify-center rounded-full p-1.5 transition-colors disabled:cursor-wait disabled:opacity-60"
        :class="[
          favoritesStore.isFavorite(offerId)
            ? 'bg-accent text-ink'
            : 'bg-surface text-ink/60 hover:text-accent',
          error ? 'ring-2 ring-status-reserved/40' : '',
        ]"
        :disabled="favoritesStore.isPending(offerId)"
        :aria-pressed="favoritesStore.isFavorite(offerId)"
        :aria-label="
          favoritesStore.isFavorite(offerId) ? 'Retirer des favoris' : 'Ajouter aux favoris'
        "
        @click.stop.prevent="toggle"
      >
        <Heart :size="size" :fill="favoritesStore.isFavorite(offerId) ? 'currentColor' : 'none'" />
      </button>

      <span
        v-if="error"
        class="absolute top-full right-0 z-30 mt-2 w-56 rounded-lg bg-status-reserved px-3 py-2 text-left font-body text-xs leading-5 text-surface shadow-lg"
        role="alert"
      >
        {{ error }}
      </span>
    </span>
  </span>
</template>
