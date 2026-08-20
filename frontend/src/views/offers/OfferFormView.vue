<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import api from '@/services/api'
import { fetchCategories } from '@/services/categories'

import type { Category, Offer } from '@/types/offer'

import { extractErrorMessage } from '@/utils/errors'

import OfferInfoStep, {
  type OfferInfoData,
} from '@/components/offers/OfferInfoStep.vue'

import OfferImagesStep from '@/components/offers/OfferImagesStep.vue'

import OfferLocationStep, {
  type OfferLocationData,
} from '@/components/offers/OfferLocationStep.vue'

const route = useRoute()
const router = useRouter()

/*
|--------------------------------------------------------------------------
| Edit mode
|--------------------------------------------------------------------------
*/

const offerId = computed(() => {
  const id = route.params.id

  return Array.isArray(id)
    ? id[0]
    : id
})

const isEdit = computed(() => Boolean(offerId.value))

/*
|--------------------------------------------------------------------------
| Step
|--------------------------------------------------------------------------
*/

const step = ref(1)

const totalSteps = 3

const progress = computed(() => {
  return `${(step.value / totalSteps) * 100}%`
})

/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

const categories = ref<Category[]>([])

/*
|--------------------------------------------------------------------------
| Offer information
|--------------------------------------------------------------------------
*/

const offerInfo = ref<OfferInfoData>({
  title: '',
  description: '',
  categoryId: null,
  type: 'product',
  price: '',
  isNegotiable: false,
  status: 'active',
})

/*
|--------------------------------------------------------------------------
| Images
|--------------------------------------------------------------------------
*/

const images = ref<File[]>([])

const existingImages = ref<
  {
    id: number
    url: string
  }[]
>([])

/*
|--------------------------------------------------------------------------
| Location
|--------------------------------------------------------------------------
*/

const location = ref<OfferLocationData>({
  latitude: null,
  longitude: null,
})

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const loadingOffer = ref(false)
const submitting = ref(false)
const error = ref('')

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

function nextStep() {
  if (step.value < totalSteps) {
    step.value++
    error.value = ''

    window.scrollTo({
      top: 0,
      behavior: 'smooth',
    })
  }
}

function previousStep() {
  if (step.value > 1) {
    step.value--
    error.value = ''

    window.scrollTo({
      top: 0,
      behavior: 'smooth',
    })
  }
}

/*
|--------------------------------------------------------------------------
| Load offer when editing
|--------------------------------------------------------------------------
*/

async function loadOfferForEdit() {
  if (!offerId.value) {
    return
  }

  loadingOffer.value = true
  error.value = ''

  try {
    const response = await api.get<{ data: Offer }>(
      `/offers/${offerId.value}`,
    )

    const offer = response.data.data

    offerInfo.value = {
      title: offer.title,
      description: offer.description,
      categoryId: offer.category?.id ?? null,
      type: offer.type,
      price: String(offer.price),
      isNegotiable: offer.is_negotiable,
      status: offer.status,
    }

    location.value = {
      latitude: offer.location?.latitude ?? null,
      longitude: offer.location?.longitude ?? null,
    }

    existingImages.value = offer.images ?? []
  } catch (err) {
    error.value = extractErrorMessage(
      err,
      "Impossible de charger cette annonce.",
    )
  } finally {
    loadingOffer.value = false
  }
}

/*
|--------------------------------------------------------------------------
| Remove existing image
|--------------------------------------------------------------------------
*/

async function removeExistingImage(imageId: number) {
  if (!offerId.value) {
    return
  }

  try {
    await api.delete(
      `/offers/${offerId.value}/images/${imageId}`,
    )

    existingImages.value =
      existingImages.value.filter(
        (image) => image.id !== imageId,
      )
  } catch (err) {
    error.value = extractErrorMessage(
      err,
      'Impossible de supprimer cette photo.',
    )
  }
}

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

