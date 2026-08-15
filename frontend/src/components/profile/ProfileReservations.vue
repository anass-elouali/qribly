<script setup lang="ts">
import ReservationRow from '@/components/reservations/ReservationRow.vue'
import ReviewForm from '@/components/reviews/ReviewForm.vue'
import StarRating from '@/components/reviews/StarRating.vue'

import type { Reservation } from '@/types/reservation'

defineProps<{
  reservations: Reservation[]
}>()

const emit = defineEmits<{
  cancel: [id: number]
  reviewSubmitted: [reservation: Reservation, review: NonNullable<Reservation['review']>]
}>()
</script>

<template>
  <section>
    <div class="mb-6">
      <p
        class="font-mono text-[0.65rem] tracking-[0.2em] text-primary uppercase"
      >
        Mes rendez-vous
      </p>

      <h2 class="mt-1 font-display text-2xl font-bold text-ink">
        Mes réservations
      </h2>

      <p class="mt-1 font-body text-sm text-ink/50">
        Suivez les services que vous avez réservés.
      </p>
    </div>

    <div
      v-if="reservations.length === 0"
      class="rounded-xl border border-dashed border-ink/15 px-6 py-16 text-center"
    >
      <p class="font-display text-lg font-bold text-ink">
        Aucune réservation
      </p>

      <p class="mx-auto mt-2 max-w-md font-body text-sm text-ink/50">
        Tu n'as encore réservé aucun service.
      </p>
    </div>

    <div
      v-else
      class="flex flex-col gap-3"
    >
      <ReservationRow
        v-for="reservation in reservations"
        :key="reservation.id"
        :reservation="reservation"
      >
        <template #actions>
          <button
            v-if="['pending', 'confirmed'].includes(reservation.status)"
            type="button"
            class="rounded-md border border-ink/15 px-3 py-1.5 text-sm text-status-reserved transition hover:border-status-reserved"
            @click="emit('cancel', reservation.id)"
          >
            Annuler
          </button>
        </template>

        <template
          v-if="reservation.status === 'completed'"
          #review
        >
          <div
            v-if="reservation.review"
            class="flex items-center gap-2"
          >
            <StarRating :rating="reservation.review.rating" />

            <p
              v-if="reservation.review.comment"
              class="font-body text-sm text-ink/70"
            >
              {{ reservation.review.comment }}
            </p>
          </div>

          <ReviewForm
            v-else
            :reservation-id="reservation.id"
            @submitted="
              emit(
                'reviewSubmitted',
                reservation,
                $event,
              )
            "
          />
        </template>
      </ReservationRow>
    </div>
  </section>
</template>