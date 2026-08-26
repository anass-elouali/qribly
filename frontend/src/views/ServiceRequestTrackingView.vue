<script setup lang="ts">
import {
  ArrowLeft,
  Banknote,
  CalendarClock,
  Check,
  CircleCheckBig,
  Clock3,
  House,
  Inbox,
  LoaderCircle,
  MapPin,
  MessageCircle,
  RefreshCw,
  Tag,
  UsersRound,
  X,
} from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import api from '@/services/api'
import {
  acceptServiceRequestProposal,
  declineServiceRequestProposal,
  fetchServiceRequest,
} from '@/services/serviceRequests'
import type { ServiceRequest, ServiceRequestProposal } from '@/types/serviceRequest'
import type { Conversation } from '@/types/conversation'
import { useChatStore } from '@/stores/chat'
import { inAppTimeZone } from '@/utils/dateTime'
import { extractErrorMessage } from '@/utils/errors'

const route = useRoute()
const router = useRouter()
const chatStore = useChatStore()
const serviceRequest = ref<ServiceRequest | null>(null)
const loading = ref(true)
const error = ref('')
const actingProposalId = ref<number | null>(null)
const actingAction = ref<'accept' | 'decline' | null>(null)
const actionError = ref('')
const actionSuccess = ref('')
const discussingProposalId = ref<number | null>(null)

const requestId = computed(() => Number(route.params.id))
const proposals = computed(() => serviceRequest.value?.proposals ?? [])
const proposalsCount = computed(
  () => serviceRequest.value?.proposals_count ?? proposals.value.length,
)

const statusLabel = computed(() => {
  const labels: Record<ServiceRequest['status'], string> = {
    open: 'Ouverte',
    fulfilled: 'Finalisée',
    cancelled: 'Annulée',
  }

  return serviceRequest.value ? labels[serviceRequest.value.status] : ''
})

const periodLabel = computed(() => {
  if (!serviceRequest.value) return ''

  const start = inAppTimeZone(serviceRequest.value.desired_start_at)
  const end = inAppTimeZone(serviceRequest.value.desired_end_at)

  if (start.isSame(end, 'day')) {
    return `${start.format('DD/MM/YYYY · HH:mm')}–${end.format('HH:mm')}`
  }

  return `${start.format('DD/MM/YYYY · HH:mm')} → ${end.format('DD/MM/YYYY · HH:mm')}`
})

function formatPrice(value: string | null): string {
  if (!value) return 'À convenir'

  return `${new Intl.NumberFormat('fr-MA', { maximumFractionDigits: 2 }).format(Number(value))} DH`
}

function proposalStatusLabel(status: NonNullable<ServiceRequest['proposals']>[number]['status']) {
  const labels = {
    pending: 'À examiner',
    accepted: 'Acceptée',
    declined: 'Refusée',
    withdrawn: 'Retirée',
  }

  return labels[status]
}

async function loadRequest() {
  if (!Number.isInteger(requestId.value) || requestId.value <= 0) {
    error.value = 'Cette demande est introuvable.'
    loading.value = false
    return
  }

  loading.value = true
  error.value = ''

  try {
    serviceRequest.value = await fetchServiceRequest(requestId.value)
  } catch (exception) {
    const message = extractErrorMessage(
      exception,
      'Impossible de charger le suivi de cette demande.',
    )
    error.value = message.trim() || 'Impossible de charger le suivi de cette demande.'
  } finally {
    loading.value = false
  }
}

async function acceptProposal(proposal: ServiceRequestProposal) {
  if (actingProposalId.value) return

  if (
    !confirm(
      `Confirmer la réservation avec ${proposal.provider?.name ?? 'ce prestataire'} pour ${formatPrice(proposal.proposed_price)} ?`,
    )
  ) {
    return
  }

  actingProposalId.value = proposal.id
  actingAction.value = 'accept'
  actionError.value = ''
  actionSuccess.value = ''

  try {
    await acceptServiceRequestProposal(proposal.id)
    actionSuccess.value = 'Réservation confirmée ! Retrouve-la dans tes réservations.'
    await loadRequest()
  } catch (exception) {
    actionError.value = extractErrorMessage(exception, "Impossible d'accepter cette proposition.")
  } finally {
    actingProposalId.value = null
    actingAction.value = null
  }
}

async function declineProposal(proposal: ServiceRequestProposal) {
  if (actingProposalId.value) return

  actingProposalId.value = proposal.id
  actingAction.value = 'decline'
  actionError.value = ''
  actionSuccess.value = ''

  try {
    await declineServiceRequestProposal(proposal.id)
    await loadRequest()
  } catch (exception) {
    actionError.value = extractErrorMessage(exception, 'Impossible de refuser cette proposition.')
  } finally {
    actingProposalId.value = null
    actingAction.value = null
  }
}