async function handleSubmit() {
  error.value = ''

  if (
    location.value.latitude === null ||
    location.value.longitude === null
  ) {
    error.value =
      "Choisis un emplacement avant de publier."

    step.value = 3

    return
  }

  submitting.value = true

  try {
    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    if (isEdit.value) {
      await api.put(
        `/offers/${offerId.value}`,
        {
          category_id: offerInfo.value.categoryId,

          title: offerInfo.value.title,

          description:
            offerInfo.value.description,

          type: offerInfo.value.type,

          price: offerInfo.value.price,

          is_negotiable:
            offerInfo.value.isNegotiable,

          status: offerInfo.value.status,

          location: {
            latitude:
              location.value.latitude,

            longitude:
              location.value.longitude,
          },
        },
      )

      /*
      |--------------------------------------------------------------------------
      | Upload newly selected images
      |--------------------------------------------------------------------------
      */

      if (images.value.length > 0) {
        const formData = new FormData()

        images.value.forEach((file) => {
          formData.append('images[]', file)
        })

        const response = await api.post<{
          images: {
            id: number
            url: string
          }[]
        }>(
          `/offers/${offerId.value}/images`,
          formData,
        )

        existingImages.value =
          response.data.images
      }

      await router.push({
        name: 'offer-details',
        params: {
          id: offerId.value,
        },
      })

      return
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    const formData = new FormData()

    formData.append(
      'category_id',
      String(offerInfo.value.categoryId),
    )

    formData.append(
      'title',
      offerInfo.value.title,
    )

    formData.append(
      'description',
      offerInfo.value.description,
    )

    formData.append(
      'type',
      offerInfo.value.type,
    )

    formData.append(
      'price',
      offerInfo.value.price,
    )

    formData.append(
      'is_negotiable',
      offerInfo.value.isNegotiable
        ? '1'
        : '0',
    )

    formData.append(
      'status',
      offerInfo.value.status,
    )

    formData.append(
      'location[latitude]',
      String(location.value.latitude),
    )

    formData.append(
      'location[longitude]',
      String(location.value.longitude),
    )

    images.value.forEach((file) => {
      formData.append('images[]', file)
    })

    const response = await api.post<{
      data: Offer
    }>(
      '/offers',
      formData,
    )

    await router.push({
      name: 'offer-details',
      params: {
        id: response.data.data.id,
      },
    })
  } catch (err) {
    error.value = extractErrorMessage(
      err,
      "Impossible d'enregistrer l'annonce.",
    )
  } finally {
    submitting.value = false
  }
}

/*
|--------------------------------------------------------------------------
| Load initial data
|--------------------------------------------------------------------------
*/

onMounted(async () => {
  try {
    categories.value = await fetchCategories()

    await loadOfferForEdit()
  } catch (err) {
    error.value = extractErrorMessage(
      err,
      'Impossible de charger les données.',
    )
  }
})
</script>

<template>
  <div class="mx-auto max-w-2xl px-5 py-8 sm:px-6">
    <!-- Header -->
    <div class="mb-8">
      <div class="mb-2 flex items-center justify-between">
        <div>
          <p
            class="font-mono text-xs tracking-widest text-primary uppercase"
          >
            {{ isEdit ? 'Modifier' : 'Nouvelle annonce' }}
          </p>

          <h1
            class="mt-1 font-display text-3xl font-bold text-primary"
          >
            {{ isEdit
              ? "Modifier l'annonce"
              : 'Publier une annonce' }}
          </h1>
        </div>

        <span
          class="font-mono text-xs text-ink/40"
        >
          {{ step }}/{{ totalSteps }}
        </span>
      </div>

      <!-- Progress -->
      <div
        class="mt-5 h-1.5 overflow-hidden rounded-full bg-ink/10"
      >
        <div
          class="h-full rounded-full bg-primary transition-all duration-300"
          :style="{ width: progress }"
        ></div>
      </div>

      <!-- Step labels -->
      <div
        class="mt-3 grid grid-cols-3 text-center font-mono text-[10px] uppercase"
      >
        <span
          :class="
            step >= 1
              ? 'text-primary'
              : 'text-ink/30'
          "
        >
          Informations
        </span>

        <span
          :class="
            step >= 2
              ? 'text-primary'
              : 'text-ink/30'
          "
        >
          Photos
        </span>

        <span
          :class="
            step >= 3
              ? 'text-primary'
              : 'text-ink/30'
          "
        >
          Emplacement
        </span>
      </div>
    </div>

    <!-- Loading -->
    <div
      v-if="loadingOffer"
      class="rounded-2xl border border-ink/10 bg-surface p-8 text-center"
    >
      <p class="font-mono text-sm text-ink/50">
        Chargement de l'annonce…
      </p>
    </div>

    <!-- Form -->
    <div
      v-else
      class="rounded-2xl border border-ink/10 bg-surface p-5 shadow-sm sm:p-7"
    >
      <!-- Global error -->
      <div
        v-if="error"
        class="mb-6 rounded-xl bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved"
      >
        {{ error }}
      </div>

      <!-- STEP 1 -->
      <OfferInfoStep
        v-if="step === 1"
        v-model="offerInfo"
        :categories="categories"
        @next="nextStep"
      />

      <!-- STEP 2 -->
      <OfferImagesStep
        v-else-if="step === 2"
        v-model="images"
        :existing-images="existingImages"
        :is-edit="isEdit"
        @remove-existing="removeExistingImage"
        @back="previousStep"
        @next="nextStep"
      />

      <!-- STEP 3 -->
      <OfferLocationStep
        v-else
        v-model="location"
        @back="previousStep"
        @submit="handleSubmit"
      />

      <!-- Submit loading -->
      <div
        v-if="submitting"
        class="mt-5 flex items-center justify-center gap-2 rounded-xl bg-primary/5 px-4 py-3 font-mono text-xs text-primary"
      >
        <span>
          {{ isEdit
            ? "Enregistrement des modifications…"
            : "Publication de l'annonce…" }}
        </span>
      </div>
    </div>

    <!-- Cancel -->
    <div class="mt-5 text-center">
      <RouterLink
        :to="{ name: 'home' }"
        class="font-mono text-xs text-ink/40 transition hover:text-primary"
      >
        Annuler
      </RouterLink>
    </div>
  </div>
</template>