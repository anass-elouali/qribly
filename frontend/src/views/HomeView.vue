<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { fetchCategories } from '@/services/categories'
import { extractErrorMessage } from '@/utils/errors'
import OfferCard from '@/components/offers/OfferCard.vue'
import OfferGridSkeleton from '@/components/offers/OfferGridSkeleton.vue'
import QriblyLogo from '@/components/branding/QriblyLogo.vue'
import AsyncStatePanel from '@/components/ui/AsyncStatePanel.vue'
import FeaturedOffersCarousel from '@/components/home/FeaturedOffersCarousel.vue'
import CategoryShowcase from '@/components/home/CategoryShowcase.vue'
import SpotlightBanner from '@/components/home/SpotlightBanner.vue'
import type { Category, Offer, PaginatedResponse } from '@/types/offer'

const router = useRouter()

const offers = ref<Offer[]>([])
const categories = ref<Category[]>([])

// A broader, unfiltered pool of offers fetched once, used only to source real
// photos for the homepage's visual sections (carousel, category tiles, spotlight)
// without adding new backend endpoints or affecting the paginated grid below.
const showcasePool = ref<Offer[]>([])

const categoryImages = computed(() => {
  const images: Record<number, string | null> = {}

  for (const category of categories.value) {
    const match = showcasePool.value.find(
      (offer) => offer.category?.id === category.id && offer.images?.[0],
    )
    images[category.id] = match?.images?.[0]?.url ?? null
  }

  return images
})

const featuredOffers = computed(() =>
  showcasePool.value.filter((offer) => offer.status === 'active' && offer.images?.length).slice(0, 8),
)

const spotlightOffer = computed(() => featuredOffers.value[0] ?? null)

async function loadShowcasePool() {
  try {
    const response = await api.get<PaginatedResponse<Offer>>('/offers', {
      params: { per_page: 80 },
    })

    showcasePool.value = response.data.data
  } catch {
    // Purely decorative data — a failure here shouldn't block the rest of the
    // homepage, so the carousel/category photos/spotlight simply stay empty.
  }
}

const selectedCategory = ref<number | null>(null)
const selectedType = ref<'product' | 'service' | null>(null)

const page = ref(1)
const lastPage = ref(1)

const loading = ref(false)
const error = ref('')
const categoriesLoading = ref(true)
const categoriesError = ref('')

let offersRequestId = 0

const searchQuery = ref('')

function submitSearch() {
  const query = searchQuery.value.trim()

  router.push({
    name: 'search',
    query: query ? { q: query } : undefined,
  })
}

