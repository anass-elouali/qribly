<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

import ProviderActionDialog from '@/components/reservations/ProviderActionDialog.vue'
import ProviderAvailabilityEditor from '@/components/profile/ProviderAvailabilityEditor.vue'
import ProviderReservationCard from '@/components/reservations/ProviderReservationCard.vue'
import ReservationDetailsModal from '@/components/reservations/ReservationDetailsModal.vue'
import api from '@/services/api'
import { useChatStore } from '@/stores/chat'

import type { ProviderReservationAction, Reservation } from '@/types/reservation'

const router = useRouter()
const chatStore = useChatStore()

const props = defineProps<{
  reservations: Reservation[]
  activeAction?: { id: number; action: ProviderReservationAction } | null
  actionError?: string
  actionSuccess?: string
}>()

const emit = defineEmits<{
  action: [id: number, action: ProviderReservationAction]
  clearFeedback: []
}>()

type ProviderFilter = 'all' | 'pending' | 'confirmed' | 'completed' | 'cancelled'

const providerFilter = ref<ProviderFilter>('all')
const nowMs = ref(Date.now())
let clockInterval: ReturnType<typeof setInterval> | undefined

onMounted(() => {
  clockInterval = setInterval(() => {
    nowMs.value = Date.now()
  }, 30_000)
})

onBeforeUnmount(() => {
  if (clockInterval) {
    clearInterval(clockInterval)
  }
})
const providerFilters: { key: ProviderFilter; label: string }[] = [
  { key: 'all', label: 'Toutes' },
  { key: 'pending', label: 'En attente' },
  { key: 'confirmed', label: 'Confirmées' },
  { key: 'completed', label: 'Terminées' },
  { key: 'cancelled', label: 'Annulées' },
]

const providerStats = computed(() => ({
  total: props.reservations.length,
  pending: props.reservations.filter((reservation) => reservation.status === 'pending').length,
  confirmed: props.reservations.filter((reservation) => reservation.status === 'confirmed').length,
  completed: props.reservations.filter((reservation) => reservation.status === 'completed').length,
  cancelled: props.reservations.filter((reservation) => reservation.status === 'cancelled').length,
}))

const filteredReservations = computed(() => {
  if (providerFilter.value === 'all') {
    return props.reservations
  }

  return props.reservations.filter((reservation) => reservation.status === providerFilter.value)
})

const selectedReservation = ref<Reservation | null>(null)
const pendingConfirmation = ref<{
  reservation: Reservation
  action: ProviderReservationAction
} | null>(null)
const contactError = ref('')
const contactingUserId = ref<number | null>(null)

function viewReservation(reservation: Reservation) {
  selectedReservation.value = reservation
  contactError.value = ''
}

function closeReservationDetails() {
  if (props.activeAction) {
    return
  }

  selectedReservation.value = null
  contactError.value = ''
}

function requestAction(id: number, action: ProviderReservationAction) {
  const reservation = props.reservations.find((item) => item.id === id)

  if (!reservation || props.activeAction) {
    return
  }

  emit('clearFeedback')
  pendingConfirmation.value = { reservation, action }
}

function submitPendingAction() {
  if (!pendingConfirmation.value || props.activeAction) {
    return
  }

  emit('action', pendingConfirmation.value.reservation.id, pendingConfirmation.value.action)
}

function closeActionDialog() {
  if (props.activeAction) {
    return
  }

  pendingConfirmation.value = null
  emit('clearFeedback')
}

function activeActionFor(reservationId: number) {
  return props.activeAction?.id === reservationId ? props.activeAction.action : null
}

async function messageCustomer(userId: number) {
  if (!userId || contactingUserId.value !== null) {
    return
  }

  contactError.value = ''
  contactingUserId.value = userId

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
  } finally {
    contactingUserId.value = null
  }
}

watch(
  () => props.actionSuccess,
  (success) => {
    if (success) {
      pendingConfirmation.value = null
    }
  },
)
</script>

