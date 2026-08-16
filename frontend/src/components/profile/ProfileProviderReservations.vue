<script setup lang="ts">
import { computed, ref } from 'vue'

import ProviderReservationCard from '@/components/reservations/ProviderReservationCard.vue'
import ReservationDetailsModal from '@/components/reservations/ReservationDetailsModal.vue'

import type { Reservation } from '@/types/reservation'

import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useChatStore } from '@/stores/chat'

const router = useRouter()
const chatStore = useChatStore()

const props = defineProps<{
  reservations: Reservation[]
}>()

const emit = defineEmits<{
  confirm: [id: number]
  cancel: [id: number]
  complete: [id: number]
}>()

type ProviderFilter =
  | 'all'
  | 'pending'
  | 'confirmed'
  | 'completed'
  | 'cancelled'

const providerFilter = ref<ProviderFilter>('all')

const providerFilters: {
  key: ProviderFilter
  label: string
}[] = [
  { key: 'all', label: 'Toutes' },
  { key: 'pending', label: 'En attente' },
  { key: 'confirmed', label: 'Confirmées' },
  { key: 'completed', label: 'Terminées' },
  { key: 'cancelled', label: 'Annulées' },
]

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

const providerStats = computed(() => ({
  total: props.reservations.length,

  pending: props.reservations.filter(
    (reservation) => reservation.status === 'pending',
  ).length,

  confirmed: props.reservations.filter(
    (reservation) => reservation.status === 'confirmed',
  ).length,

  completed: props.reservations.filter(
    (reservation) => reservation.status === 'completed',
  ).length,

  cancelled: props.reservations.filter(
    (reservation) => reservation.status === 'cancelled',
  ).length,
}))

/*
|--------------------------------------------------------------------------
| Filtering
|--------------------------------------------------------------------------
*/

const filteredReservations = computed(() => {
  if (providerFilter.value === 'all') {
    return props.reservations
  }

  return props.reservations.filter(
    (reservation) => reservation.status === providerFilter.value,
  )
})

/*
|--------------------------------------------------------------------------
| Reservation details modal
|--------------------------------------------------------------------------
*/

const selectedReservation = ref<Reservation | null>(null)
const contactError = ref('')

function viewReservation(reservation: Reservation) {
  selectedReservation.value = reservation
  contactError.value = ''
}

function closeReservationDetails() {
  selectedReservation.value = null
  contactError.value = ''
}

/*
|--------------------------------------------------------------------------
| Reservation actions
|--------------------------------------------------------------------------
|
| We close the modal after an action because the parent ProfileView
| will update the reservation status.
|--------------------------------------------------------------------------
*/

function confirmReservation(id: number) {
  emit('confirm', id)
  selectedReservation.value = null
}

function cancelReservation(id: number) {
  emit('cancel', id)
  selectedReservation.value = null
}

function completeReservation(id: number) {
  emit('complete', id)
  selectedReservation.value = null
}


async function messageCustomer(userId: number) {
  if (!userId) {
    return
  }

  contactError.value = ''

  try {
    const response = await api.post('/conversations', {
      user_id: userId,
    })

    const conversation = response.data

    chatStore.upsertConversation(conversation)

    selectedReservation.value = null

    await router.push({
      name: 'conversation',
      params: {
        id: conversation.id,
      },
    })
  } catch {
    contactError.value = 'Impossible de contacter ce client pour le moment.'
  }
}
</script>

<template>
  <section>
    <!-- ========================================================= -->
    <!-- HEADER                                                    -->
    <!-- ========================================================= -->

    <div
      class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
    >
      <div>
        <p
          class="font-mono text-[0.65rem] tracking-[0.2em] text-primary uppercase"
        >
          Espace fournisseur
        </p>

        <h2 class="mt-1 font-display text-2xl font-bold text-ink">
          Réservations reçues
        </h2>

        <p class="mt-1 max-w-xl font-body text-sm text-ink/50">
          Gérez les réservations de vos services et suivez vos rendez-vous.
        </p>
      </div>
    </div>

    <!-- ========================================================= -->
    <!-- STATISTICS                                                -->
    <!-- ========================================================= -->

    <div class="mb-7 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <!-- Total -->
      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p
          class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
          Total
        </p>

        <p class="mt-2 font-display text-2xl font-bold text-ink">
          {{ providerStats.total }}
        </p>
      </div>

      <!-- Pending -->
      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p
          class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
          En attente
        </p>

        <p class="mt-2 font-display text-2xl font-bold text-ink">
          {{ providerStats.pending }}
        </p>
      </div>

      <!-- Confirmed -->
      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p
          class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
          Confirmées
        </p>

        <p class="mt-2 font-display text-2xl font-bold text-ink">
          {{ providerStats.confirmed }}
        </p>
      </div>

      <!-- Completed -->
      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p
          class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
          Terminées
        </p>

        <p class="mt-2 font-display text-2xl font-bold text-ink">
          {{ providerStats.completed }}
        </p>
      </div>
    </div>

    <!-- ========================================================= -->
    <!-- FILTERS                                                   -->
    <!-- ========================================================= -->

    <div class="mb-6 flex flex-wrap gap-2">
      <button
        v-for="filter in providerFilters"
        :key="filter.key"
        type="button"
        class="rounded-full border px-3 py-1.5 font-mono text-xs transition"
        :class="
          providerFilter === filter.key
            ? 'border-primary bg-primary text-surface'
            : 'border-ink/10 text-ink/55 hover:border-ink/20 hover:text-ink'
        "
        @click="providerFilter = filter.key"
      >
        {{ filter.label }}
      </button>
    </div>

    <!-- ========================================================= -->
    <!-- RESERVATIONS                                              -->
    <!-- ========================================================= -->

    <div class="flex flex-col gap-4">
      <!-- Empty state -->
      <div
        v-if="filteredReservations.length === 0"
        class="rounded-xl border border-dashed border-ink/15 px-6 py-16 text-center"
      >
        <p class="font-display text-lg font-bold text-ink">
          Aucune réservation
        </p>

        <p class="mx-auto mt-2 max-w-md font-body text-sm text-ink/50">
          Aucune réservation ne correspond à ce filtre.
        </p>
      </div>

      <!-- Reservation cards -->
      <ProviderReservationCard
        v-for="reservation in filteredReservations"
        :key="reservation.id"
        :reservation="reservation"
        @view="viewReservation"
        @confirm="confirmReservation"
        @cancel="cancelReservation"
        @complete="completeReservation"
      />
    </div>

    <!-- ========================================================= -->
    <!-- RESERVATION DETAILS MODAL                                 -->
    <!-- ========================================================= -->

    <ReservationDetailsModal
      v-if="selectedReservation"
      :reservation="selectedReservation"
      :contact-error="contactError"
      @close="closeReservationDetails"
      @message="messageCustomer"
      @confirm="confirmReservation"
      @cancel="cancelReservation"
      @complete="completeReservation"
    />
  </section>
</template>