async function loadOffers() {
  const requestId = ++offersRequestId
  loading.value = true
  error.value = ''

  try {
    const response = await api.get<PaginatedResponse<Offer>>('/offers', {
      params: {
        page: page.value,
        category: selectedCategory.value ?? undefined,
        type: selectedType.value ?? undefined,
      },
    })

    if (requestId === offersRequestId) {
      offers.value = response.data.data
      lastPage.value = response.data.meta.last_page
    }
  } catch (exception) {
    if (requestId === offersRequestId) {
      error.value = extractErrorMessage(exception, 'Impossible de charger les annonces.')
    }
  } finally {
    if (requestId === offersRequestId) {
      loading.value = false
    }
  }
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

function selectCategory(id: number | null) {
  selectedCategory.value = id
  page.value = 1
}

function selectType(type: 'product' | 'service' | null) {
  selectedType.value = type
  page.value = 1
}

function searchOffers() {
  page.value = 1
  loadOffers()
}

function goToNearby() {
  router.push({ name: 'nearby' })
}

function resetOfferFilters() {
  const hasFilters =
    selectedCategory.value !== null || selectedType.value !== null || page.value !== 1

  selectedCategory.value = null
  selectedType.value = null
  page.value = 1

  if (!hasFilters) {
    loadOffers()
  }
}

watch([selectedCategory, selectedType, page], loadOffers)

onMounted(() => {
  Promise.all([loadCategories(), loadOffers(), loadShowcasePool()])
})
</script>

<template>
  <div class="mx-auto max-w-6xl px-6 py-8">
    <!-- Hero -->
    <section class="mb-14">
      <div class="rounded-2xl border border-ink/10 bg-surface px-6 py-8 sm:px-10 sm:py-10 lg:px-16">
        <div class="mx-auto max-w-3xl text-center">
          <QriblyLogo
            class="mx-auto w-full max-w-xs sm:max-w-sm"
            :animated="true"
            :show-tagline="true"
          />

          <h1
            class="mt-6 font-display text-4xl font-bold tracking-tight text-ink sm:text-5xl lg:text-6xl"
          >
            Trouvez ce dont vous avez besoin,
            <span class="text-primary">près de chez vous.</span>
          </h1>

          <p class="mx-auto mt-5 max-w-2xl text-base leading-7 text-ink/60 sm:text-lg">
            Découvrez des produits et services proposés par des personnes et commerces de votre
            ville.
          </p>

          <!-- Search -->
          <form
            class="mx-auto mt-8 flex max-w-2xl flex-col gap-3 sm:flex-row"
            @submit.prevent="searchOffers"
          >
            <div
              class="flex flex-1 items-center rounded-lg border border-ink/15 bg-surface px-4 transition-colors focus-within:border-primary"
            >
              <svg
                class="mr-3 h-5 w-5 shrink-0 text-ink/40"
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
                v-model="searchQuery"
                type="search"
                placeholder="Que recherchez-vous ?"
                class="w-full bg-transparent py-3 text-sm text-ink outline-none placeholder:text-ink/40"
                @keyup.enter="submitSearch"
              />
            </div>

            <button
              type="button"
              class="rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-surface transition-opacity hover:opacity-90"
              @click="submitSearch"
            >
              Rechercher
            </button>
          </form>

          <!-- Nearby -->
          <button
            type="button"
            class="mt-5 inline-flex items-center gap-2 text-sm font-medium text-primary transition-opacity hover:opacity-70"
            @click="goToNearby"
          >
            <svg
              class="h-4 w-4"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              aria-hidden="true"
            >
              <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
              <circle cx="12" cy="10" r="2.5" />
            </svg>

            Découvrir les offres près de moi
          </button>
        </div>
      </div>
    </section>

    <!-- Spotlight -->
    <SpotlightBanner v-if="spotlightOffer" :offer="spotlightOffer" />

    <!-- Featured carousel -->
    <FeaturedOffersCarousel v-if="featuredOffers.length > 0" :offers="featuredOffers" />

    <!-- Category showcase -->
    <section v-if="!categoriesError" class="mb-12">
      <template v-if="categoriesLoading">
        <h2 class="mb-5 font-display text-2xl font-bold text-ink sm:text-3xl">
          Parcourir par catégorie
        </h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          <span
            v-for="index in 5"
            :key="index"
            class="aspect-[4/3] animate-pulse rounded-xl bg-ink/10 motion-reduce:animate-none"
            aria-hidden="true"
          />
        </div>
        <span class="sr-only" role="status">Chargement des catégories…</span>
      </template>

      <CategoryShowcase
        v-else
        :categories="categories"
        :category-images="categoryImages"
        :selected-category="selectedCategory"
        @select="selectCategory"
      />
    </section>

    <div
      v-else
      class="mb-12 flex flex-wrap items-center gap-3 rounded-lg bg-status-reserved/5 px-4 py-3"
      role="alert"
    >
      <p class="text-sm text-status-reserved">{{ categoriesError }}</p>
      <button type="button" class="text-sm font-semibold text-primary hover:underline" @click="loadCategories">
        Réessayer
      </button>
    </div>

    <!-- Filters -->
    <section class="mb-12">
      <div class="rounded-xl border border-ink/10 bg-surface p-5">
        <h2 class="mb-3 font-display text-lg font-semibold text-ink">Type</h2>

        <div class="flex flex-wrap gap-1">
          <button
            type="button"
            class="rounded-full border px-4 py-1.5 text-sm transition-colors"
            :class="
              selectedType === null
                ? 'border-primary bg-primary text-surface'
                : 'border-ink/15 text-ink/70 hover:border-primary hover:text-primary'
            "
            @click="selectType(null)"
          >
            Tout
          </button>

          <button
            type="button"
            class="rounded-full border px-4 py-1.5 text-sm transition-colors"
            :class="
              selectedType === 'product'
                ? 'border-primary bg-primary text-surface'
                : 'border-ink/15 text-ink/70 hover:border-primary hover:text-primary'
            "
            @click="selectType('product')"
          >
            Produits
          </button>

          <button
            type="button"
            class="rounded-full border px-4 py-1.5 text-sm transition-colors"
            :class="
              selectedType === 'service'
                ? 'border-primary bg-primary text-surface'
                : 'border-ink/15 text-ink/70 hover:border-primary hover:text-primary'
            "
            @click="selectType('service')"
          >
            Services
          </button>
        </div>
      </div>
    </section>

    <!-- Offers -->
    <section>
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h2 class="font-display text-2xl font-bold text-primary">Dernières annonces</h2>

          <p class="mt-1 text-sm text-ink/50">
            Découvrez les dernières offres disponibles près de chez vous.
          </p>
        </div>
      </div>

      <AsyncStatePanel
        v-if="error"
        class="mb-5"
        variant="error"
        title="Les annonces ne sont pas disponibles"
        :message="error"
        action-label="Réessayer"
        compact
        @action="loadOffers"
      />

      <OfferGridSkeleton v-if="loading && offers.length === 0" />

      <AsyncStatePanel
        v-else-if="!error && offers.length === 0"
        variant="empty"
        title="Aucune annonce trouvée"
        message="Essaie une autre catégorie ou affiche de nouveau toutes les annonces."
        action-label="Réinitialiser les filtres"
        @action="resetOfferFilters"
      />

      <div
        v-if="offers.length > 0"
        class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
        :class="loading ? 'pointer-events-none opacity-60' : ''"
        :aria-busy="loading"
      >
        <OfferCard
          v-for="offer in offers"
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

      <!-- Pagination -->
      <div
        v-if="lastPage > 1 && offers.length > 0"
        class="mt-8 flex items-center justify-center gap-3 font-mono text-sm"
      >
        <button
          type="button"
          class="rounded-md border border-ink/15 px-3 py-1.5 disabled:opacity-40"
          :disabled="page === 1 || loading"
          @click="page--"
        >
          ←
        </button>

        <span class="text-ink/60"> Page {{ page }} / {{ lastPage }} </span>

        <button
          type="button"
          class="rounded-md border border-ink/15 px-3 py-1.5 disabled:opacity-40"
          :disabled="page === lastPage || loading"
          @click="page++"
        >
          →
        </button>
      </div>
    </section>
  </div>
</template>
