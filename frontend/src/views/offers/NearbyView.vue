<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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

const searchQuery = ref('')

const carousel = ref<HTMLElement | null>(null)

const filteredOffers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  if (!query) {
    return offers.value
  }

  return offers.value.filter((offer) => {
    const title = offer.title?.toLowerCase() ?? ''
    const description = offer.description?.toLowerCase() ?? ''
    const category = offer.category?.name?.toLowerCase() ?? ''

    return (
      title.includes(query) ||
      description.includes(query) ||
      category.includes(query)
    )
  })
})

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
      locationError.value =
        "Impossible d'accéder à ta position. Autorise la géolocalisation."
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
  router.push({
    name: 'offer-details',
    params: { id: offerId },
  })
}

function clearSearch() {
  searchQuery.value = ''
}

function scrollCarousel(direction: 'left' | 'right') {
  if (!carousel.value) return

  const amount = carousel.value.clientWidth * 0.8

  carousel.value.scrollBy({
    left: direction === 'left' ? -amount : amount,
    behavior: 'smooth',
  })
}
</script>

<template>
  <div class="mx-auto max-w-6xl px-6 py-8">
    <!-- Header + compact toolbar -->
    <div class="mb-5 flex flex-col gap-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="font-display text-2xl font-bold text-ink">
          Près de moi
        </h1>

        <div
          class="flex flex-1 flex-wrap items-center justify-end gap-2"
        >
          <!-- Search -->
          <div
            class="flex h-9 w-full max-w-xs items-center rounded-md border border-ink/15 bg-surface px-3 transition-colors focus-within:border-primary"
          >
            <svg
              class="mr-2 h-4 w-4 shrink-0 text-ink/40"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <circle cx="11" cy="11" r="7" />
              <path d="m20 20-4-4" />
            </svg>

            <input
              v-model="searchQuery"
              type="search"
              placeholder="Rechercher autour de moi..."
              class="min-w-0 flex-1 bg-transparent text-sm text-ink outline-none placeholder:text-ink/40"
            />

            <button
              v-if="searchQuery"
              type="button"
              class="ml-2 text-ink/40 transition-colors hover:text-ink"
              aria-label="Effacer la recherche"
              @click="clearSearch"
            >
              ×
            </button>
          </div>

          <!-- Divider -->
          <div class="hidden h-7 w-px bg-ink/10 sm:block"></div>

          <!-- View modes -->
          <div class="flex gap-1 rounded-full bg-primary/8 p-1">
            <button
              v-for="mode in viewModes"
              :key="mode.value"
              type="button"
              class="rounded-full px-3 py-1.5 font-mono text-xs tracking-wide transition-colors"
              :class="
                viewMode === mode.value
                  ? 'bg-surface text-ink shadow-sm'
                  : 'text-ink/50 hover:text-ink'
              "
              @click="viewMode = mode.value"
            >
              {{ mode.label }}
            </button>
          </div>

          <!-- Divider -->
          <div class="hidden h-7 w-px bg-ink/10 sm:block"></div>

          <!-- Radius -->
          <label
            v-if="userPosition"
            class="flex h-9 items-center gap-2 rounded-md border border-ink/15 bg-surface px-3 font-mono text-xs text-ink/60"
          >
            Rayon

            <select
              v-model.number="radiusKm"
              class="bg-transparent font-body text-sm text-ink outline-none"
              @change="fetchNearbyOffers"
            >
              <option
                v-for="option in radiusOptions"
                :key="option"
                :value="option"
              >
                {{ option }} km
              </option>
            </select>
          </label>
        </div>
      </div>

      <!-- Search result summary -->
      <div
        v-if="userPosition && !locating"
        class="flex items-center justify-between border-b border-ink/10 pb-3"
      >
        <p class="font-mono text-xs text-ink/50">
          <template v-if="loadingOffers">
            Recherche des annonces…
          </template>

          <template v-else-if="searchQuery">
            {{ filteredOffers.length }} résultat(s) pour
            <span class="text-ink">"{{ searchQuery }}"</span>
          </template>

          <template v-else>
            {{ filteredOffers.length }} annonce(s) dans un rayon de
            {{ radiusKm }} km
          </template>
        </p>

        <button
          v-if="searchQuery"
          type="button"
          class="font-mono text-xs text-primary transition-opacity hover:opacity-70"
          @click="clearSearch"
        >
          Réinitialiser
        </button>
      </div>
    </div>

    <!-- Location states -->
    <p
      v-if="locating"
      class="font-mono text-sm text-ink/50"
    >
      Localisation en cours…
    </p>

    <p
      v-else-if="locationError"
      class="rounded-md bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved"
    >
      {{ locationError }}
    </p>

    <template v-else-if="userPosition">
      <!-- No results -->
      <div
        v-if="!loadingOffers && filteredOffers.length === 0"
        class="flex min-h-[360px] items-center justify-center rounded-xl border border-dashed border-ink/15 bg-surface-alt"
      >
        <div class="text-center">
          <p class="font-display text-lg font-semibold text-ink">
            Aucune annonce trouvée
          </p>

          <p class="mt-2 max-w-md text-sm text-ink/50">
            Essaie une autre recherche ou augmente le rayon autour de ta
            position.
          </p>

          <button
            v-if="searchQuery"
            type="button"
            class="mt-5 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-surface transition-opacity hover:opacity-90"
            @click="clearSearch"
          >
            Voir toutes les annonces
          </button>
        </div>
      </div>

      <template v-else>
        <!-- RADAR -->
        <div
          v-if="viewMode === 'radar'"
          class="grid gap-6 md:grid-cols-2"
        >
          <div class="relative h-[500px]">
            <OfferMap
              :center="userPosition"
              :radius-km="radiusKm"
              :offers="filteredOffers"
              @select="goToOffer"
            />

            <RadarOverlay />
          </div>

          <div class="flex flex-col gap-2">
            <p
              class="font-mono text-xs tracking-wide text-ink/40 uppercase"
            >
              Triées par distance
            </p>

            <RouterLink
              v-for="offer in filteredOffers"
              :key="offer.id"
              :to="{
                name: 'offer-details',
                params: { id: offer.id },
              }"
              class="flex items-center gap-3 rounded-md border border-ink/10 bg-surface p-3 transition hover:border-primary"
            >
              <span class="h-9 w-9 shrink-0 rounded-md bg-primary"></span>

              <span class="min-w-0 flex-1">
                <span
                  class="block truncate font-body text-sm font-semibold text-ink"
                >
                  {{ offer.title }}
                </span>

                <span class="flex items-center gap-2">
                  <span
                    v-if="offer.distance != null"
                    class="font-mono text-xs text-ink/50"
                  >
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

              <span
                class="-rotate-2 shrink-0 rounded bg-accent px-2 py-0.5 font-mono text-xs font-bold text-ink"
              >
                {{ formatPrice(offer.price) }} DH
              </span>
            </RouterLink>
          </div>
        </div>

        <!-- EXPLORATEUR -->
        <div
          v-else-if="viewMode === 'explorer'"
          class="grid gap-6 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]"
        >
          <div
            class="flex max-h-[640px] flex-col gap-4 overflow-y-auto pr-1"
          >
            <OfferCard
              v-for="offer in filteredOffers"
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
            :offers="filteredOffers"
            @select="goToOffer"
          />
        </div>

        <!-- IMMERSIVE -->
        <div
          v-else
          class="relative -mx-6 h-[70vh] overflow-hidden md:mx-0 md:rounded-xl"
        >
          <OfferMap
            class="!h-full !w-full !rounded-none md:!rounded-xl"
            :center="userPosition"
            :radius-km="radiusKm"
            :offers="filteredOffers"
            @select="goToOffer"
          />

          <!-- Carousel -->
          <div
            class="absolute right-0 bottom-0 left-0 z-[1100] border-t border-ink/10 bg-surface/95 shadow-[0_-8px_30px_rgba(0,0,0,0.12)] backdrop-blur-md"
          >
            <div class="px-4 pt-3 pb-4">
              <div class="mb-3 flex items-center justify-between">
                <div>
                  <p
                    class="font-mono text-xs tracking-wide text-ink/40 uppercase"
                  >
                    Autour de toi
                  </p>

                  <p class="mt-0.5 text-xs text-ink/50">
                    {{ filteredOffers.length }} annonce(s)
                  </p>
                </div>

                <div class="flex items-center gap-1">
                  <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full border border-ink/10 bg-surface text-ink/60 transition-colors hover:border-primary hover:text-primary"
                    aria-label="Annonces précédentes"
                    @click="scrollCarousel('left')"
                  >
                    ←
                  </button>

                  <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full border border-ink/10 bg-surface text-ink/60 transition-colors hover:border-primary hover:text-primary"
                    aria-label="Annonces suivantes"
                    @click="scrollCarousel('right')"
                  >
                    →
                  </button>
                </div>
              </div>

              <div
                ref="carousel"
                class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
              >
                <RouterLink
                  v-for="offer in filteredOffers"
                  :key="offer.id"
                  :to="{
                    name: 'offer-details',
                    params: { id: offer.id },
                  }"
                  class="group w-[230px] shrink-0 snap-start overflow-hidden rounded-lg border border-ink/10 bg-ground transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-md sm:w-[250px]"
                >
                  <!-- Image -->
                  <div class="relative h-28 overflow-hidden bg-primary/10">
                    <img
                      v-if="offer.images?.[0]?.url"
                      :src="offer.images[0].url"
                      :alt="offer.title"
                      class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />

                    <div
                      v-else
                      class="flex h-full items-center justify-center bg-primary/10"
                    >
                      <span
                        class="font-mono text-[0.65rem] tracking-wider text-primary/50 uppercase"
                      >
                        Qribly
                      </span>
                    </div>

                    <span
                      class="absolute top-2 right-2 rounded bg-accent px-2 py-0.5 font-mono text-xs font-bold text-ink shadow-sm"
                    >
                      {{ formatPrice(offer.price) }} DH
                    </span>
                  </div>

                  <!-- Content -->
                  <div class="p-3">
                    <p
                      class="truncate font-body text-sm font-semibold text-ink"
                    >
                      {{ offer.title }}
                    </p>

                    <div class="mt-2 flex items-center justify-between gap-2">
                      <span
                        v-if="offer.distance != null"
                        class="font-mono text-xs text-ink/50"
                      >
                        {{ formatDistance(offer.distance) }}
                      </span>

                      <span
                        v-if="offer.category"
                        class="truncate font-mono text-[0.65rem] text-ink/40"
                      >
                        {{ offer.category.name }}
                      </span>
                    </div>
                  </div>
                </RouterLink>
              </div>
            </div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>