<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import dayjs from 'dayjs'
import { useAuthStore } from '@/stores/auth'
import { useFavoritesStore } from '@/stores/favorites'
import api from '@/services/api'
import OfferCard from '@/components/offers/OfferCard.vue'
import ReservationRow from '@/components/reservations/ReservationRow.vue'
import ReviewForm from '@/components/reviews/ReviewForm.vue'
import StarRating from '@/components/reviews/StarRating.vue'
import type { Offer, PaginatedResponse } from '@/types/offer'
import type { Reservation } from '@/types/reservation'

const authStore = useAuthStore()
const favoritesStore = useFavoritesStore()

type Tab = 'offers' | 'favorites' | 'reservations' | 'provider'

const tabs: { key: Tab; label: string }[] = [
  { key: 'offers', label: 'Mes annonces' },
  { key: 'favorites', label: 'Mes favoris' },
  { key: 'reservations', label: 'Mes réservations' },
  { key: 'provider', label: 'Réservations reçues' },
]

const activeTab = ref<Tab>('offers')
const loadedTabs = new Set<Tab>()

const myOffers = ref<Offer[]>([])
const favorites = ref<Offer[]>([])
const reservations = ref<Reservation[]>([])
const providerReservations = ref<Reservation[]>([])

const loading = ref(false)
const error = ref('')

