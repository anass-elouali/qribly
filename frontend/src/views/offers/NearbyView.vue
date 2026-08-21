<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { SlidersHorizontal, X } from 'lucide-vue-next'
import api from '@/services/api'
import { fetchCategories } from '@/services/categories'
import OfferMap from '@/components/offers/OfferMap.vue'
import OfferCard from '@/components/offers/OfferCard.vue'
import OfferGridSkeleton from '@/components/offers/OfferGridSkeleton.vue'
import RadarOverlay from '@/components/offers/RadarOverlay.vue'
import AsyncStatePanel from '@/components/ui/AsyncStatePanel.vue'
import type { Category, Offer, PaginatedResponse } from '@/types/offer'
import { extractErrorMessage } from '@/utils/errors'
import { statusLabel, statusColor, formatPrice, formatDistance } from '@/utils/offer'

const router = useRouter()

type ViewMode = 'radar' | 'explorer' | 'immersive'
type BrowseScope = 'all' | 'nearby' | 'city'
type LocationErrorKind =
  'unsupported' | 'permission-denied' | 'position-unavailable' | 'timeout' | 'unknown'

type CityOption = {
  name: string
  latitude: number
  longitude: number
}

const viewModes: { value: ViewMode; label: string }[] = [
  { value: 'radar', label: 'Radar' },
  { value: 'explorer', label: 'Explorateur' },
  { value: 'immersive', label: 'Immersif' },
]

const cityOptions: CityOption[] = [
  { name: 'Agadir', latitude: 30.4278, longitude: -9.5981 },
  { name: 'Casablanca', latitude: 33.5731, longitude: -7.5898 },
  { name: 'Essaouira', latitude: 31.5085, longitude: -9.7595 },
  { name: 'Fès', latitude: 34.0181, longitude: -5.0078 },
  { name: 'Marrakech', latitude: 31.6295, longitude: -7.9811 },
  { name: 'Meknès', latitude: 33.8935, longitude: -5.5473 },
  { name: 'Oujda', latitude: 34.6814, longitude: -1.9086 },
  { name: 'Rabat', latitude: 34.0209, longitude: -6.8416 },
  { name: 'Tanger', latitude: 35.7595, longitude: -5.834 },
  { name: 'Tétouan', latitude: 35.5889, longitude: -5.3626 },
]

const viewMode = ref<ViewMode>('explorer')
const browseScope = ref<BrowseScope>('all')

const searchCenter = ref<{ latitude: number; longitude: number } | null>(null)
const radiusKm = ref(5)
const radiusOptions = [1, 5, 10, 25, 50]

const locating = ref(false)
const locationError = ref<LocationErrorKind | null>(null)
const cityQuery = ref('')
const cityError = ref('')
const selectedCityName = ref('')

const categories = ref<Category[]>([])
const selectedCategory = ref<number | null>(null)
const selectedType = ref<string | null>(null)
const selectedStatus = ref<string | null>(null)
const appliedMinPrice = ref<number | null>(null)
const appliedMaxPrice = ref<number | null>(null)
const categoriesLoading = ref(true)
const categoriesError = ref('')
const filtersOpen = ref(false)
const filterCloseButton = ref<HTMLButtonElement | null>(null)
const draftSelectedCategory = ref<number | null>(null)
const draftSelectedType = ref<string | null>(null)
const draftSelectedStatus = ref<string | null>(null)
const draftMinPrice = ref<number | null>(null)
const draftMaxPrice = ref<number | null>(null)

const offers = ref<Offer[]>([])
const loadingOffers = ref(false)
const offersError = ref('')

let offersRequestId = 0
let retryOffersRequest: (() => Promise<void>) | null = null

const searchQuery = ref('')

const carousel = ref<HTMLElement | null>(null)

const types = [
  {
    value: 'product',
    label: 'Produits',
  },
  {
    value: 'service',
    label: 'Services',
  },
]

const statuses = [
  {
    value: 'active',
    label: 'Disponible',
  },
  {
    value: 'reserved',
    label: 'Réservé',
  },
]

const filteredOffers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return offers.value.filter((offer) => {
    if (selectedType.value !== null && offer.type !== selectedType.value) {
      return false
    }

    if (selectedCategory.value !== null && offer.category?.id !== selectedCategory.value) {
      return false
    }

    if (selectedStatus.value !== null && offer.status !== selectedStatus.value) {
      return false
    }

    const price = Number(offer.price)

    if (appliedMinPrice.value !== null && price < appliedMinPrice.value) {
      return false
    }

    if (appliedMaxPrice.value !== null && price > appliedMaxPrice.value) {
      return false
    }

    if (!query) {
      return true
    }

    const title = offer.title?.toLowerCase() ?? ''
    const description = offer.description?.toLowerCase() ?? ''
    const category = offer.category?.name?.toLowerCase() ?? ''

    return title.includes(query) || description.includes(query) || category.includes(query)
  })
})