async function discussProposal(proposal: ServiceRequestProposal) {
  if (!proposal.provider || discussingProposalId.value !== null) return

  discussingProposalId.value = proposal.id
  actionError.value = ''
  actionSuccess.value = ''

  try {
    const response = await api.post<Conversation>(
      `/service-request-proposals/${proposal.id}/conversation`,
    )

    chatStore.upsertConversation(response.data)
    await router.push({
      name: 'conversation',
      params: { id: response.data.id },
    })
  } catch (exception) {
    actionError.value = extractErrorMessage(
      exception,
      'Impossible d’ouvrir la discussion avec ce prestataire.',
    )
  } finally {
    discussingProposalId.value = null
  }
}

onMounted(loadRequest)
</script>

<template>
  <section class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 sm:py-12">
    <RouterLink
      :to="{ name: 'home' }"
      class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-primary hover:underline"
    >
      <ArrowLeft :size="17" aria-hidden="true" /> Retour à l’accueil
    </RouterLink>

    <div
      v-if="loading"
      class="mt-6 flex min-h-72 items-center justify-center rounded-2xl border border-ink/10 bg-surface"
      role="status"
    >
      <LoaderCircle :size="30" class="animate-spin text-primary" aria-hidden="true" />
      <span class="ml-3 text-sm text-ink/60">Chargement du suivi…</span>
    </div>

    <div
      v-else-if="error"
      class="mt-6 rounded-2xl border border-accent/30 bg-accent/10 p-6 text-center"
      role="alert"
    >
      <p class="font-display text-xl font-semibold text-primary">Suivi indisponible</p>
      <p class="mt-2 text-sm text-ink/65">{{ error }}</p>
      <button
        type="button"
        class="mt-5 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-surface"
        @click="loadRequest"
      >
        <RefreshCw :size="17" aria-hidden="true" /> Réessayer
      </button>
    </div>

    <template v-else-if="serviceRequest">
      <header class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="font-mono text-xs tracking-[0.14em] text-primary uppercase">
            Suivi de la demande
          </p>
          <h1 class="mt-2 font-display text-3xl font-semibold text-primary sm:text-4xl">
            Demande n°{{ serviceRequest.id }}
          </h1>
        </div>
        <span
          class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold"
          :class="
            serviceRequest.status === 'open'
              ? 'bg-status-active/10 text-status-active'
              : 'bg-ink/10 text-ink/60'
          "
        >
          <CircleCheckBig
            v-if="serviceRequest.status === 'fulfilled'"
            :size="15"
            aria-hidden="true"
          />
          <Clock3 v-else :size="15" aria-hidden="true" />
          {{ statusLabel }}
        </span>
      </header>

      <article class="mt-6 rounded-2xl border border-ink/10 bg-surface p-5 shadow-sm sm:p-7">
        <p class="font-mono text-xs tracking-[0.12em] text-ink/45 uppercase">Besoin publié</p>
        <h2 class="mt-3 max-w-3xl font-display text-2xl font-semibold leading-snug text-primary">
          {{ serviceRequest.summary }}
        </h2>

        <dl class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div class="rounded-xl bg-ground p-4">
            <dt class="flex items-center gap-2 text-xs font-semibold text-ink/45 uppercase">
              <Tag :size="16" aria-hidden="true" /> Service
            </dt>
            <dd class="mt-2 font-semibold text-ink">{{ serviceRequest.category.name }}</dd>
          </div>
          <div class="rounded-xl bg-ground p-4">
            <dt class="flex items-center gap-2 text-xs font-semibold text-ink/45 uppercase">
              <MapPin :size="16" aria-hidden="true" /> Ville
            </dt>
            <dd class="mt-2 font-semibold text-ink">{{ serviceRequest.city }}</dd>
          </div>
          <div class="rounded-xl bg-ground p-4">
            <dt class="flex items-center gap-2 text-xs font-semibold text-ink/45 uppercase">
              <CalendarClock :size="16" aria-hidden="true" /> Disponibilité
            </dt>
            <dd class="mt-2 font-semibold text-ink">{{ periodLabel }}</dd>
          </div>
          <div class="rounded-xl bg-ground p-4">
            <dt class="flex items-center gap-2 text-xs font-semibold text-ink/45 uppercase">
              <Banknote :size="16" aria-hidden="true" /> Budget maximum
            </dt>
            <dd class="mt-2 font-semibold text-ink">
              {{ formatPrice(serviceRequest.budget_max) }}
            </dd>
          </div>
          <div class="rounded-xl bg-ground p-4">
            <dt class="flex items-center gap-2 text-xs font-semibold text-ink/45 uppercase">
              <House :size="16" aria-hidden="true" /> Déplacement
            </dt>
            <dd class="mt-2 font-semibold text-ink">
              {{ serviceRequest.at_home ? 'À mon domicile' : 'Je me déplace' }}
            </dd>
          </div>
          <div class="rounded-xl bg-ground p-4">
            <dt class="flex items-center gap-2 text-xs font-semibold text-ink/45 uppercase">
              <UsersRound :size="16" aria-hidden="true" /> Propositions
            </dt>
            <dd class="mt-2 font-semibold text-ink">{{ proposalsCount }}</dd>
          </div>
        </dl>
      </article>

      <section class="mt-6 rounded-2xl border border-ink/10 bg-surface p-5 shadow-sm sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="font-mono text-xs tracking-[0.12em] text-primary uppercase">Réponses</p>
            <h2 class="mt-1 font-display text-2xl font-semibold text-primary">
              Propositions reçues ({{ proposalsCount }})
            </h2>
          </div>
          <button
            type="button"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-ink/15 px-4 text-sm font-semibold text-ink transition hover:border-primary"
            @click="loadRequest"
          >
            <RefreshCw :size="16" aria-hidden="true" /> Actualiser
          </button>
        </div>

        <p
          v-if="actionSuccess"
          class="mt-6 rounded-lg border border-status-active/20 bg-status-active/5 px-4 py-3 text-sm text-status-active"
          role="status"
          aria-live="polite"
        >
          {{ actionSuccess }}
        </p>

        <p
          v-if="actionError"
          class="mt-6 rounded-lg border border-status-reserved/20 bg-status-reserved/5 px-4 py-3 text-sm text-status-reserved"
          role="alert"
        >
          {{ actionError }}
        </p>

        <div v-if="proposals.length === 0" class="mt-6 rounded-xl bg-ground px-5 py-9 text-center">
          <Inbox :size="32" class="mx-auto text-primary/45" aria-hidden="true" />
          <p class="mt-3 font-semibold text-ink">En attente des prestataires</p>
          <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-ink/55">
            Les professionnels compatibles ont été prévenus. Leurs prix, horaires et messages
            apparaîtront ici.
          </p>
        </div>

        <ul v-else class="mt-6 space-y-3">
          <li
            v-for="proposal in proposals"
            :key="proposal.id"
            class="rounded-xl border border-ink/10 p-4 sm:p-5"
          >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p class="font-semibold text-ink">{{ proposal.provider?.name ?? 'Prestataire' }}</p>
                <p v-if="proposal.offer" class="mt-1 text-sm text-ink/55">
                  {{ proposal.offer.title }}
                </p>
              </div>
              <div class="sm:text-right">
                <p class="font-display text-xl font-semibold text-primary">
                  {{ formatPrice(proposal.proposed_price) }}
                </p>
                <p class="mt-1 text-xs font-semibold text-ink/45">
                  {{ proposalStatusLabel(proposal.status) }}
                </p>
              </div>
            </div>
            <p class="mt-3 flex items-center gap-2 text-sm text-ink/60">
              <CalendarClock :size="16" aria-hidden="true" />
              {{ inAppTimeZone(proposal.scheduled_at).format('DD/MM/YYYY · HH:mm') }}
            </p>
            <p
              v-if="proposal.message"
              class="mt-3 rounded-lg bg-ground p-3 text-sm leading-6 text-ink/65"
            >
              <span class="mb-1 block text-xs font-semibold text-ink/45 uppercase">
                Message du prestataire
              </span>
              {{ proposal.message }}
            </p>

            <div
              v-if="proposal.status === 'pending' && serviceRequest.status === 'open'"
              class="mt-4 flex flex-wrap gap-2"
            >
              <button
                type="button"
                :disabled="actingProposalId !== null || discussingProposalId !== null"
                class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border border-primary/25 px-4 text-sm font-semibold text-primary transition hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50"
                @click="discussProposal(proposal)"
              >
                <MessageCircle :size="15" aria-hidden="true" />
                {{ discussingProposalId === proposal.id ? 'Ouverture…' : 'Discuter' }}
              </button>
              <button
                type="button"
                :disabled="actingProposalId !== null || discussingProposalId !== null"
                class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-status-active px-4 text-sm font-semibold text-surface transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                @click="acceptProposal(proposal)"
              >
                <Check :size="15" aria-hidden="true" />
                {{
                  actingProposalId === proposal.id && actingAction === 'accept'
                    ? 'Confirmation…'
                    : 'Accepter'
                }}
              </button>
              <button
                type="button"
                :disabled="actingProposalId !== null || discussingProposalId !== null"
                class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border border-ink/15 px-4 text-sm font-semibold text-ink/60 transition hover:border-status-reserved/40 hover:text-status-reserved disabled:cursor-not-allowed disabled:opacity-50"
                @click="declineProposal(proposal)"
              >
                <X :size="15" aria-hidden="true" />
                {{
                  actingProposalId === proposal.id && actingAction === 'decline'
                    ? 'Refus…'
                    : 'Refuser'
                }}
              </button>
            </div>
          </li>
        </ul>
      </section>

      <div class="mt-6 flex flex-col gap-3 sm:flex-row">
        <RouterLink
          :to="{ name: 'service-request-create' }"
          class="inline-flex min-h-11 items-center justify-center rounded-lg border border-ink/15 px-5 text-sm font-semibold text-ink transition hover:border-primary"
        >
          Nouvelle demande
        </RouterLink>
        <RouterLink
          :to="{ name: 'home' }"
          class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-5 text-sm font-semibold text-surface transition hover:opacity-95"
        >
          Retour à l’accueil
        </RouterLink>
      </div>
    </template>
  </section>
</template>