function initials(name: string) {
  return name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

async function loadTab(tab: Tab) {
  if (loadedTabs.has(tab)) {
    return
  }

  loading.value = true
  error.value = ''

  try {
    if (tab === 'offers') {
      const response = await api.get<PaginatedResponse<Offer>>('/offers', { params: { mine: 1 } })
      myOffers.value = response.data.data
    } else if (tab === 'favorites') {
      const response = await api.get<PaginatedResponse<Offer>>('/favorites')
      favorites.value = response.data.data
    } else if (tab === 'reservations') {
      const response = await api.get<PaginatedResponse<Reservation>>('/reservations')
      reservations.value = response.data.data
    } else {
      const response = await api.get<PaginatedResponse<Reservation>>('/provider/reservations')
      providerReservations.value = response.data.data
    }

    loadedTabs.add(tab)
  } catch {
    error.value = 'Impossible de charger cette section.'
  } finally {
    loading.value = false
  }
}

function selectTab(tab: Tab) {
  activeTab.value = tab
  loadTab(tab)
}

async function deleteOffer(id: number) {
  if (!confirm('Supprimer cette annonce ?')) {
    return
  }

  try {
    await api.delete(`/offers/${id}`)
    myOffers.value = myOffers.value.filter((offer) => offer.id !== id)
  } catch {
    error.value = "Impossible de supprimer l'annonce."
  }
}

async function removeFavorite(id: number) {
  try {
    await favoritesStore.toggle(id)
    favorites.value = favorites.value.filter((offer) => offer.id !== id)
  } catch {
    error.value = 'Impossible de retirer ce favori.'
  }
}

async function cancelReservation(id: number) {
  try {
    await api.patch(`/reservations/${id}/cancel`)
    const reservation = reservations.value.find((r) => r.id === id)
    if (reservation) {
      reservation.status = 'cancelled'
    }
  } catch {
    error.value = "Impossible d'annuler cette réservation."
  }
}

async function providerAction(id: number, action: 'confirm' | 'cancel' | 'complete') {
  try {
    await api.patch(`/provider/reservations/${id}/${action}`)
    const reservation = providerReservations.value.find((r) => r.id === id)
    if (reservation) {
      reservation.status = action === 'confirm' ? 'confirmed' : action === 'complete' ? 'completed' : 'cancelled'
    }
  } catch {
    error.value = 'Impossible de mettre à jour cette réservation.'
  }
}

onMounted(() => loadTab('offers'))
</script>

<template>
  <div class="mx-auto max-w-5xl px-6 py-8">
    <div v-if="authStore.user" class="mb-8 flex items-center gap-4 border-b border-ink/10 pb-6">
      <span
        class="flex h-14 w-14 items-center justify-center rounded-full bg-primary font-display text-lg font-bold text-surface"
      >
        {{ initials(authStore.user.name) }}
      </span>
      <div>
        <p class="font-display text-xl font-bold text-ink">{{ authStore.user.name }}</p>
        <p class="font-mono text-xs text-ink/50">
          {{ authStore.user.email }} · membre depuis {{ dayjs(authStore.user.created_at).format('MMMM YYYY') }}
        </p>
      </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-2 border-b border-ink/10">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="border-b-2 px-3 py-2 font-mono text-xs tracking-wide uppercase transition-colors"
        :class="
          activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-ink/50 hover:text-ink'
        "
        @click="selectTab(tab.key)"
      >
        {{ tab.label }}
      </button>
    </div>

    <p v-if="error" class="mb-4 rounded-md bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved">
      {{ error }}
    </p>

    <p v-if="loading" class="font-mono text-sm text-ink/50">Chargement…</p>

    <template v-else>
      <div v-if="activeTab === 'offers'">
        <p v-if="myOffers.length === 0" class="font-mono text-sm text-ink/50">
          Tu n'as encore publié aucune annonce.
        </p>
        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <OfferCard
            v-for="offer in myOffers"
            :id="offer.id"
            :key="offer.id"
            :title="offer.title"
            :price="offer.price"
            :status="offer.status"
            :is-negotiable="offer.is_negotiable"
            :category="offer.category ?? null"
            :images="offer.images"
          >
            <template #actions>
              <RouterLink
                :to="{ name: 'offer-edit', params: { id: offer.id } }"
                class="flex-1 rounded-md border border-ink/15 px-3 py-1.5 text-center text-sm text-ink/70 transition hover:border-primary hover:text-primary"
              >
                Modifier
              </RouterLink>
              <button
                type="button"
                class="flex-1 rounded-md border border-ink/15 px-3 py-1.5 text-sm text-status-reserved transition hover:border-status-reserved"
                @click="deleteOffer(offer.id)"
              >
                Supprimer
              </button>
            </template>
          </OfferCard>
        </div>
      </div>

      <div v-else-if="activeTab === 'favorites'">
        <p v-if="favorites.length === 0" class="font-mono text-sm text-ink/50">Aucune annonce en favori.</p>
        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <OfferCard
            v-for="offer in favorites"
            :id="offer.id"
            :key="offer.id"
            :title="offer.title"
            :price="offer.price"
            :status="offer.status"
            :is-negotiable="offer.is_negotiable"
            :category="offer.category ?? null"
            :images="offer.images"
          >
            <template #actions>
              <button
                type="button"
                class="w-full rounded-md border border-ink/15 px-3 py-1.5 text-sm text-ink/70 transition hover:border-primary hover:text-primary"
                @click="removeFavorite(offer.id)"
              >
                Retirer des favoris
              </button>
            </template>
          </OfferCard>
        </div>
      </div>

      <div v-else-if="activeTab === 'reservations'" class="flex flex-col gap-3">
        <p v-if="reservations.length === 0" class="font-mono text-sm text-ink/50">Aucune réservation.</p>
        <ReservationRow v-for="reservation in reservations" :key="reservation.id" :reservation="reservation">
          <template #actions>
            <button
              v-if="['pending', 'confirmed'].includes(reservation.status)"
              type="button"
              class="rounded-md border border-ink/15 px-3 py-1.5 text-sm text-status-reserved transition hover:border-status-reserved"
              @click="cancelReservation(reservation.id)"
            >
              Annuler
            </button>
          </template>

          <template v-if="reservation.status === 'completed'" #review>
            <div v-if="reservation.review" class="flex items-center gap-2">
              <StarRating :rating="reservation.review.rating" />
              <p v-if="reservation.review.comment" class="font-body text-sm text-ink/70">
                {{ reservation.review.comment }}
              </p>
            </div>
            <ReviewForm
              v-else
              :reservation-id="reservation.id"
              @submitted="(review) => (reservation.review = review)"
            />
          </template>
        </ReservationRow>
      </div>

      <div v-else class="flex flex-col gap-3">
        <p v-if="providerReservations.length === 0" class="font-mono text-sm text-ink/50">
          Aucune réservation reçue.
        </p>
        <ReservationRow
          v-for="reservation in providerReservations"
          :key="reservation.id"
          :reservation="reservation"
          :person-label="reservation.user ? `réservé par ${reservation.user.name}` : null"
        >
          <template #actions>
            <button
              v-if="reservation.status === 'pending'"
              type="button"
              class="rounded-md border border-status-active px-3 py-1.5 text-sm text-status-active transition hover:bg-status-active/10"
              @click="providerAction(reservation.id, 'confirm')"
            >
              Confirmer
            </button>
            <button
              v-if="reservation.status === 'confirmed'"
              type="button"
              class="rounded-md border border-primary px-3 py-1.5 text-sm text-primary transition hover:bg-primary/10"
              @click="providerAction(reservation.id, 'complete')"
            >
              Terminer
            </button>
            <button
              v-if="['pending', 'confirmed'].includes(reservation.status)"
              type="button"
              class="rounded-md border border-ink/15 px-3 py-1.5 text-sm text-status-reserved transition hover:border-status-reserved"
              @click="providerAction(reservation.id, 'cancel')"
            >
              Annuler
            </button>
          </template>
        </ReservationRow>
      </div>
    </template>
  </div>
</template>