const canExpandRadius = computed(() => radiusKm.value < radiusOptions[radiusOptions.length - 1]!)

const isLocationScope = computed(() => browseScope.value !== 'all' && searchCenter.value !== null)
const userPosition = computed(() => (isLocationScope.value ? searchCenter.value : null))

const hasActiveFilters = computed(
  () =>
    searchQuery.value !== '' ||
    selectedCategory.value !== null ||
    selectedType.value !== null ||
    selectedStatus.value !== null ||
    appliedMinPrice.value !== null ||
    appliedMaxPrice.value !== null,
)

const activeFilterCount = computed(
  () =>
    Number(selectedType.value !== null) +
    Number(selectedCategory.value !== null) +
    Number(selectedStatus.value !== null) +
    Number(appliedMinPrice.value !== null || appliedMaxPrice.value !== null),
)

const draftActiveFilterCount = computed(
  () =>
    Number(draftSelectedType.value !== null) +
    Number(draftSelectedCategory.value !== null) +
    Number(draftSelectedStatus.value !== null) +
    Number(draftMinPrice.value !== null || draftMaxPrice.value !== null),
)

const draftPriceError = computed(() => {
  if (
    draftMinPrice.value !== null &&
    draftMaxPrice.value !== null &&
    draftMinPrice.value > draftMaxPrice.value
  ) {
    return 'Le prix minimum doit être inférieur au prix maximum.'
  }

  return ''
})

const draftResultCount = computed(() => {
  return offers.value.filter((offer) => {
    if (draftSelectedType.value !== null && offer.type !== draftSelectedType.value) {
      return false
    }

    if (
      draftSelectedCategory.value !== null &&
      offer.category?.id !== draftSelectedCategory.value
    ) {
      return false
    }

    if (draftSelectedStatus.value !== null && offer.status !== draftSelectedStatus.value) {
      return false
    }

    const price = Number(offer.price)

    if (draftMinPrice.value !== null && price < draftMinPrice.value) {
      return false
    }

    if (draftMaxPrice.value !== null && price > draftMaxPrice.value) {
      return false
    }

    const query = searchQuery.value.trim().toLowerCase()

    if (!query) {
      return true
    }

    const title = offer.title?.toLowerCase() ?? ''
    const description = offer.description?.toLowerCase() ?? ''
    const category = offer.category?.name?.toLowerCase() ?? ''

    return title.includes(query) || description.includes(query) || category.includes(query)
  }).length
})

const priceFilterLabel = computed(() => {
  if (appliedMinPrice.value !== null && appliedMaxPrice.value !== null) {
    return `${appliedMinPrice.value}–${appliedMaxPrice.value} DH`
  }

  if (appliedMinPrice.value !== null) {
    return `Dès ${appliedMinPrice.value} DH`
  }

  if (appliedMaxPrice.value !== null) {
    return `Jusqu’à ${appliedMaxPrice.value} DH`
  }

  return 'Prix'
})

const locationErrorMessage = computed(() => {
  switch (locationError.value) {
    case 'unsupported':
      return 'La géolocalisation n’est pas disponible sur ce navigateur.'
    case 'permission-denied':
      return 'L’accès à ta position a été refusé. Autorise la géolocalisation dans ton navigateur.'
    case 'position-unavailable':
      return 'Ta position est momentanément indisponible. Vérifie ta connexion ou réessaie plus tard.'
    case 'timeout':
      return 'La localisation prend trop de temps. Vérifie ton signal et réessaie.'
    case 'unknown':
      return 'Impossible de déterminer ta position pour le moment.'
    default:
      return ''
  }
})

function classifyLocationError(error: GeolocationPositionError): LocationErrorKind {
  switch (error.code) {
    case 1:
      return 'permission-denied'
    case 2:
      return 'position-unavailable'
    case 3:
      return 'timeout'
    default:
      return 'unknown'
  }
}

