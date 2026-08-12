<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import dayjs from 'dayjs'
import api from '@/services/api'
import type { Review } from '@/types/review'
import type { PaginatedResponse } from '@/types/offer'
import StarRating from '@/components/reviews/StarRating.vue'

const props = defineProps<{
  offerId: number
}>()

const reviews = ref<Review[]>([])
const total = ref(0)
const loading = ref(true)

const averageRating = computed(() => {
  if (reviews.value.length === 0) return 0
  return reviews.value.reduce((sum, review) => sum + review.rating, 0) / reviews.value.length
})

async function loadReviews() {
  loading.value = true
  try {
    const response = await api.get<PaginatedResponse<Review>>(`/offers/${props.offerId}/reviews`)
    reviews.value = response.data.data
    total.value = response.data.meta.total
  } finally {
    loading.value = false
  }
}

onMounted(loadReviews)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center gap-2">
      <h2 class="font-display text-lg font-bold text-primary">Avis</h2>
      <template v-if="total > 0">
        <StarRating :rating="averageRating" :size="16" />
        <span class="font-mono text-xs text-ink/50">
          {{ averageRating.toFixed(1) }}/5 · {{ total }} avis
        </span>
      </template>
    </div>

    <p v-if="loading" class="font-mono text-sm text-ink/50">Chargement…</p>
    <p v-else-if="reviews.length === 0" class="font-mono text-sm text-ink/50">Aucun avis pour le moment.</p>

    <ul v-else class="flex flex-col gap-3">
      <li v-for="review in reviews" :key="review.id" class="rounded-md border border-ink/10 bg-surface p-3">
        <div class="flex items-center justify-between gap-2">
          <p class="font-body text-sm font-semibold text-ink">{{ review.user?.name ?? 'Utilisateur' }}</p>
          <StarRating :rating="review.rating" />
        </div>
        <p v-if="review.comment" class="mt-1 font-body text-sm text-ink/70">{{ review.comment }}</p>
        <p class="mt-1 font-mono text-xs text-ink/40">{{ dayjs(review.created_at).fromNow() }}</p>
      </li>
    </ul>
  </div>
</template>
