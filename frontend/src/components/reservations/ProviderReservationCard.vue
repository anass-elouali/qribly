<script setup lang="ts">
import dayjs from 'dayjs'
import type { Reservation } from '@/types/reservation'
import { reservationStatusLabel, reservationStatusColor } from '@/utils/reservation'
import { initials } from '@/utils/user'

defineProps<{
  reservation: Reservation
}>()

defineEmits<{
  view: [reservation: Reservation]
  confirm: [id: number]
  cancel: [id: number]
  complete: [id: number]
}>()
</script>

<template>
  <article class="rounded-lg border border-ink/10 bg-surface p-5 transition hover:border-ink/20">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div class="flex min-w-0 items-center gap-3">
        <span
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 font-display text-sm font-bold text-primary"
        >
          {{ initials(reservation.user?.name ?? '?') }}
        </span>

        <div class="min-w-0">
          <p class="truncate font-body font-semibold text-ink">
            {{ reservation.user?.name ?? 'Utilisateur inconnu' }}
          </p>

          <p class="truncate font-mono text-xs text-ink/50">
            {{ reservation.user?.email ?? 'Email indisponible' }}
          </p>
        </div>
      </div>

      <span
        class="shrink-0 rounded px-2 py-1 font-mono text-[0.65rem] tracking-wide text-surface uppercase"
        :class="reservationStatusColor[reservation.status]"
      >
        {{ reservationStatusLabel[reservation.status] }}
      </span>
    </div>

    <!-- Reservation info -->
    <div class="mt-5 grid gap-4 border-t border-ink/10 pt-4 sm:grid-cols-2">
      <div>
        <p class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">
          Service
        </p>

        <p class="mt-1 font-body font-semibold text-ink">
          {{ reservation.offer?.title ?? 'Annonce supprimée' }}
        </p>

        <p class="mt-0.5 font-mono text-xs text-ink/50">
          {{ reservation.offer?.price }} DH
        </p>
      </div>

      <div>
        <p class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">
          Rendez-vous
        </p>

        <p class="mt-1 font-body font-semibold text-ink">
          {{ dayjs(reservation.scheduled_at).format('DD MMM YYYY') }}
        </p>

        <p class="mt-0.5 font-mono text-xs text-ink/50">
          {{ dayjs(reservation.scheduled_at).format('HH:mm') }}
        </p>
      </div>
    </div>

    <!-- Notes -->
    <div
      v-if="reservation.notes"
      class="mt-4 rounded-md bg-ink/[0.03] px-3 py-2.5"
    >
      <p class="font-body text-sm leading-relaxed text-ink/70">
        "{{ reservation.notes }}"
      </p>
    </div>

    <!-- Actions -->
    <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-ink/10 pt-4">
      <button
        type="button"
        class="font-mono text-xs tracking-wide text-primary uppercase transition hover:opacity-70"
        @click="$emit('view', reservation)"
      >
        Voir les détails →
      </button>

      <div class="flex gap-2">
        <button
          v-if="reservation.status === 'pending'"
          type="button"
          class="rounded-md border border-status-active px-3 py-1.5 text-sm text-status-active transition hover:bg-status-active/10"
          @click="$emit('confirm', reservation.id)"
        >
          Confirmer
        </button>

        <button
          v-if="reservation.status === 'confirmed'"
          type="button"
          class="rounded-md border border-primary px-3 py-1.5 text-sm text-primary transition hover:bg-primary/10"
          @click="$emit('complete', reservation.id)"
        >
          Terminer
        </button>

        <button
          v-if="['pending', 'confirmed'].includes(reservation.status)"
          type="button"
          class="rounded-md border border-ink/15 px-3 py-1.5 text-sm text-status-reserved transition hover:border-status-reserved"
          @click="$emit('cancel', reservation.id)"
        >
          Annuler
        </button>
      </div>
    </div>
  </article>
</template>