function requestUserPosition() {
  locating.value = true
  locationError.value = null
  cityError.value = ''

  if (!navigator.geolocation) {
    locationError.value = 'unsupported'
    locating.value = false
    return
  }

  navigator.geolocation.getCurrentPosition(
    (position) => {
      const center = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
      }

      locationError.value = null
      locating.value = false
      radiusKm.value = 5
      void loadOffersNear(center, 'nearby')
    },
    (error) => {
      locationError.value = classifyLocationError(error)
      locating.value = false
    },
    {
      enableHighAccuracy: false,
      timeout: 10_000,
      maximumAge: 60_000,
    },
  )
}

function normalizeCity(value: string) {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toLowerCase()
}

async function loadCategories() {
  categoriesLoading.value = true
  categoriesError.value = ''

  try {
    categories.value = await fetchCategories()
  } catch (exception) {
    categoriesError.value = extractErrorMessage(exception, 'Impossible de charger les catégories.')
  } finally {
    categoriesLoading.value = false
  }
}

function toggleCategory(category: number) {
  selectedCategory.value = selectedCategory.value === category ? null : category
}

function toggleType(type: string) {
  selectedType.value = selectedType.value === type ? null : type
}

function toggleStatus(status: string) {
  selectedStatus.value = selectedStatus.value === status ? null : status
}

function openFilters() {
  draftSelectedCategory.value = selectedCategory.value
  draftSelectedType.value = selectedType.value
  draftSelectedStatus.value = selectedStatus.value
  draftMinPrice.value = appliedMinPrice.value
  draftMaxPrice.value = appliedMaxPrice.value
  filtersOpen.value = true
}

function closeFilters() {
  filtersOpen.value = false
}

function applyFilters() {
  if (draftPriceError.value) return

  selectedCategory.value = draftSelectedCategory.value
  selectedType.value = draftSelectedType.value
  selectedStatus.value = draftSelectedStatus.value
  appliedMinPrice.value = draftMinPrice.value
  appliedMaxPrice.value = draftMaxPrice.value
  filtersOpen.value = false
}

function clearDraftFilters() {
  draftSelectedCategory.value = null
  draftSelectedType.value = null
  draftSelectedStatus.value = null
  draftMinPrice.value = null
  draftMaxPrice.value = null
}

function clearFilters() {
  searchQuery.value = ''
  selectedCategory.value = null
  selectedType.value = null
  selectedStatus.value = null
  appliedMinPrice.value = null
  appliedMaxPrice.value = null
  clearDraftFilters()
}

function searchByCity() {
  const query = normalizeCity(cityQuery.value)
  const matches = cityOptions.filter((city) => normalizeCity(city.name).includes(query))

  if (!query || matches.length !== 1) {
    cityError.value = 'Choisis une ville dans la liste proposée.'
    return
  }

  const city = matches[0]!
  cityQuery.value = city.name
  cityError.value = ''
  locationError.value = null
  radiusKm.value = 25

  void loadOffersNear(
    {
      latitude: city.latitude,
      longitude: city.longitude,
    },
    'city',
    city.name,
  )
}

async function fetchAllOffers() {
  const requestId = ++offersRequestId
  retryOffersRequest = fetchAllOffers
  loadingOffers.value = true
  offersError.value = ''

  try {
    const firstResponse = await api.get<PaginatedResponse<Offer>>('/offers', {
      params: { page: 1 },
    })
    const remainingPages = Array.from(
      { length: Math.max(0, firstResponse.data.meta.last_page - 1) },
      (_, index) => index + 2,
    )
    const remainingResponses = await Promise.all(
      remainingPages.map((page) =>
        api.get<PaginatedResponse<Offer>>('/offers', {
          params: { page },
        }),
      ),
    )

    if (requestId === offersRequestId) {
      offers.value = [
        ...firstResponse.data.data,
        ...remainingResponses.flatMap((response) => response.data.data),
      ]
      browseScope.value = 'all'
      searchCenter.value = null
      selectedCityName.value = ''
      cityQuery.value = ''
      locationError.value = null
      cityError.value = ''
      retryOffersRequest = null
    }
  } catch (exception) {
    if (requestId === offersRequestId) {
      offersError.value = extractErrorMessage(exception, 'Impossible de charger les annonces.')
    }
  } finally {
    if (requestId === offersRequestId) {
      loadingOffers.value = false
    }
  }
}

