<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import dayjs from 'dayjs'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import type { Offer } from '@/types/offer'
import { statusLabel, statusColor, formatPrice } from '@/utils/offer'
import { resolveStorageUrl } from '@/utils/url'
import { extractErrorMessage } from '@/utils/errors'
import FavoriteButton from '@/components/offers/FavoriteButton.vue'
import OfferReviews from '@/components/reviews/OfferReviews.vue'

const route = useRoute()
const authStore = useAuthStore()
const offer = ref<Offer | null>(null)
const loading = ref(true)
const error = ref('')
const selectedImageIndex = ref(0)

const scheduledAt = ref('')
const notes = ref('')
const booking = ref(false)
const bookingError = ref('')
const bookingSuccess = ref(false)

const minScheduledAt = dayjs().format('YYYY-MM-DDTHH:mm')

function initials(name: string) {
  return name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

async function loadOffer() {
  loading.value = true
  error.value = ''
  selectedImageIndex.value = 0

  try {
    const response = await api.get<{ data: Offer }>(`/offers/${route.params.id}`)
    offer.value = response.data.data
  } catch {
    error.value = "Cette annonce n'existe pas ou plus."
  } finally {
    loading.value = false
  }
}

const selectedImageUrl = computed(() => {
  const image = offer.value?.images?.[selectedImageIndex.value]
  return image ? resolveStorageUrl(image.url) : null
})

async function submitReservation() {
  if (!offer.value) {
    return
  }

  bookingError.value = ''
  booking.value = true

  try {
    await api.post(`/offers/${offer.value.id}/reservations`, {
      scheduled_at: scheduledAt.value,
      notes: notes.value || undefined,
    })

    bookingSuccess.value = true
    scheduledAt.value = ''
    notes.value = ''
  } catch (err) {
    bookingError.value = extractErrorMessage(err, 'Impossible de réserver ce service.')
  } finally {
    booking.value = false
  }
}

onMounted(loadOffer)
watch(() => route.params.id, () => {
  loadOffer()
  bookingSuccess.value = false
  bookingError.value = ''
})
</script>

<template>
  <div class="mx-auto max-w-4xl px-6 py-8">
    <p v-if="loading" class="font-mono text-sm text-ink/50">Chargement…</p>

    <p v-else-if="error" class="rounded-md bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved">
      {{ error }}
    </p>

    <div v-else-if="offer" class="grid gap-8 md:grid-cols-2">
      <div class="flex flex-col gap-3">
        <div class="aspect-[4/3] overflow-hidden rounded-md bg-primary">
          <img v-if="selectedImageUrl" :src="selectedImageUrl" :alt="offer.title" class="h-full w-full object-cover" />
          <div
            v-else
            class="flex h-full items-center justify-center font-mono text-xs tracking-wide text-surface/70 uppercase"
          >
            Photo
          </div>
        </div>

        <div v-if="offer.images && offer.images.length > 1" class="flex gap-2">
          <button
            v-for="(image, index) in offer.images"
            :key="image.id"
            type="button"
            class="h-14 w-14 overflow-hidden rounded border-2 transition"
            :class="index === selectedImageIndex ? 'border-primary' : 'border-transparent'"
            @click="selectedImageIndex = index"
          >
            <img :src="resolveStorageUrl(image.url)" :alt="`Photo ${index + 1}`" class="h-full w-full object-cover" />
          </button>
        </div>
      </div>

      <div class="flex flex-col gap-4">
        <p v-if="offer.category" class="font-mono text-xs tracking-wide text-ink/50 uppercase">
          {{ offer.category.name }}
        </p>

        <div class="flex items-start justify-between gap-3">
          <h1 class="font-display text-2xl font-bold text-ink">{{ offer.title }}</h1>
          <FavoriteButton :offer-id="offer.id" :size="20" class="shrink-0 border border-ink/10" />
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <span class="-rotate-2 rounded bg-accent px-3 py-1 font-mono text-lg font-bold text-ink">
            {{ formatPrice(offer.price) }} DH
          </span>
          <span
            class="rounded px-2 py-0.5 font-mono text-xs tracking-wide text-surface uppercase"
            :class="statusColor[offer.status]"
          >
            {{ statusLabel[offer.status] }}
          </span>
          <span v-if="offer.is_negotiable" class="font-mono text-xs text-ink/50">Prix négociable</span>
        </div>

        <p class="font-body text-ink/80">{{ offer.description }}</p>

        <p class="font-mono text-xs text-ink/40">Publié {{ dayjs(offer.created_at).fromNow() }}</p>

        <div v-if="offer.owner" class="flex items-center gap-3 rounded-md border border-ink/10 bg-surface p-3">
          <span
            class="flex h-10 w-10 items-center justify-center rounded-full bg-primary font-mono text-xs font-bold text-surface"
          >
            {{ initials(offer.owner.name) }}
          </span>
          <p class="font-body font-semibold text-ink">{{ offer.owner.name }}</p>
        </div>

        <div v-if="offer.type === 'service'" class="rounded-md border border-ink/10 bg-surface p-4">
          <h2 class="mb-3 font-display text-lg font-bold text-primary">Réserver ce service</h2>

          <p v-if="!authStore.isAuthenticated" class="font-body text-sm text-ink/60">
            <RouterLink :to="{ name: 'login' }" class="font-semibold text-primary hover:underline">
              Connecte-toi
            </RouterLink>
            pour réserver.
          </p>

          <p v-else-if="offer.owner && offer.owner.id === authStore.user?.id" class="font-body text-sm text-ink/60">
            C'est ta propre annonce.
          </p>

          <p
            v-else-if="bookingSuccess"
            class="rounded-md bg-status-active/10 px-4 py-3 text-sm text-status-active"
          >
            Réservation envoyée ! Retrouve-la dans
            <RouterLink :to="{ name: 'profile' }" class="font-semibold underline">ton profil</RouterLink>.
          </p>

          <form v-else class="flex flex-col gap-3" @submit.prevent="submitReservation">
            <div>
              <label for="scheduled" class="mb-1 block font-mono text-xs tracking-wide text-ink/60 uppercase">
                Date et heure
              </label>
              <input
                id="scheduled"
                v-model="scheduledAt"
                type="datetime-local"
                :min="minScheduledAt"
                required
                class="w-full rounded-lg border border-ink/15 bg-ground px-3 py-2 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
              />
            </div>

            <div>
              <label for="resa-notes" class="mb-1 block font-mono text-xs tracking-wide text-ink/60 uppercase">
                Notes (optionnel)
              </label>
              <textarea
                id="resa-notes"
                v-model="notes"
                rows="2"
                class="w-full rounded-lg border border-ink/15 bg-ground px-3 py-2 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
              ></textarea>
            </div>

            <p v-if="bookingError" class="rounded-md bg-status-reserved/10 px-3 py-2 text-sm text-status-reserved">
              {{ bookingError }}
            </p>

            <button
              type="submit"
              :disabled="booking"
              class="rounded-lg bg-accent px-4 py-2.5 font-semibold text-ink transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ booking ? 'Envoi…' : 'Réserver' }}
            </button>
          </form>
        </div>
      </div>
    </div>

    <div v-if="offer" class="mt-10 border-t border-ink/10 pt-6">
      <OfferReviews :offer-id="offer.id" />
    </div>
  </div>
</template>
