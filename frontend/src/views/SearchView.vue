<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { fetchCategories } from '@/services/categories'
import OfferCard from '@/components/offers/OfferCard.vue'
import type { Category, Offer, PaginatedResponse } from '@/types/offer'

const route = useRoute()
const router = useRouter()

const offers = ref<Offer[]>([])
const categories = ref<Category[]>([])

const searchQuery = ref('')
const selectedCategory = ref<number | null>(null)
const selectedType = ref<string | null>(null)
const selectedStatus = ref<string | null>(null)

const minPrice = ref<number | null>(null)
const maxPrice = ref<number | null>(null)

const page = ref(1)
const lastPage = ref(1)

const loading = ref(false)
const error = ref('')

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

async function loadOffers() {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get<PaginatedResponse<Offer>>('/offers', {
      params: {
        q: searchQuery.value || undefined,
        category: selectedCategory.value ?? undefined,
        type: selectedType.value ?? undefined,
        status: selectedStatus.value ?? undefined,
        min_price: minPrice.value ?? undefined,
        max_price: maxPrice.value ?? undefined,
        page: page.value,
      },
    })

    offers.value = response.data.data
    lastPage.value = response.data.meta.last_page
  } catch {
    error.value = 'Impossible de charger les annonces.'
  } finally {
    loading.value = false
  }
}

function updateFilters() {
  page.value = 1

  router.replace({
    name: 'search',
    query: {
      q: searchQuery.value || undefined,
      category: selectedCategory.value
        ? String(selectedCategory.value)
        : undefined,
      type: selectedType.value || undefined,
      status: selectedStatus.value || undefined,
      min_price: minPrice.value !== null
        ? String(minPrice.value)
        : undefined,
      max_price: maxPrice.value !== null
        ? String(maxPrice.value)
        : undefined,
    },
  })
}

function selectCategory(category: number | null) {
  selectedCategory.value = category
  updateFilters()
}

function selectType(type: string | null) {
  selectedType.value = type
  updateFilters()
}

function selectStatus(status: string | null) {
  selectedStatus.value = status
  updateFilters()
}

function applyPriceFilter() {
  updateFilters()
}

function clearFilters() {
  searchQuery.value = ''
  selectedCategory.value = null
  selectedType.value = null
  selectedStatus.value = null
  minPrice.value = null
  maxPrice.value = null
  page.value = 1

  updateFilters()
}

function hasActiveFilters() {
  return (
    searchQuery.value !== '' ||
    selectedCategory.value !== null ||
    selectedType.value !== null ||
    selectedStatus.value !== null ||
    minPrice.value !== null ||
    maxPrice.value !== null
  )
}

function loadFiltersFromUrl() {
  searchQuery.value =
    typeof route.query.q === 'string'
      ? route.query.q
      : ''

  selectedCategory.value =
    typeof route.query.category === 'string'
      ? Number(route.query.category)
      : null

  selectedType.value =
    typeof route.query.type === 'string'
      ? route.query.type
      : null

  selectedStatus.value =
    typeof route.query.status === 'string'
      ? route.query.status
      : null

  minPrice.value =
    typeof route.query.min_price === 'string'
      ? Number(route.query.min_price)
      : null

  maxPrice.value =
    typeof route.query.max_price === 'string'
      ? Number(route.query.max_price)
      : null
}

function goToPage(newPage: number) {
  if (newPage < 1 || newPage > lastPage.value) {
    return
  }

  page.value = newPage
  loadOffers()
}

watch(
  () => route.query,
  () => {
    loadFiltersFromUrl()
    loadOffers()
  },
)

onMounted(async () => {
  categories.value = await fetchCategories()

  loadFiltersFromUrl()
  await loadOffers()
})
</script>

