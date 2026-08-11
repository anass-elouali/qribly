<script setup lang="ts">
import { onMounted } from 'vue'
import { Heart } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useFavoritesStore } from '@/stores/favorites'

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

onMounted(() => {
  if (authStore.isAuthenticated) {
    favoritesStore.load()
  }
})

function toggle() {
  favoritesStore.toggle(props.offerId)
}
</script>

<template>
  <button
    v-if="authStore.isAuthenticated"
    type="button"
    class="flex items-center justify-center rounded-full p-1.5 transition-colors"
    :class="
      favoritesStore.isFavorite(offerId)
        ? 'bg-accent text-ink'
        : 'bg-surface text-ink/60 hover:text-accent'
    "
    :aria-pressed="favoritesStore.isFavorite(offerId)"
    :aria-label="favoritesStore.isFavorite(offerId) ? 'Retirer des favoris' : 'Ajouter aux favoris'"
    @click.stop.prevent="toggle"
  >
    <Heart :size="size" :fill="favoritesStore.isFavorite(offerId) ? 'currentColor' : 'none'" />
  </button>
</template>
