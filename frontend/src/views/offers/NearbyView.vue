<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { isAxiosError } from 'axios'
import { ChevronDown, MapPin, SlidersHorizontal, X } from 'lucide-vue-next'
import api from '@/services/api'
import { fetchCategories } from '@/services/categories'
import OfferMap from '@/components/offers/OfferMap.vue'
import OfferCard from '@/components/offers/OfferCard.vue'
import OfferGridSkeleton from '@/components/offers/OfferGridSkeleton.vue'
import RadarOverlay from '@/components/offers/RadarOverlay.vue'
import AsyncStatePanel from '@/components/ui/AsyncStatePanel.vue'
import LocationScopeCombobox from '@/components/ui/LocationScopeCombobox.vue'
import { cityByName, type CityOption } from '@/data/moroccanCities'
import type { Category, Offer, PaginatedResponse } from '@/types/offer'
import { extractErrorMessage } from '@/utils/errors'
import { statusLabel, statusColor, formatPrice, formatDistance } from '@/utils/offer'
import { resolveStorageUrl } from '@/utils/url'

const router = useRouter()

type ViewMode = 'radar' | 'explorer' | 'immersive'
type BrowseScope = 'all' | 'nearby' | 'city'
type CityAreaMode = 'city' | 'surroundings'
type LocationErrorKind =
  'unsupported' | 'permission-denied' | 'position-unavailable' | 'timeout' | 'unknown'

const viewModes: { value: ViewMode; label: string }[] = [
  { value: 'radar', label: 'Distances' },
  { value: 'explorer', label: 'Liste + carte' },
  { value: 'immersive', label: 'Carte' },
]

const viewMode = ref<ViewMode>('explorer')
const browseScope = ref<BrowseScope>('all')

const searchCenter = ref<{ latitude: number; longitude: number } | null>(null)
const radiusKm = ref(5)
const draftRadiusKm = ref(5)
const radiusOptions = [5, 10, 25, 50, 100]

const locating = ref(false)
const locationError = ref<LocationErrorKind | null>(null)
const selectedCity = ref<CityOption | null>(null)
const selectedCityName = ref('')
const cityAreaMode = ref<CityAreaMode>('city')

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

const OFFERS_REQUEST_TIMEOUT_MS = 30_000

function isTransientOffersError(error: unknown) {
  if (!isAxiosError(error)) return false

  const status = error.response?.status

  return (
    error.code === 'ECONNABORTED' ||
    error.code === 'ETIMEDOUT' ||
    !error.response ||
    (status !== undefined && status >= 500)
  )
}

async function getOffersPage(params: Record<string, string | number>) {
  const request = () =>
    api.get<PaginatedResponse<Offer>>('/offers', {
      params,
      timeout: OFFERS_REQUEST_TIMEOUT_MS,
    })

  try {
    return await request()
  } catch (error) {
    if (!isTransientOffersError(error)) throw error

    await new Promise((resolve) => window.setTimeout(resolve, 400))

    return request()
  }
}

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

const isRadiusSearch = computed(
  () => browseScope.value === 'nearby' || cityAreaMode.value === 'surroundings',
)

const locationSelectionKey = computed(() => {
  if (locating.value || browseScope.value === 'nearby') return 'nearby'

  if (browseScope.value === 'city' && selectedCity.value) {
    return `city:${selectedCity.value.name}`
  }

  return 'all'
})

const availableViewModes = computed(() => {
  if (browseScope.value === 'all') return []

  if (browseScope.value === 'city') {
    return cityAreaMode.value === 'surroundings'
      ? viewModes
      : viewModes.filter((mode) => mode.value !== 'radar')
  }

  return viewModes
})

const searchPlaceholder = computed(() => {
  if (browseScope.value === 'city' && selectedCityName.value) {
    return cityAreaMode.value === 'surroundings'
      ? `Rechercher à ${selectedCityName.value} et aux alentours…`
      : `Rechercher à ${selectedCityName.value}…`
  }

  if (browseScope.value === 'nearby') {
    return 'Rechercher autour de ta position…'
  }

  return 'Rechercher parmi toutes les annonces…'
})