async function loadOffersNear(
  center: { latitude: number; longitude: number },
  targetScope: Exclude<BrowseScope, 'all'>,
  cityName = '',
) {
  const requestId = ++offersRequestId
  retryOffersRequest = () => loadOffersNear(center, targetScope, cityName)
  loadingOffers.value = true
  offersError.value = ''

  try {
    const response = await api.get<PaginatedResponse<Offer>>('/offers/nearby', {
      params: {
        latitude: center.latitude,
        longitude: center.longitude,
        radius: radiusKm.value,
      },
    })

    if (requestId === offersRequestId) {
      searchCenter.value = center
      browseScope.value = targetScope
      selectedCityName.value = cityName
      offers.value = response.data.data
      retryOffersRequest = null
    }
  } catch (exception) {
    if (requestId === offersRequestId) {
      offersError.value = extractErrorMessage(
        exception,
        'Impossible de charger les annonces pour cette zone.',
      )
    }
  } finally {
    if (requestId === offersRequestId) {
      loadingOffers.value = false
    }
  }
}

async function fetchNearbyOffers() {
  if (!searchCenter.value || browseScope.value === 'all') return

  await loadOffersNear(searchCenter.value, browseScope.value, selectedCityName.value)
}

function showAllOffers() {
  void fetchAllOffers()
}

function retryOffers() {
  if (retryOffersRequest) {
    void retryOffersRequest()
    return
  }

  if (browseScope.value === 'all') {
    void fetchAllOffers()
    return
  }

  void fetchNearbyOffers()
}

function expandRadius() {
  const nextRadius = radiusOptions.find((option) => option > radiusKm.value)

  if (!nextRadius) return

  radiusKm.value = nextRadius
  void fetchNearbyOffers()
}

let previousBodyOverflow = ''

watch(filtersOpen, (isOpen) => {
  if (isOpen) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    window.setTimeout(() => filterCloseButton.value?.focus(), 0)
    return
  }

  document.body.style.overflow = previousBodyOverflow
})

function handleFilterKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && filtersOpen.value) {
    closeFilters()
  }
}

