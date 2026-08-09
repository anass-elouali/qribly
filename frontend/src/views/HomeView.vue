<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import { fetchCategories } from '@/services/categories'
import OfferCard from '@/components/offers/OfferCard.vue'
import QriblyLogo from '@/components/branding/QriblyLogo.vue'
import type { Category, Offer, PaginatedResponse } from '@/types/offer'

const offers = ref<Offer[]>([])
const categories = ref<Category[]>([])
const selectedCategory = ref<number | null>(null)
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const error = ref('')

async function loadOffers() {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get<PaginatedResponse<Offer>>('/offers', {
      params: {
        page: page.value,
        category: selectedCategory.value ?? undefined,
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

watch([selectedCategory, page], loadOffers)

onMounted(async () => {
  categories.value = await fetchCategories()
  loadOffers()
})
</script>

<template>
  <div class="mx-auto max-w-6xl px-6 py-8">
    <div class="flex justify-center py-8">
      <QriblyLogo class="w-full max-w-sm" :animated="true" :show-tagline="true" />
    </div>

    <h1 class="mb-6 font-display text-2xl font-bold text-primary">Annonces</h1>

    <div class="mb-6 flex flex-wrap gap-2">
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
        Tout
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

    <p v-if="error" class="rounded-md bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved">
      {{ error }}
    </p>

    <p v-else-if="loading" class="font-mono text-sm text-ink/50">Chargement…</p>

    <p v-else-if="offers.length === 0" class="font-mono text-sm text-ink/50">
      Aucune annonce pour le moment.
    </p>

    <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
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

    <div v-if="lastPage > 1" class="mt-8 flex items-center justify-center gap-3 font-mono text-sm">
      <button
        type="button"
        class="rounded-md border border-ink/15 px-3 py-1.5 disabled:opacity-40"
        :disabled="page === 1"
        @click="page--"
      >
        ←
      </button>
      <span class="text-ink/60">Page {{ page }} / {{ lastPage }}</span>
      <button
        type="button"
        class="rounded-md border border-ink/15 px-3 py-1.5 disabled:opacity-40"
        :disabled="page === lastPage"
        @click="page++"
      >
        →
      </button>
    </div>
  </div>
</template>
