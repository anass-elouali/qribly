<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import { useFavoritesStore } from '@/stores/favorites'
import api from '@/services/api'

import ProfileHeader from '@/components/profile/ProfileHeader.vue'
import ProfileTabs from '@/components/profile/ProfileTabs.vue'
import ProfileOffers from '@/components/profile/ProfileOffers.vue'
import ProfileFavorites from '@/components/profile/ProfileFavorites.vue'
import ProfileReservations from '@/components/profile/ProfileReservations.vue'
import ProfileProviderReservations from '@/components/profile/ProfileProviderReservations.vue'

import type { Offer, PaginatedResponse } from '@/types/offer'
import type { Reservation } from '@/types/reservation'

const route = useRoute()
const authStore = useAuthStore()
const favoritesStore = useFavoritesStore()

type Tab = 'offers' | 'favorites' | 'reservations' | 'provider'

const tabs: { key: Tab; label: string }[] = [
  { key: 'offers', label: 'Mes annonces' },
  { key: 'favorites', label: 'Mes favoris' },
  { key: 'reservations', label: 'Mes réservations' },
  { key: 'provider', label: 'Réservations reçues' },
]

const initialTab: Tab =
  tabs.some((tab) => tab.key === route.query.tab)
    ? (route.query.tab as Tab)
    : 'offers'

const activeTab = ref<Tab>(initialTab)

const myOffers = ref<Offer[]>([])
const favorites = ref<Offer[]>([])
const reservations = ref<Reservation[]>([])
const providerReservations = ref<Reservation[]>([])

const loading = ref(false)
const error = ref('')

const loadedTabs = new Set<Tab>()

/*
|--------------------------------------------------------------------------
| Data loading
|--------------------------------------------------------------------------
*/

async function loadTab(tab: Tab) {
  if (loadedTabs.has(tab)) {
    return
  }

  loading.value = true
  error.value = ''

  try {
    if (tab === 'offers') {
      const response = await api.get<PaginatedResponse<Offer>>('/offers', {
        params: {
          mine: 1,
        },
      })

      myOffers.value = response.data.data
    }

    if (tab === 'favorites') {
      const response = await api.get<PaginatedResponse<Offer>>('/favorites')

      favorites.value = response.data.data
    }

    if (tab === 'reservations') {
      const response = await api.get<PaginatedResponse<Reservation>>(
        '/reservations',
      )

      reservations.value = response.data.data
    }

    if (tab === 'provider') {
      const response = await api.get<PaginatedResponse<Reservation>>(
        '/provider/reservations',
      )

      providerReservations.value = response.data.data
    }

    loadedTabs.add(tab)
  } catch {
    error.value = 'Impossible de charger cette section.'
  } finally {
    loading.value = false
  }
}

/*
|--------------------------------------------------------------------------
| Tabs
|--------------------------------------------------------------------------
*/

function selectTab(tab: Tab) {
  activeTab.value = tab
  loadTab(tab)
}

/*
|--------------------------------------------------------------------------
| Offers
|--------------------------------------------------------------------------
*/

async function deleteOffer(id: number) {
  if (!confirm('Supprimer cette annonce ?')) {
    return
  }

  try {
    await api.delete(`/offers/${id}`)

    myOffers.value = myOffers.value.filter(
      (offer) => offer.id !== id,
    )
  } catch {
    error.value = "Impossible de supprimer l'annonce."
  }
}

/*
|--------------------------------------------------------------------------
| Favorites
|--------------------------------------------------------------------------
*/

async function removeFavorite(id: number) {
  try {
    await favoritesStore.toggle(id)

    favorites.value = favorites.value.filter(
      (offer) => offer.id !== id,
    )
  } catch {
    error.value = 'Impossible de retirer ce favori.'
  }
}

/*
|--------------------------------------------------------------------------
| User reservations
|--------------------------------------------------------------------------
*/

async function cancelReservation(id: number) {
  try {
    await api.patch(`/reservations/${id}/cancel`)

    const reservation = reservations.value.find(
      (reservation) => reservation.id === id,
    )

    if (reservation) {
      reservation.status = 'cancelled'
    }
  } catch {
    error.value = "Impossible d'annuler cette réservation."
  }
}

function handleReviewSubmitted(
  reservation: Reservation,
  review: NonNullable<Reservation['review']>,
) {
  reservation.review = review
}

/*
|--------------------------------------------------------------------------
| Provider reservations
|--------------------------------------------------------------------------
*/

async function providerAction(
  id: number,
  action: 'confirm' | 'cancel' | 'complete',
) {
  try {
    await api.patch(`/provider/reservations/${id}/${action}`)

    const reservation = providerReservations.value.find(
      (reservation) => reservation.id === id,
    )

    if (!reservation) {
      return
    }

    if (action === 'confirm') {
      reservation.status = 'confirmed'
    }

    if (action === 'complete') {
      reservation.status = 'completed'
    }

    if (action === 'cancel') {
      reservation.status = 'cancelled'
    }
  } catch {
    error.value = 'Impossible de mettre à jour cette réservation.'
  }
}

/*
|--------------------------------------------------------------------------
| Profile stats
|--------------------------------------------------------------------------
*/

const offersCount = computed(() => myOffers.value.length)
const favoritesCount = computed(() => favorites.value.length)
const reservationsCount = computed(() => reservations.value.length)

/*
|--------------------------------------------------------------------------
| Initial load
|--------------------------------------------------------------------------
*/

onMounted(() => {
  loadTab(initialTab)
})
</script>

<template>
  <div class="mx-auto max-w-6xl px-6 py-8 lg:py-10">

    <!-- Profile header -->
    <ProfileHeader
      :offers-count="offersCount"
      :favorites-count="favoritesCount"
      :reservations-count="reservationsCount"
    />

    <!-- Tabs -->
    <ProfileTabs
      :tabs="tabs"
      :active-tab="activeTab"
      @select="selectTab"
    />

    <!-- Error -->
    <p
      v-if="error"
      class="mb-5 rounded-md bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved"
    >
      {{ error }}
    </p>

    <!-- Loading -->
    <div
      v-if="loading"
      class="flex min-h-48 items-center justify-center"
    >
      <p class="font-mono text-sm text-ink/50">
        Chargement…
      </p>
    </div>

    <!-- Content -->
    <template v-else>

      <ProfileOffers
        v-if="activeTab === 'offers'"
        :offers="myOffers"
        @delete="deleteOffer"
      />

      <ProfileFavorites
        v-else-if="activeTab === 'favorites'"
        :favorites="favorites"
        @remove="removeFavorite"
      />

      <ProfileReservations
        v-else-if="activeTab === 'reservations'"
        :reservations="reservations"
        @cancel="cancelReservation"
        @review-submitted="handleReviewSubmitted"
      />

      <ProfileProviderReservations
        v-else
        :reservations="providerReservations"
        @confirm="providerAction($event, 'confirm')"
        @cancel="providerAction($event, 'cancel')"
        @complete="providerAction($event, 'complete')"
      />

    </template>
  </div>
</template>