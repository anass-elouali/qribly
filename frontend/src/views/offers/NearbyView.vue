<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import OfferMap from '@/components/offers/OfferMap.vue'
import type { Offer, PaginatedResponse } from '@/types/offer'

const router = useRouter()

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
  <div class="mx-auto max-w-5xl px-6 py-8">
    <div class="mb-4 flex items-center justify-between">
      <h1 class="font-display text-2xl font-bold text-ink">Près de moi</h1>

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

    <p v-if="locating" class="font-mono text-sm text-ink/50">Localisation en cours…</p>
    <p v-else-if="locationError" class="rounded-md bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved">
      {{ locationError }}
    </p>

    <template v-else-if="userPosition">
      <p class="mb-3 font-mono text-xs text-ink/50">
        {{ loadingOffers ? 'Recherche des annonces…' : `${offers.length} annonce(s) dans un rayon de ${radiusKm} km` }}
      </p>
      <OfferMap :center="userPosition" :radius-km="radiusKm" :offers="offers" @select="goToOffer" />
    </template>
  </div>
</template>
