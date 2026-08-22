<script setup lang="ts">
import { ref, watch } from 'vue'
import ReservationRow from '@/components/reservations/ReservationRow.vue'
import ReviewForm from '@/components/reviews/ReviewForm.vue'
import StarRating from '@/components/reviews/StarRating.vue'

import type { Reservation } from '@/types/reservation'

const props = withDefaults(
  defineProps<{
    reservations: Reservation[]
    cancellingId?: number | null
    actionError?: string
    actionSuccess?: string
  }>(),
  {
    cancellingId: null,
    actionError: '',
    actionSuccess: '',
  },
)

const emit = defineEmits<{
  cancel: [id: number]
  reviewSubmitted: [reservation: Reservation, review: NonNullable<Reservation['review']>]
}>()

const confirmingCancellationId = ref<number | null>(null)

function askToCancel(id: number) {
  confirmingCancellationId.value = id
}

function keepReservation() {
  confirmingCancellationId.value = null
}

watch(
  () => props.cancellingId,
  (currentId, previousId) => {
    if (previousId !== null && currentId === null && !props.actionError) {
      confirmingCancellationId.value = null
    }
  },
)
</script>

<template>
  <section>
    <div class="mb-6">
      <p class="font-mono text-[0.65rem] tracking-[0.2em] text-primary uppercase">
        Mes rendez-vous
      </p>

      <h2 class="mt-1 font-display text-2xl font-bold text-ink">Mes réservations</h2>

      <p class="mt-1 font-body text-sm text-ink/50">Suivez les services que vous avez réservés.</p>
    </div>

    <p
      v-if="actionSuccess"
      class="mb-4 rounded-lg border border-status-active/20 bg-status-active/10 px-4 py-3 font-body text-sm text-status-active"
      role="status"
      aria-live="polite"
    >
      {{ actionSuccess }}
    </p>

    <p
      v-if="actionError"
      class="mb-4 rounded-lg border border-status-reserved/20 bg-status-reserved/10 px-4 py-3 font-body text-sm text-status-reserved"
      role="alert"
    >
      {{ actionError }}
    </p>

    <div
      v-if="reservations.length === 0"
      class="rounded-xl border border-dashed border-ink/15 px-6 py-16 text-center"
    >
      <p class="font-display text-lg font-bold text-ink">Aucune réservation</p>

      <p class="mx-auto mt-2 max-w-md font-body text-sm text-ink/50">
        Tu n'as encore réservé aucun service.
      </p>

      <RouterLink
        :to="{ name: 'home' }"
        class="mt-5 inline-flex rounded-lg bg-accent px-4 py-2.5 font-body text-sm font-semibold text-ink transition hover:opacity-90"
      >
        Découvrir les services
      </RouterLink>
    </div>

    <div v-else class="flex flex-col gap-3">
      <ReservationRow
        v-for="reservation in reservations"
        :key="reservation.id"
        :reservation="reservation"
      >
        <template #actions>
          <button
            v-if="
              ['pending', 'confirmed'].includes(reservation.status) &&
              confirmingCancellationId !== reservation.id
            "
            type="button"
            :disabled="cancellingId !== null"
            class="rounded-md border border-ink/15 px-3 py-1.5 text-sm text-status-reserved transition hover:border-status-reserved disabled:cursor-not-allowed disabled:opacity-40"
            @click="askToCancel(reservation.id)"
          >
            Annuler
          </button>
        </template>

        <template
          v-if="
            ['pending', 'confirmed'].includes(reservation.status) &&
            confirmingCancellationId === reservation.id
          "
          #details
        >
          <div
            class="rounded-lg border border-status-reserved/20 bg-status-reserved/5 p-3 sm:flex sm:items-center sm:justify-between sm:gap-4"
            role="alertdialog"
            :aria-labelledby="`cancel-title-${reservation.id}`"
            :aria-describedby="`cancel-description-${reservation.id}`"
          >
            <div>
              <p
                :id="`cancel-title-${reservation.id}`"
                class="font-body text-sm font-semibold text-ink"
              >
                Annuler cette réservation ?
              </p>
              <p
                :id="`cancel-description-${reservation.id}`"
                class="mt-0.5 font-body text-xs leading-5 text-ink/55"
              >
                Le prestataire sera informé et ce créneau redeviendra disponible.
              </p>
            </div>

            <div class="mt-3 flex shrink-0 gap-2 sm:mt-0">
              <button
                type="button"
                :disabled="cancellingId === reservation.id"
                class="rounded-md border border-ink/15 px-3 py-2 font-body text-xs font-semibold text-ink transition hover:border-ink/40 disabled:cursor-not-allowed disabled:opacity-40"
                @click="keepReservation"
              >
                Garder
              </button>
              <button
                type="button"
                :disabled="cancellingId === reservation.id"
                class="rounded-md bg-status-reserved px-3 py-2 font-body text-xs font-semibold text-surface transition hover:opacity-90 disabled:cursor-wait disabled:opacity-60"
                @click="emit('cancel', reservation.id)"
              >
                {{ cancellingId === reservation.id ? 'Annulation…' : 'Oui, annuler' }}
              </button>
            </div>
          </div>
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
            @submitted="emit('reviewSubmitted', reservation, $event)"
          />
        </template>
      </ReservationRow>
    </div>
  </section>
</template>
