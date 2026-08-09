<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { fetchCategories } from '@/services/categories'
import type { Category, Offer } from '@/types/offer'
import { statusLabel } from '@/utils/offer'
import { extractErrorMessage } from '@/utils/errors'

const route = useRoute()
const router = useRouter()

const offerId = computed(() => {
  const id = route.params.id
  return Array.isArray(id) ? id[0] : id
})
const isEdit = computed(() => Boolean(offerId.value))

const categories = ref<Category[]>([])
const title = ref('')
const description = ref('')
const categoryId = ref<number | null>(null)
const type = ref<'product' | 'service'>('product')
const price = ref('')
const isNegotiable = ref(false)
const status = ref<'active' | 'reserved' | 'sold' | 'inactive'>('active')
const latitude = ref<number | null>(null)
const longitude = ref<number | null>(null)
const images = ref<File[]>([])

const loadingOffer = ref(false)
const submitting = ref(false)
const locating = ref(false)
const error = ref('')

async function loadOfferForEdit() {
  if (!offerId.value) {
    return
  }

  loadingOffer.value = true

  try {
    const response = await api.get<{ data: Offer }>(`/offers/${offerId.value}`)
    const offer = response.data.data

    title.value = offer.title
    description.value = offer.description
    categoryId.value = offer.category?.id ?? null
    type.value = offer.type
    price.value = offer.price
    isNegotiable.value = offer.is_negotiable
    status.value = offer.status
    latitude.value = offer.location?.latitude ?? null
    longitude.value = offer.location?.longitude ?? null
  } catch {
    error.value = "Impossible de charger cette annonce."
  } finally {
    loadingOffer.value = false
  }
}

function useCurrentLocation() {
  if (!navigator.geolocation) {
    error.value = "La géolocalisation n'est pas disponible sur cet appareil."
    return
  }

  locating.value = true

  navigator.geolocation.getCurrentPosition(
    (position) => {
      latitude.value = position.coords.latitude
      longitude.value = position.coords.longitude
      locating.value = false
    },
    () => {
      error.value = 'Impossible de récupérer ta position — autorise la géolocalisation.'
      locating.value = false
    },
  )
}

function onImagesChange(event: Event) {
  const input = event.target as HTMLInputElement
  images.value = input.files ? Array.from(input.files).slice(0, 5) : []
}

async function handleSubmit() {
  error.value = ''

  if (latitude.value === null || longitude.value === null) {
    error.value = "Choisis un emplacement avant de publier."
    return
  }

  submitting.value = true

  try {
    if (isEdit.value) {
      await api.put(`/offers/${offerId.value}`, {
        category_id: categoryId.value,
        title: title.value,
        description: description.value,
        type: type.value,
        price: price.value,
        is_negotiable: isNegotiable.value,
        status: status.value,
        location: { latitude: latitude.value, longitude: longitude.value },
      })

      await router.push({ name: 'offer-details', params: { id: offerId.value } })
    } else {
      const formData = new FormData()
      formData.append('category_id', String(categoryId.value))
      formData.append('title', title.value)
      formData.append('description', description.value)
      formData.append('type', type.value)
      formData.append('price', price.value)
      formData.append('is_negotiable', isNegotiable.value ? '1' : '0')
      formData.append('status', status.value)
      formData.append('location[latitude]', String(latitude.value))
      formData.append('location[longitude]', String(longitude.value))
      images.value.forEach((file) => formData.append('images[]', file))

      const response = await api.post<{ data: Offer }>('/offers', formData)

      await router.push({ name: 'offer-details', params: { id: response.data.data.id } })
    }
  } catch (err) {
    error.value = extractErrorMessage(err, "Impossible d'enregistrer l'annonce.")
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  categories.value = await fetchCategories()
  loadOfferForEdit()
})
</script>

<template>
  <div class="mx-auto max-w-2xl px-6 py-8">
    <h1 class="mb-6 font-display text-2xl font-bold text-primary">
      {{ isEdit ? "Modifier l'annonce" : 'Publier une annonce' }}
    </h1>

    <p v-if="loadingOffer" class="font-mono text-sm text-ink/50">Chargement…</p>

    <form v-else class="flex flex-col gap-5" @submit.prevent="handleSubmit">
      <div v-if="!isEdit">
        <label class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
          Photos (5 max)
        </label>
        <input
          type="file"
          accept="image/png,image/jpeg,image/webp,image/avif"
          multiple
          class="block w-full text-sm text-ink/70 file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:font-semibold file:text-surface"
          @change="onImagesChange"
        />
        <p v-if="images.length" class="mt-2 font-mono text-xs text-ink/50">
          {{ images.length }} photo(s) sélectionnée(s)
        </p>
      </div>

      <div>
        <label for="title" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
          Titre
        </label>
        <input
          id="title"
          v-model="title"
          type="text"
          required
          maxlength="255"
          class="w-full rounded-lg border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label for="category" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
            Catégorie
          </label>
          <select
            id="category"
            v-model.number="categoryId"
            required
            class="w-full rounded-lg border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          >
            <option :value="null" disabled>Choisir…</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">
              {{ category.name }}
            </option>
          </select>
        </div>

        <div>
          <label for="type" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
            Type
          </label>
          <select
            id="type"
            v-model="type"
            required
            class="w-full rounded-lg border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          >
            <option value="product">Produit</option>
            <option value="service">Service</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label for="price" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
            Prix (DH)
          </label>
          <input
            id="price"
            v-model="price"
            type="number"
            step="0.01"
            min="0"
            required
            class="w-full rounded-lg border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <div>
          <label for="status" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
            Statut
          </label>
          <select
            id="status"
            v-model="status"
            required
            class="w-full rounded-lg border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          >
            <option v-for="(label, value) in statusLabel" :key="value" :value="value">
              {{ label }}
            </option>
          </select>
        </div>
      </div>

      <label class="flex items-center gap-2 font-body text-sm text-ink">
        <input v-model="isNegotiable" type="checkbox" class="accent-primary" />
        Prix négociable
      </label>

      <div>
        <label for="description" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
          Description
        </label>
        <textarea
          id="description"
          v-model="description"
          required
          rows="4"
          class="w-full rounded-lg border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
        ></textarea>
      </div>

      <div>
        <label class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">Emplacement</label>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-ink/15 bg-surface px-4 py-2.5 font-body text-sm text-ink transition hover:border-primary"
          :disabled="locating"
          @click="useCurrentLocation"
        >
          {{ locating ? 'Localisation…' : 'Utiliser ma position actuelle' }}
        </button>
        <p v-if="latitude !== null && longitude !== null" class="mt-2 font-mono text-xs text-ink/50">
          {{ latitude.toFixed(4) }}, {{ longitude.toFixed(4) }}
        </p>
      </div>

      <p v-if="error" class="rounded-lg bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved">
        {{ error }}
      </p>

      <div class="flex gap-3">
        <button
          type="submit"
          :disabled="submitting"
          class="rounded-lg bg-accent px-5 py-3 font-semibold text-ink transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {{ submitting ? 'Enregistrement…' : isEdit ? "Enregistrer les modifications" : "Publier l'annonce" }}
        </button>
        <RouterLink
          :to="{ name: 'home' }"
          class="rounded-lg border border-ink/15 px-5 py-3 font-body text-sm text-ink/70 transition hover:border-primary hover:text-primary"
        >
          Annuler
        </RouterLink>
      </div>
    </form>
  </div>
</template>
