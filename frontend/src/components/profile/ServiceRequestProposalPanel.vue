<script setup lang="ts">
import { computed, ref } from 'vue'
import dayjs from 'dayjs'
import { Banknote, Pencil, Send, X } from 'lucide-vue-next'

import BookingSlotPicker from '@/components/reservations/BookingSlotPicker.vue'
import {
  upsertServiceRequestProposal,
  withdrawServiceRequestProposal,
} from '@/services/serviceRequests'
import { extractErrorMessage } from '@/utils/errors'
import {
  dateTimeValueToApiDateTime,
  inAppTimeZone,
  parseDateTimeValue,
} from '@/utils/dateTime'
import type { ServiceRequest, ServiceRequestProposal } from '@/types/serviceRequest'

const props = defineProps<{
  request: ServiceRequest
}>()

const emit = defineEmits<{
  updated: [proposal: ServiceRequestProposal]
}>()

const statusLabels: Record<ServiceRequestProposal['status'], string> = {
  pending: 'Proposition envoyée',
  accepted: 'Proposition acceptée',
  declined: 'Proposition refusée',
  withdrawn: 'Proposition retirée',
}

const proposal = computed(() => props.request.proposals?.[0] ?? null)
const isRequestOpen = computed(
  () => props.request.status === 'open' && dayjs(props.request.expires_at).isAfter(dayjs()),
)
const isLocked = computed(() => proposal.value?.status === 'accepted')

const minScheduledAt = computed(() => {
  const now = dayjs().add(1, 'minute')
  const desiredStart = dayjs(props.request.desired_start_at)

  return inAppTimeZone((desiredStart.isAfter(now) ? desiredStart : now).toISOString()).format(
    'YYYY-MM-DDTHH:mm',
  )
})

const maxScheduledAt = computed(() => {
  const duration = props.request.matched_offer?.service_duration_minutes ?? 60

  return inAppTimeZone(props.request.desired_end_at)
    .subtract(duration, 'minute')
    .format('YYYY-MM-DDTHH:mm')
})

const editing = ref(false)
const proposedPrice = ref('')
const scheduledAt = ref('')
const scheduledAtTouched = ref(false)
const message = ref('')
const saving = ref(false)
const withdrawing = ref(false)
const formError = ref('')

const scheduledAtError = computed(() => {
  if (!scheduledAtTouched.value) {
    return ''
  }

  if (!scheduledAt.value) {
    return 'Choisis une date et une heure.'
  }

  const duration = props.request.matched_offer?.service_duration_minutes ?? 60
  const selectedStart = parseDateTimeValue(scheduledAt.value)
  const selectedEnd = selectedStart.add(duration, 'minute')

  if (!selectedStart.isValid()) {
    return 'Choisis une date et une heure valides.'
  }

  if (
    selectedStart.isBefore(dayjs(props.request.desired_start_at)) ||
    selectedEnd.isAfter(dayjs(props.request.desired_end_at))
  ) {
    return 'Choisis un créneau compris dans la période demandée.'
  }

  return ''
})

function startEditing() {
  formError.value = ''
  proposedPrice.value = proposal.value?.proposed_price ?? props.request.matched_offer?.price ?? ''
  scheduledAt.value = proposal.value?.scheduled_at ?? ''
  message.value = proposal.value?.message ?? ''
  scheduledAtTouched.value = false
  editing.value = true
}

function cancelEditing() {
  editing.value = false
  formError.value = ''
}

async function submitProposal() {
  const offer = props.request.matched_offer

  if (!offer || saving.value) {
    return
  }

  scheduledAtTouched.value = true

  if (scheduledAtError.value) {
    return
  }

  saving.value = true
  formError.value = ''

  try {
    const result = await upsertServiceRequestProposal(props.request.id, {
      offer_id: offer.id,
      proposed_price: Number(proposedPrice.value),
      scheduled_at: dateTimeValueToApiDateTime(scheduledAt.value),
      message: message.value.trim() || undefined,
    })

    emit('updated', result)
    editing.value = false
  } catch (exception) {
    formError.value = extractErrorMessage(exception, "Impossible d'envoyer la proposition.")
  } finally {
    saving.value = false
  }
}

async function withdraw() {
  if (!proposal.value || withdrawing.value) {
    return
  }

  withdrawing.value = true
  formError.value = ''

  try {
    const result = await withdrawServiceRequestProposal(proposal.value.id)
    emit('updated', result)
  } catch (exception) {
    formError.value = extractErrorMessage(exception, 'Impossible de retirer la proposition.')
  } finally {
    withdrawing.value = false
  }
}
</script>