<template>
  <section>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="font-mono text-[0.65rem] tracking-[0.2em] text-primary uppercase">
          Espace fournisseur
        </p>

        <h2 class="mt-1 font-display text-2xl font-bold text-ink">Réservations reçues</h2>

        <p class="mt-1 max-w-xl font-body text-sm text-ink/50">
          Gérez les réservations de vos services et suivez vos rendez-vous.
        </p>
      </div>
    </div>

    <ProviderAvailabilityEditor />

    <div class="mb-7 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">Total</p>
        <p class="mt-2 font-display text-2xl font-bold text-ink">{{ providerStats.total }}</p>
      </div>

      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">En attente</p>
        <p class="mt-2 font-display text-2xl font-bold text-ink">{{ providerStats.pending }}</p>
      </div>

      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">Confirmées</p>
        <p class="mt-2 font-display text-2xl font-bold text-ink">{{ providerStats.confirmed }}</p>
      </div>

      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">Terminées</p>
        <p class="mt-2 font-display text-2xl font-bold text-ink">{{ providerStats.completed }}</p>
      </div>

      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">Annulées</p>
        <p class="mt-2 font-display text-2xl font-bold text-ink">{{ providerStats.cancelled }}</p>
      </div>
    </div>

    <p
      v-if="actionSuccess"
      class="mb-5 rounded-lg border border-status-active/20 bg-status-active/5 px-4 py-3 font-body text-sm text-status-active"
      role="status"
      aria-live="polite"
    >
      {{ actionSuccess }}
    </p>

    <p
      v-if="actionError && !pendingConfirmation"
      class="mb-5 rounded-lg border border-status-reserved/20 bg-status-reserved/5 px-4 py-3 font-body text-sm text-status-reserved"
      role="alert"
    >
      {{ actionError }}
    </p>

    <p
      v-if="contactError && !selectedReservation"
      class="mb-5 rounded-lg border border-status-reserved/20 bg-status-reserved/5 px-4 py-3 font-body text-sm text-status-reserved"
      role="alert"
    >
      {{ contactError }}
    </p>

    <div class="mb-6 flex flex-wrap gap-2" aria-label="Filtrer les réservations reçues">
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
        :aria-pressed="providerFilter === filter.key"
        @click="providerFilter = filter.key"
      >
        {{ filter.label }}
      </button>
    </div>

    <div class="flex flex-col gap-4">
      <div
        v-if="filteredReservations.length === 0"
        class="rounded-xl border border-dashed border-ink/15 px-6 py-16 text-center"
      >
        <p class="font-display text-lg font-bold text-ink">Aucune réservation</p>
        <p class="mx-auto mt-2 max-w-md font-body text-sm text-ink/50">
          Aucune réservation ne correspond à ce filtre.
        </p>
      </div>

      <ProviderReservationCard
        v-for="reservation in filteredReservations"
        :key="reservation.id"
        :reservation="reservation"
        :now-ms="nowMs"
        :active-action="activeActionFor(reservation.id)"
        :contacting="contactingUserId === reservation.user?.id"
        @view="viewReservation"
        @message="messageCustomer"
        @confirm="requestAction($event, 'confirm')"
        @cancel="requestAction($event, 'cancel')"
        @complete="requestAction($event, 'complete')"
      />
    </div>

    <ReservationDetailsModal
      v-if="selectedReservation"
      :reservation="selectedReservation"
      :now-ms="nowMs"
      :contact-error="contactError"
      :active-action="activeActionFor(selectedReservation.id)"
      :contacting="contactingUserId === selectedReservation.user?.id"
      @close="closeReservationDetails"
      @message="messageCustomer"
      @confirm="requestAction($event, 'confirm')"
      @cancel="requestAction($event, 'cancel')"
      @complete="requestAction($event, 'complete')"
    />

    <ProviderActionDialog
      v-if="pendingConfirmation"
      :reservation="pendingConfirmation.reservation"
      :action="pendingConfirmation.action"
      :busy="Boolean(activeAction)"
      :error="actionError"
      @close="closeActionDialog"
      @confirm="submitPendingAction"
    />
  </section>
</template>
