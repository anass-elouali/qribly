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
  <div
    class="flex flex-col gap-2 rounded-md border border-ink/10 bg-surface p-4 sm:flex-row sm:items-center sm:justify-between"
  >
    <div>
      <p class="font-body font-semibold text-ink">
        {{ reservation.offer?.title ?? 'Annonce supprimée' }}
      </p>
      <p class="font-mono text-xs text-ink/50">
        {{ dayjs(reservation.scheduled_at).format('DD/MM/YYYY HH:mm') }}
        <span v-if="personLabel"> · {{ personLabel }}</span>
      </p>
      <p v-if="reservation.notes" class="mt-1 font-body text-sm text-ink/70">{{ reservation.notes }}</p>
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
</template>