<template>
  <div class="mt-5 border-t border-ink/10 pt-5">
    <p v-if="!request.matched_offer" class="font-body text-sm text-ink/50">
      Aucune annonce compatible n’a pu être associée à cette demande.
    </p>

    <div v-else-if="proposal && !editing" class="flex flex-col gap-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <span
          class="w-fit rounded-full border px-3 py-1.5 font-mono text-xs font-semibold"
          :class="
            proposal.status === 'accepted' || proposal.status === 'pending'
              ? 'border-status-active/20 bg-status-active/5 text-status-active'
              : 'border-status-reserved/20 bg-status-reserved/5 text-status-reserved'
          "
        >
          {{ statusLabels[proposal.status] }}
        </span>

        <div v-if="!isLocked && isRequestOpen" class="flex gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-primary px-3 py-1.5 font-mono text-xs text-primary transition hover:bg-primary/5"
            @click="startEditing"
          >
            <Pencil :size="13" aria-hidden="true" />
            {{ proposal.status === 'pending' ? 'Modifier' : 'Renvoyer' }}
          </button>

          <button
            v-if="proposal.status === 'pending'"
            type="button"
            :disabled="withdrawing"
            class="inline-flex items-center gap-1.5 rounded-lg border border-ink/15 px-3 py-1.5 font-mono text-xs text-ink/60 transition hover:border-status-reserved/40 hover:text-status-reserved disabled:opacity-50"
            @click="withdraw"
          >
            <X :size="13" aria-hidden="true" />
            {{ withdrawing ? 'Retrait…' : 'Retirer' }}
          </button>
        </div>
      </div>

      <dl class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-lg bg-ground px-4 py-3">
          <dt class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">
            Annonce utilisée
          </dt>
          <dd class="mt-1.5 font-body text-sm font-semibold text-ink">
            {{ request.matched_offer.title }}
          </dd>
        </div>

        <div class="rounded-lg bg-ground px-4 py-3">
          <dt class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">Prix proposé</dt>
          <dd class="mt-1.5 font-body text-sm font-semibold text-ink">
            {{ Number(proposal.proposed_price).toFixed(0) }} DH
          </dd>
        </div>

        <div class="rounded-lg bg-ground px-4 py-3">
          <dt class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">Créneau</dt>
          <dd class="mt-1.5 font-body text-sm font-semibold text-ink">
            {{ inAppTimeZone(proposal.scheduled_at).format('ddd D MMM · HH:mm') }}
          </dd>
        </div>
      </dl>

      <p v-if="proposal.message" class="font-body text-sm leading-6 text-ink/60">
        « {{ proposal.message }} »
      </p>

      <p v-if="formError" class="font-body text-sm text-status-reserved" role="alert">
        {{ formError }}
      </p>
    </div>

    <p v-else-if="!isRequestOpen" class="font-body text-sm text-ink/50">
      Cette demande n’est plus ouverte.
    </p>

    <button
      v-else-if="!editing"
      type="button"
      class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-mono text-xs font-semibold text-surface transition hover:opacity-90"
      @click="startEditing"
    >
      <Send :size="14" aria-hidden="true" />
      Faire une proposition
    </button>

    <form v-else class="flex flex-col gap-4" novalidate @submit.prevent="submitProposal">
      <div class="rounded-lg bg-ground px-4 py-3">
        <p class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">
          Annonce utilisée
        </p>
        <p class="mt-1 font-body text-sm font-semibold text-ink">
          {{ request.matched_offer!.title }}
        </p>
      </div>

      <div>
        <label
          :for="`proposal-price-${request.id}`"
          class="mb-1.5 block font-body text-sm font-medium text-ink"
        >
          Prix proposé (DH)
        </label>
        <div class="relative">
          <Banknote
            :size="16"
            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink/30"
            aria-hidden="true"
          />
          <input
            :id="`proposal-price-${request.id}`"
            v-model="proposedPrice"
            type="number"
            min="0"
            step="1"
            required
            class="w-full rounded-lg border border-ink/15 bg-ground py-2 pl-9 pr-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
      </div>

      <BookingSlotPicker
        v-model="scheduledAt"
        :offer-id="request.matched_offer!.id"
        :min-scheduled-at="minScheduledAt"
        :max-scheduled-at="maxScheduledAt"
        :window-start-at="request.desired_start_at"
        :window-end-at="request.desired_end_at"
        :error-message="scheduledAtError"
        @touch="scheduledAtTouched = true"
      />

      <div>
        <label
          :for="`proposal-message-${request.id}`"
          class="mb-1.5 block font-body text-sm font-medium text-ink"
        >
          Message pour le client (optionnel)
        </label>
        <textarea
          :id="`proposal-message-${request.id}`"
          v-model="message"
          rows="2"
          maxlength="1000"
          class="w-full rounded-lg border border-ink/15 bg-ground px-3 py-2 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
        ></textarea>
      </div>

      <p v-if="formError" class="font-body text-sm text-status-reserved" role="alert">
        {{ formError }}
      </p>

      <div class="flex flex-wrap gap-2">
        <button
          type="submit"
          :disabled="saving"
          class="rounded-lg bg-primary px-4 py-2.5 font-mono text-xs font-semibold text-surface transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {{ saving ? 'Envoi…' : 'Envoyer la proposition' }}
        </button>

        <button
          v-if="proposal"
          type="button"
          :disabled="saving"
          class="rounded-lg border border-ink/15 px-4 py-2.5 font-mono text-xs text-ink/60 disabled:opacity-50"
          @click="cancelEditing"
        >
          Annuler
        </button>
      </div>
    </form>
  </div>
</template>