onMounted(() => {
  void Promise.all([fetchAllOffers(), loadCategories()])
  document.addEventListener('keydown', handleFilterKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleFilterKeydown)
  document.body.style.overflow = previousBodyOverflow
})

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
  <div class="mx-auto max-w-7xl px-6 py-8">
    <!-- Header + compact toolbar -->
    <div class="mb-5 flex flex-col gap-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="font-display text-2xl font-bold text-ink">Près de moi</h1>

        <div class="flex flex-1 flex-wrap items-center justify-end gap-2">
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
              :disabled="loadingOffers"
              class="bg-transparent font-body text-sm text-ink outline-none"
              @change="fetchNearbyOffers"
            >
              <option v-for="option in radiusOptions" :key="option" :value="option">
                {{ option }} km
              </option>
            </select>
          </label>
        </div>
      </div>

      <div class="rounded-xl border border-ink/10 bg-surface p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-md px-4 py-2 text-sm font-semibold transition-colors"
              :class="
                browseScope === 'all'
                  ? 'bg-primary text-surface'
                  : 'border border-ink/15 text-ink hover:border-primary'
              "
              :disabled="loadingOffers && browseScope === 'all'"
              @click="showAllOffers"
            >
              Toutes les annonces
            </button>

            <button
              type="button"
              class="rounded-md border border-ink/15 px-4 py-2 text-sm font-semibold text-ink transition-colors hover:border-primary disabled:cursor-wait disabled:opacity-60"
              :class="browseScope === 'nearby' ? '!border-primary bg-primary/10 text-primary' : ''"
              :disabled="locating"
              @click="requestUserPosition"
            >
              {{ locating ? 'Localisation…' : 'Autour de moi' }}
            </button>
          </div>

          <form class="flex min-w-0 flex-1 gap-2 lg:justify-end" @submit.prevent="searchByCity">
            <label for="city-search" class="sr-only">Rechercher par ville</label>
            <input
              id="city-search"
              v-model="cityQuery"
              list="moroccan-cities"
              type="search"
              autocomplete="off"
              placeholder="Ville : Marrakech, Casablanca…"
              class="h-10 min-w-0 flex-1 rounded-md border border-ink/15 bg-ground px-3 text-sm text-ink outline-none transition focus:border-primary lg:max-w-sm"
              @input="cityError = ''"
            />
            <datalist id="moroccan-cities">
              <option v-for="city in cityOptions" :key="city.name" :value="city.name" />
            </datalist>
            <button
              type="submit"
              class="h-10 shrink-0 rounded-md bg-accent px-4 text-sm font-semibold text-ink transition-opacity hover:opacity-90"
            >
              Chercher
            </button>
          </form>
        </div>

        <p v-if="cityError" class="mt-3 text-sm text-status-reserved" role="alert">
          {{ cityError }}
        </p>
      </div>

      <nav
        aria-label="Filtres des annonces"
        class="sticky top-16 z-20 -mx-6 border-y border-ink/10 bg-surface/95 px-6 py-3 backdrop-blur-md md:mx-0 md:rounded-xl md:border"
      >
        <div class="flex min-w-0 items-center gap-2">
          <button
            type="button"
            class="relative flex h-9 shrink-0 items-center gap-2 rounded-full border border-ink/30 bg-surface px-4 text-sm font-semibold text-ink transition hover:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            :aria-label="
              activeFilterCount > 0
                ? `Filtres, ${activeFilterCount} actif(s)`
                : 'Ouvrir tous les filtres'
            "
            @click="openFilters"
          >
            <SlidersHorizontal :size="16" aria-hidden="true" />
            Filtres
            <span
              v-if="activeFilterCount > 0"
              class="absolute -top-2 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 font-mono text-[0.65rem] font-bold text-surface"
            >
              {{ activeFilterCount }}
            </span>
          </button>

          <span class="h-6 w-px shrink-0 bg-ink/10" aria-hidden="true"></span>

          <div
            role="group"
            aria-label="Filtres rapides"
            class="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
          >
            <button
              v-for="type in types"
              :key="type.value"
              type="button"
              class="h-9 shrink-0 rounded-full border px-4 text-sm transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
              :class="
                selectedType === type.value
                  ? 'border-primary bg-primary text-surface'
                  : 'border-ink/15 bg-surface text-ink/70 hover:border-ink/40 hover:text-ink'
              "
              :aria-pressed="selectedType === type.value"
              @click="toggleType(type.value)"
            >
              {{ type.label }}
            </button>

            <button
              v-for="status in statuses"
              :key="status.value"
              type="button"
              class="h-9 shrink-0 rounded-full border px-4 text-sm transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
              :class="
                selectedStatus === status.value
                  ? 'border-primary bg-primary text-surface'
                  : 'border-ink/15 bg-surface text-ink/70 hover:border-ink/40 hover:text-ink'
              "
              :aria-pressed="selectedStatus === status.value"
              @click="toggleStatus(status.value)"
            >
              {{ status.label }}
            </button>

            <span class="h-6 w-px shrink-0 bg-ink/10" aria-hidden="true"></span>

            <template v-if="categoriesLoading">
              <span
                v-for="index in 4"
                :key="index"
                class="h-9 shrink-0 animate-pulse rounded-full bg-ink/10 motion-reduce:animate-none"
                :class="index % 2 === 0 ? 'w-24' : 'w-20'"
                aria-hidden="true"
              ></span>
            </template>

            <template v-else-if="!categoriesError">
              <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="h-9 shrink-0 rounded-full border px-4 text-sm transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                :class="
                  selectedCategory === category.id
                    ? 'border-primary bg-primary text-surface'
                    : 'border-ink/15 bg-surface text-ink/70 hover:border-ink/40 hover:text-ink'
                "
                :aria-pressed="selectedCategory === category.id"
                @click="toggleCategory(category.id)"
              >
                {{ category.name }}
              </button>
            </template>

            <button
              type="button"
              class="h-9 shrink-0 rounded-full border px-4 text-sm transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
              :class="
                appliedMinPrice !== null || appliedMaxPrice !== null
                  ? 'border-primary bg-primary text-surface'
                  : 'border-ink/15 bg-surface text-ink/70 hover:border-ink/40 hover:text-ink'
              "
              @click="openFilters"
            >
              {{ priceFilterLabel }}
            </button>

            <button
              v-if="activeFilterCount > 0"
              type="button"
              class="h-9 shrink-0 px-2 text-sm font-semibold text-primary hover:underline"
              @click="clearFilters"
            >
              Tout effacer
            </button>
          </div>
        </div>
      </nav>

      <div class="flex items-center justify-between border-b border-ink/10 pb-3">
        <p class="font-mono text-xs text-ink/50">
          <template v-if="loadingOffers"> Actualisation des annonces… </template>
          <template v-else-if="offersError"> Actualisation interrompue </template>
          <template v-else-if="searchQuery">
            {{ filteredOffers.length }} résultat(s) pour
            <span class="text-ink">"{{ searchQuery }}"</span>
          </template>
          <template v-else-if="browseScope === 'city'">
            {{ filteredOffers.length }} annonce(s) autour de {{ selectedCityName }} dans un rayon de
            {{ radiusKm }} km
          </template>
          <template v-else-if="browseScope === 'nearby'">
            {{ filteredOffers.length }} annonce(s) autour de toi dans un rayon de {{ radiusKm }} km
          </template>
          <template v-else> {{ filteredOffers.length }} annonce(s) disponibles </template>
        </p>

        <button
          v-if="searchQuery"
          type="button"
          class="font-mono text-xs text-primary hover:underline"
          @click="clearSearch"
        >
          Effacer la recherche
        </button>
        <button
          v-else-if="browseScope !== 'all'"
          type="button"
          class="font-mono text-xs text-primary hover:underline"
          @click="showAllOffers"
        >
          Voir toutes les annonces
        </button>
      </div>
    </div>

    <p v-if="locating" class="mb-5 font-mono text-sm text-ink/50" role="status">
      Localisation en cours… Les annonces restent disponibles.
    </p>

    <p
      v-if="locationError"
      class="mb-5 rounded-md bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved"
      role="alert"
    >
      {{ locationErrorMessage }} Tu peux toujours rechercher une ville.
    </p>

    <main class="min-w-0 flex-1">
      <OfferGridSkeleton v-if="loadingOffers && offers.length === 0" />

      <AsyncStatePanel
        v-else-if="offersError && offers.length === 0"
        variant="error"
        title="Les annonces ne sont pas disponibles"
        :message="offersError"
        action-label="Réessayer"
        @action="retryOffers"
      />

      <template v-else>
        <AsyncStatePanel
          v-if="offersError"
          class="mb-5"
          variant="error"
          title="Actualisation impossible"
          :message="offersError"
          action-label="Réessayer"
          compact
          @action="retryOffers"
        />

        <div
          v-if="!loadingOffers && filteredOffers.length === 0"
          class="flex min-h-[360px] items-center justify-center rounded-xl border border-dashed border-ink/15"
        >
          <div class="px-6 text-center">
            <p class="font-display text-lg font-semibold text-ink">Aucune annonce trouvée</p>
            <p class="mt-2 max-w-md text-sm text-ink/50">
              <template v-if="hasActiveFilters">Élargis ou efface les filtres.</template>
              <template v-else-if="browseScope === 'all'"
                >Reviens bientôt pour découvrir les nouveautés.</template
              >
              <template v-else>Augmente le rayon ou affiche toutes les annonces.</template>
            </p>
            <button
              v-if="hasActiveFilters"
              type="button"
              class="mt-4 text-sm font-semibold text-primary hover:underline"
              @click="clearFilters"
            >
              Effacer les filtres
            </button>
            <button
              v-else-if="canExpandRadius && browseScope !== 'all'"
              type="button"
              class="mt-4 text-sm font-semibold text-primary hover:underline"
              @click="expandRadius"
            >
              Élargir le rayon
            </button>
          </div>
        </div>

        <div
          v-else-if="browseScope === 'all'"
          class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3"
          :class="loadingOffers ? 'pointer-events-none opacity-60' : ''"
          :aria-busy="loadingOffers"
        >
          <OfferCard
            v-for="offer in filteredOffers"
            :id="offer.id"
            :key="offer.id"
            :title="offer.title"
            :price="offer.price"
            :status="offer.status"
            :is-negotiable="offer.is_negotiable"
            :category="offer.category ?? null"
            :images="offer.images"
          />
        </div>

        <template v-else-if="searchCenter">
          <!-- RADAR -->
          <div v-if="viewMode === 'radar'" class="grid gap-6 md:grid-cols-2">
            <div class="relative h-[500px]">
              <OfferMap
                :center="searchCenter"
                :radius-km="radiusKm"
                :offers="filteredOffers"
                @select="goToOffer"
              />

              <RadarOverlay />
            </div>

            <div class="flex flex-col gap-2">
              <p class="font-mono text-xs tracking-wide text-ink/40 uppercase">
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
                  <span class="block truncate font-body text-sm font-semibold text-ink">
                    {{ offer.title }}
                  </span>

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
            <div class="flex max-h-[640px] flex-col gap-4 overflow-y-auto pr-1">
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
              :center="searchCenter"
              :radius-km="radiusKm"
              :offers="filteredOffers"
              @select="goToOffer"
            />
          </div>

          <!-- IMMERSIVE -->
          <div v-else class="relative -mx-6 h-[70vh] overflow-hidden md:mx-0 md:rounded-xl">
            <OfferMap
              class="!h-full !w-full !rounded-none md:!rounded-xl"
              :center="searchCenter"
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
                    <p class="font-mono text-xs tracking-wide text-ink/40 uppercase">
                      Autour de toi
                    </p>

                    <p class="mt-0.5 text-xs text-ink/50">{{ filteredOffers.length }} annonce(s)</p>
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

                      <div v-else class="flex h-full items-center justify-center bg-primary/10">
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
                      <p class="truncate font-body text-sm font-semibold text-ink">
                        {{ offer.title }}
                      </p>

                      <div class="mt-2 flex items-center justify-between gap-2">
                        <span v-if="offer.distance != null" class="font-mono text-xs text-ink/50">
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
    </main>

    <Teleport to="body">
      <div
        v-if="filtersOpen"
        class="fixed inset-0 z-[2000] flex items-end bg-ink/40 sm:items-center sm:justify-center sm:p-6"
        @click.self="closeFilters"
      >
        <section
          role="dialog"
          aria-modal="true"
          aria-labelledby="nearby-filters-title"
          class="flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-3xl border border-ink/10 bg-surface shadow-2xl sm:max-w-2xl sm:rounded-3xl"
        >
          <header
            class="relative flex h-16 shrink-0 items-center justify-center border-b border-ink/10 px-6"
          >
            <button
              ref="filterCloseButton"
              type="button"
              class="absolute left-5 flex h-9 w-9 items-center justify-center rounded-full text-ink transition hover:bg-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
              aria-label="Fermer les filtres"
              @click="closeFilters"
            >
              <X :size="20" aria-hidden="true" />
            </button>

            <h2 id="nearby-filters-title" class="font-display text-lg font-semibold text-ink">
              Filtres
            </h2>
          </header>

          <div class="overflow-y-auto px-6 py-1 sm:px-8">
            <section class="border-b border-ink/10 py-6">
              <h3 class="font-display text-xl font-semibold text-ink">Type</h3>
              <p class="mt-1 text-sm text-ink/50">Choisis le type d’annonce à afficher.</p>

              <div class="mt-4 grid grid-cols-3 gap-2">
                <button
                  type="button"
                  class="rounded-xl border px-3 py-3 text-sm font-medium transition"
                  :class="
                    draftSelectedType === null
                      ? 'border-primary bg-primary/10 text-primary ring-1 ring-primary'
                      : 'border-ink/15 text-ink hover:border-ink/40'
                  "
                  :aria-pressed="draftSelectedType === null"
                  @click="draftSelectedType = null"
                >
                  Tous
                </button>
                <button
                  v-for="type in types"
                  :key="type.value"
                  type="button"
                  class="rounded-xl border px-3 py-3 text-sm font-medium transition"
                  :class="
                    draftSelectedType === type.value
                      ? 'border-primary bg-primary/10 text-primary ring-1 ring-primary'
                      : 'border-ink/15 text-ink hover:border-ink/40'
                  "
                  :aria-pressed="draftSelectedType === type.value"
                  @click="draftSelectedType = type.value"
                >
                  {{ type.label }}
                </button>
              </div>
            </section>

            <section class="border-b border-ink/10 py-6">
              <h3 class="font-display text-xl font-semibold text-ink">Catégorie</h3>
              <p class="mt-1 text-sm text-ink/50">Affiche uniquement une catégorie.</p>

              <div class="mt-4 flex flex-wrap gap-2">
                <template v-if="categoriesLoading">
                  <span
                    v-for="index in 6"
                    :key="index"
                    class="h-10 animate-pulse rounded-full bg-ink/10 motion-reduce:animate-none"
                    :class="index % 2 === 0 ? 'w-28' : 'w-24'"
                    aria-hidden="true"
                  ></span>
                </template>

                <div
                  v-else-if="categoriesError"
                  class="flex w-full items-center justify-between gap-4 rounded-xl bg-status-reserved/10 p-4"
                  role="alert"
                >
                  <p class="text-sm text-status-reserved">{{ categoriesError }}</p>
                  <button
                    type="button"
                    class="shrink-0 text-sm font-semibold text-primary hover:underline"
                    @click="loadCategories"
                  >
                    Réessayer
                  </button>
                </div>

                <template v-else>
                  <button
                    type="button"
                    class="rounded-full border px-4 py-2 text-sm transition"
                    :class="
                      draftSelectedCategory === null
                        ? 'border-primary bg-primary text-surface'
                        : 'border-ink/15 text-ink/70 hover:border-ink/40 hover:text-ink'
                    "
                    :aria-pressed="draftSelectedCategory === null"
                    @click="draftSelectedCategory = null"
                  >
                    Toutes
                  </button>
                  <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="rounded-full border px-4 py-2 text-sm transition"
                    :class="
                      draftSelectedCategory === category.id
                        ? 'border-primary bg-primary text-surface'
                        : 'border-ink/15 text-ink/70 hover:border-ink/40 hover:text-ink'
                    "
                    :aria-pressed="draftSelectedCategory === category.id"
                    @click="draftSelectedCategory = category.id"
                  >
                    {{ category.name }}
                  </button>
                </template>
              </div>
            </section>

            <section class="border-b border-ink/10 py-6">
              <h3 class="font-display text-xl font-semibold text-ink">Statut</h3>
              <p class="mt-1 text-sm text-ink/50">Vérifie immédiatement la disponibilité.</p>

              <div class="mt-4 grid grid-cols-3 gap-2">
                <button
                  type="button"
                  class="rounded-xl border px-3 py-3 text-sm font-medium transition"
                  :class="
                    draftSelectedStatus === null
                      ? 'border-primary bg-primary/10 text-primary ring-1 ring-primary'
                      : 'border-ink/15 text-ink hover:border-ink/40'
                  "
                  :aria-pressed="draftSelectedStatus === null"
                  @click="draftSelectedStatus = null"
                >
                  Tous
                </button>
                <button
                  v-for="status in statuses"
                  :key="status.value"
                  type="button"
                  class="rounded-xl border px-3 py-3 text-sm font-medium transition"
                  :class="
                    draftSelectedStatus === status.value
                      ? 'border-primary bg-primary/10 text-primary ring-1 ring-primary'
                      : 'border-ink/15 text-ink hover:border-ink/40'
                  "
                  :aria-pressed="draftSelectedStatus === status.value"
                  @click="draftSelectedStatus = status.value"
                >
                  {{ status.label }}
                </button>
              </div>
            </section>

            <section class="py-6">
              <h3 class="font-display text-xl font-semibold text-ink">Fourchette de prix</h3>
              <p class="mt-1 text-sm text-ink/50">Indique un prix minimum et maximum en dirhams.</p>

              <div class="mt-4 grid grid-cols-[1fr_auto_1fr] items-center gap-3">
                <label
                  class="rounded-xl border border-ink/15 px-4 py-2 focus-within:border-primary"
                >
                  <span class="block text-xs text-ink/45">Minimum</span>
                  <span class="mt-0.5 flex items-center gap-2">
                    <input
                      v-model.number="draftMinPrice"
                      type="number"
                      min="0"
                      inputmode="numeric"
                      placeholder="0"
                      class="min-w-0 flex-1 bg-transparent text-sm font-medium text-ink outline-none"
                    />
                    <span class="text-xs text-ink/50">DH</span>
                  </span>
                </label>

                <span class="text-ink/30" aria-hidden="true">—</span>

                <label
                  class="rounded-xl border border-ink/15 px-4 py-2 focus-within:border-primary"
                >
                  <span class="block text-xs text-ink/45">Maximum</span>
                  <span class="mt-0.5 flex items-center gap-2">
                    <input
                      v-model.number="draftMaxPrice"
                      type="number"
                      min="0"
                      inputmode="numeric"
                      placeholder="Aucun"
                      class="min-w-0 flex-1 bg-transparent text-sm font-medium text-ink outline-none"
                    />
                    <span class="text-xs text-ink/50">DH</span>
                  </span>
                </label>
              </div>

              <p v-if="draftPriceError" class="mt-3 text-sm text-status-reserved" role="alert">
                {{ draftPriceError }}
              </p>
            </section>
          </div>

          <footer
            class="flex shrink-0 items-center justify-between gap-4 border-t border-ink/10 bg-surface px-6 py-4 sm:px-8"
          >
            <button
              type="button"
              class="text-sm font-semibold text-ink underline underline-offset-4 transition hover:text-primary disabled:cursor-not-allowed disabled:opacity-35"
              :disabled="draftActiveFilterCount === 0"
              @click="clearDraftFilters"
            >
              Tout effacer
            </button>

            <button
              type="button"
              class="rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-surface transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="Boolean(draftPriceError)"
              @click="applyFilters"
            >
              Afficher {{ draftResultCount }} annonce(s)
            </button>
          </footer>
        </section>
      </div>
    </Teleport>
  </div>
</template>
