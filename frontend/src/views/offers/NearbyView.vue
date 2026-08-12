<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '@/services/api'
import OfferMap from '@/components/offers/OfferMap.vue'
import OfferCard from '@/components/offers/OfferCard.vue'
import RadarOverlay from '@/components/offers/RadarOverlay.vue'
import type { Offer, PaginatedResponse } from '@/types/offer'
import { statusLabel, statusColor, formatPrice, formatDistance } from '@/utils/offer'

const router = useRouter()

type ViewMode = 'radar' | 'explorer' | 'immersive'

const viewModes: { value: ViewMode; label: string }[] = [
  { value: 'radar', label: 'Radar' },
  { value: 'explorer', label: 'Explorateur' },
  { value: 'immersive', label: 'Immersif' },
]

const viewMode = ref<ViewMode>('explorer')

const userPosition = ref<{ latitude: number; longitude: number } | null>(null)
const radiusKm = ref(5)
const radiusOptions = [1, 5, 10, 25, 50]
const locating = ref(true)
const locationError = ref('')

const offers = ref<Offer[]>([])
const loadingOffers = ref(false)

onMounted(() => {
  if (!navigator.geolocation) {
    locationError.value = "Ton navigateur ne supporte pas la géolocalisation."
    locating.value = false
    return
  }

  navigator.geolocation.getCurrentPosition(
    (position) => {
      userPosition.value = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
      }
      locating.value = false
      fetchNearbyOffers()
    },
    () => {
      locationError.value = "Impossible d'accéder à ta position. Autorise la géolocalisation."
      locating.value = false
    },
  )
})

async function fetchNearbyOffers() {
  if (!userPosition.value) return

  loadingOffers.value = true
  try {
    const response = await api.get<PaginatedResponse<Offer>>('/offers/nearby', {
      params: {
        latitude: userPosition.value.latitude,
        longitude: userPosition.value.longitude,
        radius: radiusKm.value,
      },
    })
    offers.value = response.data.data
  } finally {
    loadingOffers.value = false
  }
}

function goToOffer(offerId: number) {
  router.push({ name: 'offer-details', params: { id: offerId } })
}
</script>