const resultsSummary = computed(() => {
  let summary = `${filteredOffers.value.length} annonce(s)`

  if (browseScope.value === 'city' && selectedCityName.value) {
    summary +=
      cityAreaMode.value === 'surroundings'
        ? ` à moins de ${radiusKm.value} km de ${selectedCityName.value}`
        : ` à ${selectedCityName.value}`
  } else if (browseScope.value === 'nearby') {
    summary += ` autour de ta position dans un rayon de ${radiusKm.value} km`
  } else {
    summary += ' partout au Maroc'
  }

  const query = searchQuery.value.trim()

  return query ? `${summary} pour « ${query} »` : summary
})

const mapRadiusKm = computed(() => (isRadiusSearch.value ? radiusKm.value : null))

const locationContextLabel = computed(() =>
  browseScope.value === 'city' && selectedCityName.value
    ? cityAreaMode.value === 'surroundings'
      ? `${selectedCityName.value} et alentours`
      : `À ${selectedCityName.value}`
    : 'Autour de toi',
)

const radiusDraftChanged = computed(
  () => isRadiusSearch.value && draftRadiusKm.value !== radiusKm.value,
)

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

type AppliedFilterKey = 'category' | 'type' | 'status' | 'price'

const appliedFilterChips = computed<{ key: AppliedFilterKey; label: string }[]>(() => {
  const chips: { key: AppliedFilterKey; label: string }[] = []

  if (selectedCategory.value !== null) {
    const category = categories.value.find((item) => item.id === selectedCategory.value)

    if (category) chips.push({ key: 'category', label: category.name })
  }

  if (selectedType.value !== null) {
    const type = types.find((item) => item.value === selectedType.value)

    if (type) chips.push({ key: 'type', label: type.label })
  }

  if (selectedStatus.value !== null) {
    const status = statuses.find((item) => item.value === selectedStatus.value)

    if (status) chips.push({ key: 'status', label: status.label })
  }

  if (appliedMinPrice.value !== null || appliedMaxPrice.value !== null) {
    chips.push({ key: 'price', label: priceFilterLabel.value })
  }

  return chips
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

function openFilters() {
  draftSelectedCategory.value = selectedCategory.value
  draftSelectedType.value = selectedType.value
  draftSelectedStatus.value = selectedStatus.value
  draftMinPrice.value = appliedMinPrice.value
  draftMaxPrice.value = appliedMaxPrice.value
  draftRadiusKm.value = radiusKm.value
  filtersOpen.value = true
}

function closeFilters() {
  filtersOpen.value = false
}

function applyFilters() {
  if (draftPriceError.value) return

  const shouldRefreshRadius = radiusDraftChanged.value

  selectedCategory.value = draftSelectedCategory.value
  selectedType.value = draftSelectedType.value
  selectedStatus.value = draftSelectedStatus.value
  appliedMinPrice.value = draftMinPrice.value
  appliedMaxPrice.value = draftMaxPrice.value
  radiusKm.value = draftRadiusKm.value
  filtersOpen.value = false

  if (shouldRefreshRadius) void refreshRadiusOffers()
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

function clearAppliedFilter(key: AppliedFilterKey) {
  switch (key) {
    case 'category':
      selectedCategory.value = null
      break
    case 'type':
      selectedType.value = null
      break
    case 'status':
      selectedStatus.value = null
      break
    case 'price':
      appliedMinPrice.value = null
      appliedMaxPrice.value = null
      break
  }
}

function clearAppliedFilters() {
  selectedCategory.value = null
  selectedType.value = null
  selectedStatus.value = null
  appliedMinPrice.value = null
  appliedMaxPrice.value = null
}

function selectCity(city: CityOption | null) {
  selectedCity.value = city

  if (!city) {
    if (browseScope.value === 'city') void fetchAllOffers()
    return
  }

  locationError.value = null
  cityAreaMode.value = 'city'
  radiusKm.value = 50
  void fetchOffersByCity(city)
}

function selectLocationScope(key: string) {
  if (key === 'all') {
    showAllOffers()
    return
  }

  if (key === 'nearby') {
    requestUserPosition()
    return
  }

  if (!key.startsWith('city:')) return

  const city = cityByName(key.slice(5))

  if (city) selectCity(city)
}

function setCityAreaMode(mode: CityAreaMode) {
  if (!selectedCity.value || cityAreaMode.value === mode) return

  cityAreaMode.value = mode

  if (mode === 'city') {
    void fetchOffersByCity(selectedCity.value)
    return
  }

  void loadOffersNear(
    {
      latitude: selectedCity.value.latitude,
      longitude: selectedCity.value.longitude,
    },
    'city',
    selectedCity.value,
  )
}

async function fetchAllOffers() {
  const requestId = ++offersRequestId
  retryOffersRequest = fetchAllOffers
  loadingOffers.value = true
  offersError.value = ''

  try {
    const firstResponse = await getOffersPage({ page: 1, per_page: 100 })
    const remainingPages = Array.from(
      { length: Math.max(0, firstResponse.data.meta.last_page - 1) },
      (_, index) => index + 2,
    )
    const remainingResponses = await Promise.all(
      remainingPages.map((page) => getOffersPage({ page, per_page: 100 })),
    )

    if (requestId === offersRequestId) {
      offers.value = [
        ...firstResponse.data.data,
        ...remainingResponses.flatMap((response) => response.data.data),
      ]
      browseScope.value = 'all'
      searchCenter.value = null
      selectedCityName.value = ''
      selectedCity.value = null
      cityAreaMode.value = 'city'
      locationError.value = null
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

async function fetchOffersByCity(city: CityOption) {
  const requestId = ++offersRequestId
  retryOffersRequest = () => fetchOffersByCity(city)
  loadingOffers.value = true
  offersError.value = ''

  try {
    const firstResponse = await getOffersPage({ city: city.name, page: 1, per_page: 100 })
    const remainingPages = Array.from(
      { length: Math.max(0, firstResponse.data.meta.last_page - 1) },
      (_, index) => index + 2,
    )
    const remainingResponses = await Promise.all(
      remainingPages.map((page) => getOffersPage({ city: city.name, page, per_page: 100 })),
    )

    if (requestId === offersRequestId) {
      offers.value = [
        ...firstResponse.data.data,
        ...remainingResponses.flatMap((response) => response.data.data),
      ]
      browseScope.value = 'city'
      searchCenter.value = {
        latitude: city.latitude,
        longitude: city.longitude,
      }
      selectedCity.value = city
      selectedCityName.value = city.name
      cityAreaMode.value = 'city'
      retryOffersRequest = null
    }
  } catch (exception) {
    if (requestId === offersRequestId) {
      offersError.value = extractErrorMessage(
        exception,
        `Impossible de charger les annonces à ${city.name}.`,
      )
    }
  } finally {
    if (requestId === offersRequestId) {
      loadingOffers.value = false
    }
  }
}

async function loadOffersNear(
  center: { latitude: number; longitude: number },
  targetScope: 'nearby' | 'city',
  city: CityOption | null = null,
) {
  const requestId = ++offersRequestId
  retryOffersRequest = () => loadOffersNear(center, targetScope, city)
  loadingOffers.value = true
  offersError.value = ''

  try {
    const params = {
      latitude: center.latitude,
      longitude: center.longitude,
      radius: radiusKm.value,
      per_page: 100,
    }
    const firstResponse = await api.get<PaginatedResponse<Offer>>('/offers/nearby', {
      params: {
        ...params,
        page: 1,
      },
    })
    const remainingPages = Array.from(
      { length: Math.max(0, firstResponse.data.meta.last_page - 1) },
      (_, index) => index + 2,
    )
    const remainingResponses = await Promise.all(
      remainingPages.map((page) =>
        api.get<PaginatedResponse<Offer>>('/offers/nearby', {
          params: {
            ...params,
            page,
          },
        }),
      ),
    )

    if (requestId === offersRequestId) {
      searchCenter.value = center
      browseScope.value = targetScope

      if (targetScope === 'city' && city) {
        selectedCityName.value = city.name
        selectedCity.value = city
        cityAreaMode.value = 'surroundings'
      } else {
        selectedCityName.value = ''
        selectedCity.value = null
        cityAreaMode.value = 'city'
      }
      offers.value = [
        ...firstResponse.data.data,
        ...remainingResponses.flatMap((response) => response.data.data),
      ]
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
  if (!searchCenter.value || browseScope.value !== 'nearby') return

  await loadOffersNear(searchCenter.value, 'nearby')
}

async function refreshRadiusOffers() {
  if (browseScope.value === 'nearby') {
    await fetchNearbyOffers()
    return
  }

  if (browseScope.value === 'city' && cityAreaMode.value === 'surroundings' && selectedCity.value) {
    await loadOffersNear(
      {
        latitude: selectedCity.value.latitude,
        longitude: selectedCity.value.longitude,
      },
      'city',
      selectedCity.value,
    )
  }
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

  if (browseScope.value === 'city' && selectedCity.value) {
    if (cityAreaMode.value === 'surroundings') {
      void refreshRadiusOffers()
    } else {
      void fetchOffersByCity(selectedCity.value)
    }
    return
  }

  void fetchNearbyOffers()
}

function expandRadius() {
  const nextRadius = radiusOptions.find((option) => option > radiusKm.value)

  if (!nextRadius) return

  radiusKm.value = nextRadius
  void refreshRadiusOffers()
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

watch([browseScope, cityAreaMode], () => {
  if (!availableViewModes.value.some((mode) => mode.value === viewMode.value)) {
    viewMode.value = 'explorer'
  }
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
  <div class="mx-auto max-w-7xl overflow-x-clip px-6 py-8">
    <!-- Header + compact toolbar -->
    <div class="mb-5 flex flex-col gap-4">
      <div>
        <h1 class="font-display text-2xl font-bold text-ink">Découvrir les annonces</h1>
        <p class="mt-1 text-sm text-ink/55">
          Explore les offres partout au Maroc, dans une ville ou autour de ta position.
        </p>
      </div>

      <section class="rounded-2xl border border-ink/10 bg-surface p-4 sm:p-5">
        <div class="grid gap-4 lg:grid-cols-2">
          <div class="min-w-0">
            <label
              for="nearby-search"
              class="mb-2 block font-mono text-xs font-semibold tracking-wide text-ink/50 uppercase"
            >
              Que cherches-tu ?
            </label>

            <div
              class="flex h-12 w-full items-center rounded-xl border border-ink/15 bg-ground px-4 transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15"
            >
              <svg
                class="mr-3 h-4 w-4 shrink-0 text-ink/40"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
              >
                <circle cx="11" cy="11" r="7" />
                <path d="m20 20-4-4" />
              </svg>

              <input
                id="nearby-search"
                v-model="searchQuery"
                type="search"
                :placeholder="searchPlaceholder"
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
          </div>

          <div class="min-w-0">
            <p class="mb-2 font-mono text-xs font-semibold tracking-wide text-ink/50 uppercase">
              Où chercher ?
            </p>
            <LocationScopeCombobox
              id="location-scope-filter"
              :model-value="locationSelectionKey"
              :disabled="locating"
              label="Où chercher ?"
              @update:model-value="selectLocationScope"
            />
          </div>
        </div>

        <div v-if="browseScope !== 'all'" class="mt-4 border-t border-ink/8 pt-4">
          <div
            v-if="browseScope === 'city' && selectedCity"
            class="flex min-w-0 flex-col gap-2 rounded-xl border border-ink/8 bg-ground p-2 sm:flex-row sm:items-center"
          >
            <span
              class="shrink-0 px-2 font-mono text-[0.7rem] font-semibold tracking-wide text-ink/50 uppercase"
            >
              Zone
            </span>

            <div
              class="grid min-w-0 flex-1 grid-cols-2 gap-1 rounded-lg bg-surface p-1"
              role="group"
              :aria-label="`Zone de recherche autour de ${selectedCity.name}`"
            >
              <button
                type="button"
                class="min-h-9 rounded-md px-3 py-2 text-xs font-semibold transition-colors sm:text-sm"
                :class="
                  cityAreaMode === 'city'
                    ? 'bg-primary text-surface shadow-sm'
                    : 'text-ink/60 hover:text-ink'
                "
                :aria-pressed="cityAreaMode === 'city'"
                :disabled="loadingOffers && cityAreaMode === 'city'"
                @click="setCityAreaMode('city')"
              >
                Dans {{ selectedCity.name }}
              </button>
              <button
                type="button"
                class="min-h-9 rounded-md px-3 py-2 text-xs font-semibold transition-colors sm:text-sm"
                :class="
                  cityAreaMode === 'surroundings'
                    ? 'bg-primary text-surface shadow-sm'
                    : 'text-ink/60 hover:text-ink'
                "
                :aria-pressed="cityAreaMode === 'surroundings'"
                :disabled="loadingOffers && cityAreaMode === 'surroundings'"
                @click="setCityAreaMode('surroundings')"
              >
                {{ selectedCity.name }} et alentours
              </button>
            </div>

            <label
              v-if="cityAreaMode === 'surroundings'"
              class="flex shrink-0 items-center justify-between gap-2 px-2 sm:justify-start"
            >
              <span
                class="font-mono text-[0.7rem] font-semibold tracking-wide text-ink/50 uppercase"
              >
                Rayon
              </span>
              <span class="relative">
                <select
                  v-model.number="radiusKm"
                  :aria-label="`Rayon autour de ${selectedCity.name}`"
                  :disabled="loadingOffers"
                  class="h-9 appearance-none rounded-full border border-primary/30 bg-surface py-0 pr-8 pl-3 text-sm font-semibold text-primary outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15 disabled:cursor-wait disabled:opacity-60"
                  @change="refreshRadiusOffers"
                >
                  <option v-for="option in radiusOptions" :key="option" :value="option">
                    {{ option }} km
                  </option>
                </select>
                <ChevronDown
                  :size="14"
                  class="pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 text-primary"
                  aria-hidden="true"
                />
              </span>
            </label>

            <span v-else class="shrink-0 px-2 font-mono text-[0.7rem] text-ink/45">
              Ville uniquement
            </span>
          </div>

          <div
            v-else-if="browseScope === 'nearby'"
            class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-ink/8 bg-ground px-4 py-2"
          >
            <span class="flex items-center gap-2 text-sm font-semibold text-ink/65">
              <MapPin :size="16" class="text-primary" aria-hidden="true" />
              Autour de ta position
            </span>
            <label class="flex shrink-0 items-center gap-2">
              <span
                class="font-mono text-[0.7rem] font-semibold tracking-wide text-ink/50 uppercase"
              >
                Rayon
              </span>
              <span class="relative">
                <select
                  v-model.number="radiusKm"
                  aria-label="Rayon autour de ta position"
                  :disabled="loadingOffers"
                  class="h-9 appearance-none rounded-full border border-primary/30 bg-surface py-0 pr-8 pl-3 text-sm font-semibold text-primary outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15 disabled:cursor-wait disabled:opacity-60"
                  @change="refreshRadiusOffers"
                >
                  <option v-for="option in radiusOptions" :key="option" :value="option">
                    {{ option }} km
                  </option>
                </select>
                <ChevronDown
                  :size="14"
                  class="pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 text-primary"
                  aria-hidden="true"
                />
              </span>
            </label>
          </div>
        </div>
      </section>

      <nav
        aria-label="Filtres des annonces"
        class="sticky top-16 z-20 -mx-6 overflow-hidden border-y border-ink/10 bg-surface/95 px-6 py-3 backdrop-blur-md [contain:paint] md:mx-0 md:rounded-xl md:border"
      >
        <div class="flex min-w-0 items-center gap-2">
          <button
            type="button"
            class="relative flex h-10 shrink-0 items-center gap-2 rounded-full border border-ink/30 bg-surface px-4 text-sm font-semibold text-ink transition hover:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
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

          <div
            role="group"
            aria-label="Filtres rapides"
            class="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
          >
            <label class="relative shrink-0">
              <span class="sr-only">Filtrer par catégorie</span>
              <select
                v-model="selectedCategory"
                :disabled="categoriesLoading || Boolean(categoriesError)"
                class="h-10 max-w-52 appearance-none rounded-full border bg-surface py-0 pr-9 pl-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15 disabled:cursor-wait disabled:opacity-50"
                :class="
                  selectedCategory !== null
                    ? 'border-primary font-semibold text-primary'
                    : 'border-ink/15 text-ink/70 hover:border-ink/40'
                "
              >
                <option :value="null">Catégorie</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </select>
              <ChevronDown
                :size="15"
                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-ink/45"
                aria-hidden="true"
              />
            </label>

            <label class="relative shrink-0">
              <span class="sr-only">Filtrer par type</span>
              <select
                v-model="selectedType"
                class="h-10 appearance-none rounded-full border bg-surface py-0 pr-9 pl-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15"
                :class="
                  selectedType !== null
                    ? 'border-primary font-semibold text-primary'
                    : 'border-ink/15 text-ink/70 hover:border-ink/40'
                "
              >
                <option :value="null">Type</option>
                <option v-for="type in types" :key="type.value" :value="type.value">
                  {{ type.label }}
                </option>
              </select>
              <ChevronDown
                :size="15"
                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-ink/45"
                aria-hidden="true"
              />
            </label>

            <label class="relative shrink-0">
              <span class="sr-only">Filtrer par disponibilité</span>
              <select
                v-model="selectedStatus"
                class="h-10 appearance-none rounded-full border bg-surface py-0 pr-9 pl-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15"
                :class="
                  selectedStatus !== null
                    ? 'border-primary font-semibold text-primary'
                    : 'border-ink/15 text-ink/70 hover:border-ink/40'
                "
              >
                <option :value="null">Disponibilité</option>
                <option v-for="status in statuses" :key="status.value" :value="status.value">
                  {{ status.label }}
                </option>
              </select>
              <ChevronDown
                :size="15"
                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-ink/45"
                aria-hidden="true"
              />
            </label>

            <button
              type="button"
              class="h-10 shrink-0 rounded-full border px-4 text-sm transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
              :class="
                appliedMinPrice !== null || appliedMaxPrice !== null
                  ? 'border-primary bg-primary/10 font-semibold text-primary'
                  : 'border-ink/15 bg-surface text-ink/70 hover:border-ink/40 hover:text-ink'
              "
              @click="openFilters"
            >
              {{ priceFilterLabel }}
            </button>
          </div>
        </div>

        <div
          v-if="appliedFilterChips.length"
          class="mt-3 flex min-w-0 items-center gap-2 overflow-x-auto border-t border-ink/8 pt-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
          aria-label="Filtres appliqués"
        >
          <span
            v-for="chip in appliedFilterChips"
            :key="chip.key"
            class="flex h-8 shrink-0 items-center gap-2 rounded-full bg-primary/10 px-3 text-xs font-semibold text-primary"
          >
            {{ chip.label }}
            <button
              type="button"
              class="flex h-5 w-5 items-center justify-center rounded-full transition hover:bg-primary/15 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary"
              :aria-label="`Retirer le filtre ${chip.label}`"
              @click="clearAppliedFilter(chip.key)"
            >
              <X :size="13" aria-hidden="true" />
            </button>
          </span>

          <button
            type="button"
            class="h-8 shrink-0 px-2 text-xs font-semibold text-primary hover:underline"
            @click="clearAppliedFilters"
          >
            Tout effacer
          </button>
        </div>
      </nav>

      <div
        class="flex flex-col gap-3 border-b border-ink/10 pb-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-2">
          <p class="font-mono text-xs text-ink/50">
            <template v-if="loadingOffers"> Actualisation des annonces… </template>
            <template v-else-if="offersError"> Actualisation interrompue </template>
            <template v-else>{{ resultsSummary }}</template>
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

        <div
          v-if="availableViewModes.length"
          class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-lg bg-primary/8 p-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
          role="group"
          aria-label="Mode d’affichage des résultats"
        >
          <button
            v-for="mode in availableViewModes"
            :key="mode.value"
            type="button"
            class="shrink-0 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
            :class="
              viewMode === mode.value
                ? 'bg-surface text-ink shadow-sm'
                : 'text-ink/55 hover:text-ink'
            "
            :aria-pressed="viewMode === mode.value"
            @click="viewMode = mode.value"
          >
            {{ mode.label }}
          </button>
        </div>
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
              <template v-else-if="browseScope === 'city' && cityAreaMode === 'city'">
                Choisis une autre ville ou inclus les alentours.
              </template>
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
              v-else-if="canExpandRadius && isRadiusSearch"
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
            :city="offer.city"
            :images="offer.images"
          />
        </div>

        <template v-else-if="searchCenter">
          <!-- RADAR -->
          <div v-if="viewMode === 'radar'" class="grid gap-6 md:grid-cols-2">
            <div class="relative h-[500px]">
              <OfferMap
                :center="searchCenter"
                :radius-km="mapRadiusKm"
                :offers="filteredOffers"
                @select="goToOffer"
              />

              <RadarOverlay />
            </div>

            <div class="flex flex-col gap-2">
              <p class="font-mono text-xs tracking-wide text-ink/40 uppercase">
                {{ isRadiusSearch ? 'Triées par distance' : locationContextLabel }}
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
                    <span
                      v-if="offer.city"
                      class="flex items-center gap-1 font-body text-xs text-ink/55"
                    >
                      <MapPin :size="12" class="text-primary" aria-hidden="true" />
                      {{ offer.city }}
                    </span>

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
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(420px,0.95fr)]"
          >
            <div class="lg:max-h-[680px] lg:overflow-y-auto lg:pr-1">
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <OfferCard
                  v-for="offer in filteredOffers"
                  :id="offer.id"
                  :key="offer.id"
                  :title="offer.title"
                  :price="offer.price"
                  :status="offer.status"
                  :is-negotiable="offer.is_negotiable"
                  :category="offer.category ?? null"
                  :city="offer.city"
                  :distance="offer.distance"
                  :images="offer.images"
                />
              </div>
            </div>

            <OfferMap
              class="!h-[520px] lg:sticky lg:top-24 lg:!h-[680px]"
              :center="searchCenter"
              :radius-km="mapRadiusKm"
              :offers="filteredOffers"
              @select="goToOffer"
            />
          </div>

          <!-- IMMERSIVE -->
          <div v-else class="relative -mx-6 h-[70vh] overflow-hidden md:mx-0 md:rounded-xl">
            <OfferMap
              class="!h-full !w-full !rounded-none md:!rounded-xl"
              :center="searchCenter"
              :radius-km="mapRadiusKm"
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
                      {{ locationContextLabel }}
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
                        :src="resolveStorageUrl(offer.images[0].url)"
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
                        <span
                          v-if="offer.city"
                          class="flex min-w-0 items-center gap-1 font-body text-xs text-ink/55"
                        >
                          <MapPin :size="12" class="shrink-0 text-primary" aria-hidden="true" />
                          <span class="truncate">{{ offer.city }}</span>
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
            <section v-if="isRadiusSearch" class="border-b border-ink/10 py-6">
              <h3 class="font-display text-xl font-semibold text-ink">Distance</h3>
              <p class="mt-1 text-sm text-ink/50">
                <template v-if="browseScope === 'city' && selectedCityName">
                  Jusqu’où chercher autour de {{ selectedCityName }} ?
                </template>
                <template v-else>Jusqu’où chercher autour de ta position ?</template>
              </p>

              <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-5">
                <button
                  v-for="option in radiusOptions"
                  :key="option"
                  type="button"
                  class="rounded-xl border px-3 py-3 text-sm font-medium transition"
                  :class="
                    draftRadiusKm === option
                      ? 'border-primary bg-primary/10 text-primary ring-1 ring-primary'
                      : 'border-ink/15 text-ink hover:border-ink/40'
                  "
                  :aria-pressed="draftRadiusKm === option"
                  @click="draftRadiusKm = option"
                >
                  {{ option }} km
                </button>
              </div>
            </section>

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
              <template v-if="radiusDraftChanged">Appliquer les filtres</template>
              <template v-else>Afficher {{ draftResultCount }} annonce(s)</template>
            </button>
          </footer>
        </section>
      </div>
    </Teleport>
  </div>
</template>
