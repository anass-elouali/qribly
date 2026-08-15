```vue
<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { fetchCategories } from '@/services/categories'
import OfferCard from '@/components/offers/OfferCard.vue'
import QriblyLogo from '@/components/branding/QriblyLogo.vue'
import type { Category, Offer, PaginatedResponse } from '@/types/offer'

const router = useRouter()

const offers = ref<Offer[]>([])
const categories = ref<Category[]>([])

const selectedCategory = ref<number | null>(null)
const selectedType = ref<'product' | 'service' | null>(null)

const search = ref('')
const page = ref(1)
const lastPage = ref(1)

const loading = ref(false)
const error = ref('')



const searchQuery = ref('')

function submitSearch() {
  const query = searchQuery.value.trim()

  router.push({
    name: 'search',
    query: query ? { q: query } : undefined,
  })
}

async function loadOffers() {
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

    offers.value = response.data.data
    lastPage.value = response.data.meta.last_page
  } catch {
    error.value = 'Impossible de charger les annonces.'
  } finally {
    loading.value = false
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

watch(
  [selectedCategory, selectedType, page],
  loadOffers,
)

onMounted(async () => {
  categories.value = await fetchCategories()
  await loadOffers()
})
</script>

<template>
  <div class="mx-auto max-w-6xl px-6 py-8">
    <!-- Hero -->
    <section class="mb-14">
      <div
        class="rounded-2xl border border-ink/10 bg-surface-alt
               px-6 py-8 sm:px-10 sm:py-10 lg:px-16"
      >
        <div class="mx-auto max-w-3xl text-center">
          <QriblyLogo
            class="mx-auto w-full max-w-xs sm:max-w-sm"
            :animated="true"
            :show-tagline="true"
          />

          <h1
            class="mt-6 font-display text-4xl font-bold tracking-tight text-ink
                   sm:text-5xl lg:text-6xl"
          >
            Trouvez ce dont vous avez besoin,
            <span class="text-primary">près de chez vous.</span>
          </h1>

          <p
            class="mx-auto mt-5 max-w-2xl text-base leading-7 text-ink/60
                   sm:text-lg"
          >
            Découvrez des produits et services proposés par des personnes
            et commerces de votre ville.
          </p>

          <!-- Search -->
          <form
            class="mx-auto mt-8 flex max-w-2xl flex-col gap-3 sm:flex-row"
            @submit.prevent="searchOffers"
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
                aria-hidden="true"
              >
                <circle cx="11" cy="11" r="7" />
                <path d="m20 20-4-4" />
              </svg>
              <input
                v-model="searchQuery"
                type="search"
                placeholder="Que recherchez-vous ?"
                class="w-full bg-transparent py-3 text-sm text-ink outline-none
                      placeholder:text-ink/40"
                @keyup.enter="submitSearch"
              />
                          
            </div>

            <button
              type="button"
              class="rounded-lg bg-primary px-6 py-3 text-sm font-semibold
                    text-surface transition-opacity hover:opacity-90"
              @click="submitSearch"
            >
              Rechercher
            </button>
          </form>

          <!-- Nearby -->
          <button
            type="button"
            class="mt-5 inline-flex items-center gap-2 text-sm font-medium
                   text-primary transition-opacity hover:opacity-70"
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

    <!-- Filters -->
    <section class="mb-12">
      <div
        class="flex flex-col gap-6 rounded-xl border border-ink/10 bg-surface-alt
              p-5 sm:flex-row sm:items-start sm:gap-8"
      >
        <!-- Type -->
        <div class="sm:flex-1">
          <h2 class="mb-3 font-display text-lg font-semibold text-ink">
            Type
          </h2>

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

        <!-- Separator -->
        <div class="hidden h-16 w-px bg-ink/10 sm:block"></div>
        <div class="h-px w-full bg-ink/10 sm:hidden"></div>

        <!-- Categories -->
        <div class="sm:flex-[3]">
          <h2 class="mb-3 font-display text-lg font-semibold text-ink">
            Catégories
          </h2>

          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-full border px-4 py-1.5 text-sm transition-colors"
              :class="
                selectedCategory === null
                  ? 'border-primary bg-primary text-surface'
                  : 'border-ink/15 text-ink/70 hover:border-primary hover:text-primary'
              "
              @click="selectCategory(null)"
            >
              Toutes
            </button>

            <button
              v-for="category in categories"
              :key="category.id"
              type="button"
              class="rounded-full border px-4 py-1.5 text-sm transition-colors"
              :class="
                selectedCategory === category.id
                  ? 'border-primary bg-primary text-surface'
                  : 'border-ink/15 text-ink/70 hover:border-primary hover:text-primary'
              "
              @click="selectCategory(category.id)"
            >
              {{ category.name }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Offers -->
    <section>
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h2 class="font-display text-2xl font-bold text-primary">
            Dernières annonces
          </h2>

          <p class="mt-1 text-sm text-ink/50">
            Découvrez les dernières offres disponibles près de chez vous.
          </p>
        </div>
      </div>

      <!-- Error -->
      <p
        v-if="error"
        class="rounded-md bg-status-reserved/10 px-4 py-3 text-sm
               text-status-reserved"
      >
        {{ error }}
      </p>

      <!-- Loading -->
      <p
        v-else-if="loading"
        class="font-mono text-sm text-ink/50"
      >
        Chargement…
      </p>

      <!-- Empty -->
      <p
        v-else-if="offers.length === 0"
        class="font-mono text-sm text-ink/50"
      >
        Aucune annonce pour le moment.
      </p>

      <!-- Offer cards -->
      <div
        v-else
        class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
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
        class="mt-8 flex items-center justify-center gap-3 font-mono text-sm"
      >
        <button
          type="button"
          class="rounded-md border border-ink/15 px-3 py-1.5
                 disabled:opacity-40"
          :disabled="page === 1"
          @click="page--"
        >
          ←
        </button>

        <span class="text-ink/60">
          Page {{ page }} / {{ lastPage }}
        </span>

        <button
          type="button"
          class="rounded-md border border-ink/15 px-3 py-1.5
                 disabled:opacity-40"
          :disabled="page === lastPage"
          @click="page++"
        >
          →
        </button>
      </div>
    </section>
  </div>
</template>
```