<template>
  <div class="mx-auto max-w-6xl px-6 py-8">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h1 class="font-display text-2xl font-bold text-ink">Près de moi</h1>

      <div class="flex flex-wrap items-center gap-3">
        <div class="flex gap-1 rounded-full bg-primary/8 p-1">
          <button
            v-for="mode in viewModes"
            :key="mode.value"
            type="button"
            class="rounded-full px-3 py-1 font-mono text-xs tracking-wide transition-colors"
            :class="viewMode === mode.value ? 'bg-surface text-ink shadow-sm' : 'text-ink/50 hover:text-ink'"
            @click="viewMode = mode.value"
          >
            {{ mode.label }}
          </button>
        </div>

        <label v-if="userPosition" class="flex items-center gap-2 font-mono text-xs text-ink/60">
          Rayon
          <select
            v-model.number="radiusKm"
            class="rounded-md border border-ink/15 bg-surface px-2 py-1 font-body text-sm text-ink"
            @change="fetchNearbyOffers"
          >
            <option v-for="option in radiusOptions" :key="option" :value="option">{{ option }} km</option>
          </select>
        </label>
      </div>
    </div>

    <p v-if="locating" class="font-mono text-sm text-ink/50">Localisation en cours…</p>
    <p v-else-if="locationError" class="rounded-md bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved">
      {{ locationError }}
    </p>

    <template v-else-if="userPosition">
      <p class="mb-3 font-mono text-xs text-ink/50">
        {{ loadingOffers ? 'Recherche des annonces…' : `${offers.length} annonce(s) dans un rayon de ${radiusKm} km` }}
      </p>

      <!-- RADAR -->
      <div v-if="viewMode === 'radar'" class="grid gap-6 md:grid-cols-2">
        <div class="relative h-[500px]">
          <OfferMap :center="userPosition" :radius-km="radiusKm" :offers="offers" @select="goToOffer" />
          <RadarOverlay />
        </div>

        <div class="flex flex-col gap-2">
          <p class="font-mono text-xs tracking-wide text-ink/40 uppercase">Triées par distance</p>
          <RouterLink
            v-for="offer in offers"
            :key="offer.id"
            :to="{ name: 'offer-details', params: { id: offer.id } }"
            class="flex items-center gap-3 rounded-md border border-ink/10 bg-surface p-3 transition hover:border-primary"
          >
            <span class="h-9 w-9 shrink-0 rounded-md bg-primary"></span>
            <span class="min-w-0 flex-1">
              <span class="block truncate font-body text-sm font-semibold text-ink">{{ offer.title }}</span>
              <span class="flex items-center gap-2">
                <span v-if="offer.distance != null" class="font-mono text-xs text-ink/50">
                  {{ formatDistance(offer.distance) }}
                </span>
                <span
                  class="rounded px-1.5 py-0.5 font-mono text-[0.6rem] text-surface uppercase"
                  :class="statusColor[offer.status]"
                >
                  {{ statusLabel[offer.status] }}
                </span>
              </span>
            </span>
            <span class="-rotate-2 shrink-0 rounded bg-accent px-2 py-0.5 font-mono text-xs font-bold text-ink">
              {{ formatPrice(offer.price) }} DH
            </span>
          </RouterLink>
        </div>
      </div>

      <!-- EXPLORATEUR -->
      <div v-else-if="viewMode === 'explorer'" class="grid gap-6 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
        <div class="flex max-h-[640px] flex-col gap-4 overflow-y-auto pr-1">
          <OfferCard
            v-for="offer in offers"
            :id="offer.id"
            :key="offer.id"
            class="shrink-0"
            :title="offer.title"
            :price="offer.price"
            :status="offer.status"
            :is-negotiable="offer.is_negotiable"
            :category="offer.category ?? null"
            :distance="offer.distance"
            :images="offer.images"
          />
        </div>
        <OfferMap
          class="sticky top-24 !h-[640px]"
          :center="userPosition"
          :radius-km="radiusKm"
          :offers="offers"
          @select="goToOffer"
        />
      </div>

      <!-- IMMERSIF -->
      <div v-else class="relative -mx-6 h-[70vh] overflow-hidden md:mx-0 md:rounded-md">
        <OfferMap
          class="!h-full !w-full !rounded-none md:!rounded-md"
          :center="userPosition"
          :radius-km="radiusKm"
          :offers="offers"
          @select="goToOffer"
        />

        <div
          class="absolute right-0 bottom-0 left-0 z-[1100] rounded-t-2xl bg-surface p-4 shadow-[0_-8px_24px_rgba(0,0,0,0.12)]"
        >
          <div class="mb-3 flex items-center justify-between">
            <p class="font-mono text-xs tracking-wide text-ink/40 uppercase">Autour de toi</p>
            <p class="font-mono text-xs text-ink/40">{{ offers.length }} annonce(s)</p>
          </div>
          <div class="flex gap-3 overflow-x-auto pb-1">
            <RouterLink
              v-for="offer in offers"
              :key="offer.id"
              :to="{ name: 'offer-details', params: { id: offer.id } }"
              class="flex w-44 shrink-0 flex-col gap-2 rounded-md border border-ink/10 bg-ground p-2 transition hover:border-primary"
            >
              <span class="h-20 w-full rounded bg-primary"></span>
              <span class="truncate font-body text-sm font-semibold text-ink">{{ offer.title }}</span>
              <span class="flex items-center justify-between">
                <span class="-rotate-2 rounded bg-accent px-2 py-0.5 font-mono text-xs font-bold text-ink">
                  {{ formatPrice(offer.price) }} DH
                </span>
                <span v-if="offer.distance != null" class="font-mono text-xs text-ink/50">
                  {{ formatDistance(offer.distance) }}
                </span>
              </span>
            </RouterLink>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