<template>
  <div class="mx-auto max-w-7xl px-6 py-8">

    <!-- Search header -->
    <section class="mb-8">
      <h1 class="font-display text-3xl font-bold text-primary">
        Rechercher
      </h1>

      <p class="mt-2 text-sm text-ink/60">
        Trouvez des produits et services près de chez vous.
      </p>

      <form
        class="mt-6 flex flex-col gap-3 sm:flex-row"
        @submit.prevent="updateFilters"
      >
        <div
          class="flex flex-1 items-center rounded-lg border border-ink/15
                 bg-surface px-4 transition-colors
                 focus-within:border-primary"
        >
          <svg
            class="mr-3 h-5 w-5 shrink-0 text-ink/40"
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
            placeholder="Que recherchez-vous ?"
            class="w-full bg-transparent py-3 text-sm text-ink
                   outline-none placeholder:text-ink/40"
          />
        </div>

        <button
          type="submit"
          class="rounded-lg bg-primary px-7 py-3 text-sm
                 font-semibold text-surface transition-opacity
                 hover:opacity-90"
        >
          Rechercher
        </button>
      </form>
    </section>

    <div class="flex flex-col gap-8 lg:flex-row">

      <!-- Sidebar -->
      <aside class="w-full shrink-0 lg:w-64">

        <div class="rounded-xl border border-ink/10 bg-surface p-5">

          <div class="mb-5 flex items-center justify-between">
            <h2 class="font-display text-lg font-semibold text-ink">
              Filtres
            </h2>

            <button
              v-if="hasActiveFilters()"
              type="button"
              class="text-xs font-medium text-primary hover:underline"
              @click="clearFilters"
            >
              Effacer
            </button>
          </div>

          <!-- Type -->
          <div class="border-b border-ink/10 pb-5">
            <h3 class="mb-3 text-sm font-semibold text-ink">
              Type
            </h3>

            <div class="space-y-2">
              <label
                v-for="type in types"
                :key="type.value"
                class="flex cursor-pointer items-center gap-3 text-sm text-ink/70"
              >
                <input
                  type="radio"
                  name="type"
                  :value="type.value"
                  :checked="selectedType === type.value"
                  class="accent-primary"
                  @change="selectType(type.value)"
                />

                <span>{{ type.label }}</span>
              </label>

              <label
                class="flex cursor-pointer items-center gap-3 text-sm text-ink/70"
              >
                <input
                  type="radio"
                  name="type"
                  :checked="selectedType === null"
                  class="accent-primary"
                  @change="selectType(null)"
                />

                <span>Tous</span>
              </label>
            </div>
          </div>

          <!-- Category -->
          <div class="border-b border-ink/10 py-5">
            <h3 class="mb-3 text-sm font-semibold text-ink">
              Catégorie
            </h3>

            <div class="max-h-64 space-y-2 overflow-y-auto pr-1">

              <label
                class="flex cursor-pointer items-center gap-3 text-sm text-ink/70"
              >
                <input
                  type="radio"
                  name="category"
                  :checked="selectedCategory === null"
                  class="accent-primary"
                  @change="selectCategory(null)"
                />

                <span>Toutes</span>
              </label>

              <label
                v-for="category in categories"
                :key="category.id"
                class="flex cursor-pointer items-center gap-3 text-sm text-ink/70"
              >
                <input
                  type="radio"
                  name="category"
                  :value="category.id"
                  :checked="selectedCategory === category.id"
                  class="accent-primary"
                  @change="selectCategory(category.id)"
                />

                <span>{{ category.name }}</span>
              </label>

            </div>
          </div>

          <!-- Status -->
          <div class="border-b border-ink/10 py-5">
            <h3 class="mb-3 text-sm font-semibold text-ink">
              Statut
            </h3>

            <div class="space-y-2">

              <label
                class="flex cursor-pointer items-center gap-3 text-sm text-ink/70"
              >
                <input
                  type="radio"
                  name="status"
                  :checked="selectedStatus === null"
                  class="accent-primary"
                  @change="selectStatus(null)"
                />

                <span>Tous</span>
              </label>

              <label
                v-for="status in statuses"
                :key="status.value"
                class="flex cursor-pointer items-center gap-3 text-sm text-ink/70"
              >
                <input
                  type="radio"
                  name="status"
                  :value="status.value"
                  :checked="selectedStatus === status.value"
                  class="accent-primary"
                  @change="selectStatus(status.value)"
                />

                <span>{{ status.label }}</span>
              </label>

            </div>
          </div>

          <!-- Price -->
          <div class="pt-5">
            <h3 class="mb-3 text-sm font-semibold text-ink">
              Prix
            </h3>

            <div class="flex items-center gap-2">

              <input
                v-model.number="minPrice"
                type="number"
                min="0"
                placeholder="Min"
                class="w-full rounded-md border border-ink/15
                       bg-surface px-3 py-2 text-sm outline-none
                       focus:border-primary"
                @keyup.enter="applyPriceFilter"
              />

              <span class="text-ink/40">—</span>

              <input
                v-model.number="maxPrice"
                type="number"
                min="0"
                placeholder="Max"
                class="w-full rounded-md border border-ink/15
                       bg-surface px-3 py-2 text-sm outline-none
                       focus:border-primary"
                @keyup.enter="applyPriceFilter"
              />

            </div>

            <button
              type="button"
              class="mt-3 w-full rounded-md border border-ink/15
                     px-3 py-2 text-sm font-medium text-ink/70
                     transition-colors hover:border-primary
                     hover:text-primary"
              @click="applyPriceFilter"
            >
              Appliquer
            </button>
          </div>

        </div>
      </aside>

      <!-- Results -->
      <main class="min-w-0 flex-1">

        <div class="mb-5 flex items-center justify-between">

          <div>
            <h2 class="font-display text-xl font-semibold text-ink">
              Résultats
            </h2>

            <p class="mt-1 text-sm text-ink/50">
              <span v-if="searchQuery">
                Résultats pour « {{ searchQuery }} »
              </span>

              <span v-else>
                Découvrez les annonces disponibles
              </span>
            </p>
          </div>

          <span
            v-if="!loading"
            class="font-mono text-xs text-ink/40"
          >
            Page {{ page }} / {{ lastPage }}
          </span>

        </div>

        <!-- Error -->
        <p
          v-if="error"
          class="rounded-lg bg-status-reserved/10 px-4 py-3
                 text-sm text-status-reserved"
        >
          {{ error }}
        </p>

        <!-- Loading -->
        <div
          v-else-if="loading"
          class="flex min-h-64 items-center justify-center"
        >
          <p class="font-mono text-sm text-ink/50">
            Chargement…
          </p>
        </div>

        <!-- Empty -->
        <div
          v-else-if="offers.length === 0"
          class="flex min-h-64 flex-col items-center justify-center
                 rounded-xl border border-dashed border-ink/15"
        >
          <p class="font-display text-lg font-semibold text-ink">
            Aucune annonce trouvée
          </p>

          <p class="mt-2 text-sm text-ink/50">
            Essayez de modifier vos filtres ou votre recherche.
          </p>

          <button
            type="button"
            class="mt-4 text-sm font-medium text-primary hover:underline"
            @click="clearFilters"
          >
            Effacer les filtres
          </button>
        </div>

        <!-- Offers -->
        <div
          v-else
          class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3"
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
            :images="offer.images"
          />
        </div>

        <!-- Pagination -->
        <div
          v-if="lastPage > 1"
          class="mt-8 flex items-center justify-center gap-4
                 font-mono text-sm"
        >
          <button
            type="button"
            class="rounded-md border border-ink/15 px-4 py-2
                   disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="page === 1"
            @click="goToPage(page - 1)"
          >
            ←
          </button>

          <span class="text-ink/60">
            Page {{ page }} / {{ lastPage }}
          </span>

          <button
            type="button"
            class="rounded-md border border-ink/15 px-4 py-2
                   disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="page === lastPage"
            @click="goToPage(page + 1)"
          >
            →
          </button>
        </div>

      </main>
    </div>
  </div>
</template>