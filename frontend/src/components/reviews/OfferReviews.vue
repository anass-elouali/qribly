<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import dayjs from 'dayjs'
import { Star } from 'lucide-vue-next'
import api from '@/services/api'
import type { Review } from '@/types/review'
import type { PaginatedResponse } from '@/types/offer'
import StarRating from '@/components/reviews/StarRating.vue'
import { initials } from '@/utils/user'

const props = defineProps<{
  offerId: number
}>()

const reviews = ref<Review[]>([])
const total = ref(0)
const loading = ref(true)
const error = ref('')

const averageRating = computed(() => {
  if (reviews.value.length === 0) return 0
  return reviews.value.reduce((sum, review) => sum + review.rating, 0) / reviews.value.length
})

async function loadReviews() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get<PaginatedResponse<Review>>(`/offers/${props.offerId}/reviews`)
    reviews.value = response.data.data
    total.value = response.data.meta.total
  } catch {
    error.value = 'Impossible de charger les avis.'
  } finally {
    loading.value = false
  }
}

onMounted(loadReviews)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center gap-2">
      <Star :size="20" fill="currentColor" class="text-primary" aria-hidden="true" />
      <h2 class="font-display text-xl font-bold text-primary">Avis</h2>
      <template v-if="total > 0">
        <span class="font-mono text-xs text-ink/50">
          {{ averageRating.toFixed(1) }}/5 · {{ total }}
        </span>
      </template>
    </div>

    <div
      v-if="loading"
      class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
      aria-label="Chargement des avis"
    >
      <div
        v-for="index in 3"
        :key="index"
        class="h-32 animate-pulse rounded-lg bg-ink/5 motion-reduce:animate-none"
      ></div>
    </div>

    <div
      v-else-if="error"
      class="rounded-lg bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved"
      role="alert"
    >
      <p>{{ error }}</p>
      <button type="button" class="mt-1 font-semibold underline" @click="loadReviews">
        Réessayer
      </button>
    </div>

    <p v-else-if="reviews.length === 0" class="font-body text-sm text-ink/50">
      Aucun avis pour le moment.
    </p>

    <ul v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <li
        v-for="review in reviews"
        :key="review.id"
        class="rounded-lg border border-ink/10 bg-surface p-3.5"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="flex min-w-0 items-center gap-2.5">
            <span
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary font-mono text-[0.65rem] font-bold text-surface"
            >
              {{ initials(review.user?.name ?? 'Utilisateur') }}
            </span>
            <div class="min-w-0">
              <p class="truncate font-body text-sm font-semibold text-ink">
                {{ review.user?.name ?? 'Utilisateur' }}
              </p>
              <p class="font-mono text-[0.65rem] text-ink/40">
                {{ dayjs(review.created_at).fromNow() }}
              </p>
            </div>
          </div>
          <StarRating :rating="review.rating" />
        </div>
        <p v-if="review.comment" class="mt-3 font-body text-sm leading-5 text-ink/70">
          {{ review.comment }}
        </p>
      </li>
    </ul>
  </div>
</template>
