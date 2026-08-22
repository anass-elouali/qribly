<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import { useFavoritesStore } from '@/stores/favorites'
import api from '@/services/api'
import { extractErrorMessage } from '@/utils/errors'

import ProfileHeader from '@/components/profile/ProfileHeader.vue'
import ProfileTabs from '@/components/profile/ProfileTabs.vue'
import ProfileOffers from '@/components/profile/ProfileOffers.vue'
import ProfileFavorites from '@/components/profile/ProfileFavorites.vue'
import ProfileReservations from '@/components/profile/ProfileReservations.vue'
import ProfileProviderReservations from '@/components/profile/ProfileProviderReservations.vue'
import OfferGridSkeleton from '@/components/offers/OfferGridSkeleton.vue'
import AsyncStatePanel from '@/components/ui/AsyncStatePanel.vue'

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

const initialTab: Tab = tabs.some((tab) => tab.key === route.query.tab)
  ? (route.query.tab as Tab)
  : 'offers'

const activeTab = ref<Tab>(initialTab)

const myOffers = ref<Offer[]>([])
const favorites = ref<Offer[]>([])
const reservations = ref<Reservation[]>([])
const providerReservations = ref<Reservation[]>([])

const loading = ref(false)
const actionLoading = ref(false)
const error = ref('')
const cancellingReservationId = ref<number | null>(null)
const reservationActionError = ref('')
const reservationActionSuccess = ref('')

const loadedTabs = reactive(new Set<Tab>())

/*
|--------------------------------------------------------------------------
| Data loading
|--------------------------------------------------------------------------
*/

async function fetchTabData(tab: Tab) {
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
    const response = await api.get<PaginatedResponse<Reservation>>('/reservations')

    reservations.value = response.data.data
  }

  if (tab === 'provider') {
    const response = await api.get<PaginatedResponse<Reservation>>('/provider/reservations')

    providerReservations.value = response.data.data
  }
}

async function loadTab(tab: Tab, force = false) {
  if (loadedTabs.has(tab) && !force) {
    return
  }

  loading.value = true
  error.value = ''

  try {
    await fetchTabData(tab)
    loadedTabs.add(tab)
  } catch (exception) {
    error.value = extractErrorMessage(exception, 'Impossible de charger cette section.')
  } finally {
    loading.value = false
  }
}

// Loads the other tabs quietly in the background so the header counts
// (annonces/favoris/réservations) are accurate from the start instead of
// showing 0 until the user happens to click each tab. Doesn't touch the
// visible loading/error state — that stays tied to the active tab only.
async function preloadRemainingCounts() {
  const remaining = tabs.map((tab) => tab.key).filter((key) => key !== initialTab)

  await Promise.allSettled(
    remaining.map(async (tab) => {
      try {
        await fetchTabData(tab)
        loadedTabs.add(tab)
      } catch {
        // Silent — this is only for the header counts; the tab itself
        // will retry when the user actually opens it.
      }
    }),
  )
}

/*
|--------------------------------------------------------------------------
| Tabs
|--------------------------------------------------------------------------
*/

function selectTab(tab: Tab) {
  activeTab.value = tab
  error.value = ''
  reservationActionError.value = ''
  reservationActionSuccess.value = ''
  loadTab(tab)
}

function retryActiveTab() {
  loadTab(activeTab.value, true)
}

async function runProfileAction(action: () => Promise<void>, fallbackMessage: string) {
  if (actionLoading.value) return

  actionLoading.value = true
  error.value = ''

  try {
    await action()
  } catch (exception) {
    error.value = extractErrorMessage(exception, fallbackMessage)
  } finally {
    actionLoading.value = false
  }
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

  await runProfileAction(async () => {
    await api.delete(`/offers/${id}`)

    myOffers.value = myOffers.value.filter((offer) => offer.id !== id)
  }, "Impossible de supprimer l'annonce.")
}

/*
|--------------------------------------------------------------------------
| Favorites
|--------------------------------------------------------------------------
*/

async function removeFavorite(id: number) {
  await runProfileAction(async () => {
    await favoritesStore.toggle(id)

    favorites.value = favorites.value.filter((offer) => offer.id !== id)
  }, 'Impossible de retirer ce favori.')
}

/*
|--------------------------------------------------------------------------
| User reservations
|--------------------------------------------------------------------------
*/

async function cancelReservation(id: number) {
  if (cancellingReservationId.value !== null) {
    return
  }

  const reservation = reservations.value.find((item) => item.id === id)

  if (!reservation || !['pending', 'confirmed'].includes(reservation.status)) {
    return
  }

  cancellingReservationId.value = id
  reservationActionError.value = ''
  reservationActionSuccess.value = ''

  try {
    await api.patch(`/reservations/${id}/cancel`)
    reservation.status = 'cancelled'
    reservationActionSuccess.value = `La réservation pour « ${reservation.offer?.title ?? 'ce service'} » a été annulée.`
  } catch (exception) {
    reservationActionError.value = extractErrorMessage(
      exception,
      "Impossible d'annuler cette réservation.",
    )
  } finally {
    cancellingReservationId.value = null
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

async function providerAction(id: number, action: 'confirm' | 'cancel' | 'complete') {
  await runProfileAction(async () => {
    await api.patch(`/provider/reservations/${id}/${action}`)

    const reservation = providerReservations.value.find((reservation) => reservation.id === id)

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
  }, 'Impossible de mettre à jour cette réservation.')
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
  preloadRemainingCounts()
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
    <ProfileTabs :tabs="tabs" :active-tab="activeTab" @select="selectTab" />

    <AsyncStatePanel
      v-if="error"
      class="mb-5"
      variant="error"
      title="Cette section n’a pas pu être chargée"
      :message="error"
      action-label="Réessayer"
      compact
      @action="retryActiveTab"
    />

    <OfferGridSkeleton v-if="loading && !loadedTabs.has(activeTab)" :count="3" />

    <p
      v-if="actionLoading"
      class="mb-4 rounded-lg bg-primary/5 px-4 py-3 text-center font-mono text-xs text-primary"
      role="status"
      aria-live="polite"
    >
      Mise à jour en cours…
    </p>

    <div
      v-if="loadedTabs.has(activeTab)"
      :class="loading || actionLoading ? 'pointer-events-none opacity-60' : ''"
      :aria-busy="loading || actionLoading"
      :inert="actionLoading || undefined"
    >
      <ProfileOffers v-if="activeTab === 'offers'" :offers="myOffers" @delete="deleteOffer" />

      <ProfileFavorites
        v-else-if="activeTab === 'favorites'"
        :favorites="favorites"
        @remove="removeFavorite"
      />

      <ProfileReservations
        v-else-if="activeTab === 'reservations'"
        :reservations="reservations"
        :cancelling-id="cancellingReservationId"
        :action-error="reservationActionError"
        :action-success="reservationActionSuccess"
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
    </div>
  </div>
</template>
