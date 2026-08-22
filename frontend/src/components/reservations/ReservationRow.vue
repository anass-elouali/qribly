<script setup lang="ts">
import dayjs from 'dayjs'
import type { Reservation } from '@/types/reservation'
import { reservationStatusLabel, reservationStatusColor } from '@/utils/reservation'

defineProps<{
  reservation: Reservation
  personLabel?: string | null
}>()
</script>

<template>
  <div class="flex flex-col gap-2 rounded-md border border-ink/10 bg-surface p-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div class="min-w-0">
        <RouterLink
          v-if="reservation.offer"
          :to="{ name: 'offer-details', params: { id: reservation.offer.id } }"
          class="font-body font-semibold text-ink transition hover:text-primary hover:underline"
        >
          {{ reservation.offer.title }}
        </RouterLink>
        <p v-else class="font-body font-semibold text-ink">Annonce supprimée</p>
        <p class="mt-0.5 font-mono text-xs text-ink/50">
          {{ dayjs(reservation.scheduled_at).format('ddd D MMM YYYY [à] HH:mm') }}
          <span v-if="personLabel"> · {{ personLabel }}</span>
        </p>
        <p v-if="reservation.offer?.price" class="mt-1 font-mono text-xs font-semibold text-accent">
          {{ reservation.offer.price }} DH
        </p>
        <p v-if="reservation.notes" class="mt-2 font-body text-sm leading-5 text-ink/70">
          {{ reservation.notes }}
        </p>
      </div>

      <div class="flex items-center gap-3">
        <span
          class="rounded px-2 py-0.5 font-mono text-[0.65rem] tracking-wide text-surface uppercase"
          :class="reservationStatusColor[reservation.status]"
        >
          {{ reservationStatusLabel[reservation.status] }}
        </span>
        <div class="flex gap-2">
          <slot name="actions" />
        </div>
      </div>
    </div>

    <div v-if="$slots.details">
      <slot name="details" />
    </div>

    <div v-if="$slots.review" class="border-t border-ink/10 pt-3">
      <slot name="review" />
    </div>
  </div>
</template>
