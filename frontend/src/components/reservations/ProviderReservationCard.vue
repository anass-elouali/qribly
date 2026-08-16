<script setup lang="ts">
import dayjs from 'dayjs'
import type { Reservation } from '@/types/reservation'
import {
  reservationStatusLabel,
  reservationStatusColor,
} from '@/utils/reservation'
import { initials } from '@/utils/user'

defineProps<{
  reservation: Reservation
}>()

defineEmits<{
  view: [reservation: Reservation]
  message: [userId: number]
  confirm: [id: number]
  cancel: [id: number]
  complete: [id: number]
}>()
</script>

<template>
  <article
    class="rounded-xl border border-ink/10 bg-surface p-5 transition hover:border-ink/20"
  >
    <!-- ========================================================= -->
    <!-- CUSTOMER HEADER                                           -->
    <!-- ========================================================= -->

    <div class="flex items-start justify-between gap-4">
      <div class="flex min-w-0 items-center gap-3">
        <span
          class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/10 font-display text-sm font-bold text-primary"
        >
          {{ initials(reservation.user?.name ?? '?') }}
        </span>

        <div class="min-w-0">
          <p class="truncate font-body font-semibold text-ink">
            {{ reservation.user?.name ?? 'Utilisateur inconnu' }}
          </p>

          <p class="mt-0.5 font-mono text-[0.65rem] text-ink/40">
            Réservation #{{ reservation.id }}
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

    <!-- ========================================================= -->
    <!-- RESERVATION INFORMATION                                    -->
    <!-- ========================================================= -->

    <div
      class="mt-5 grid gap-4 border-t border-ink/10 pt-4 sm:grid-cols-2"
    >
      <!-- Service -->
      <div>
        <p
          class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
          Service
        </p>

        <p
          class="mt-1 truncate font-body font-semibold text-ink"
        >
          {{ reservation.offer?.title ?? 'Annonce supprimée' }}
        </p>

        <p
          v-if="reservation.offer?.price"
          class="mt-0.5 font-mono text-xs text-ink/50"
        >
          {{ reservation.offer.price }} DH
        </p>
      </div>

      <!-- Appointment -->
      <div>
        <p
          class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
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

    <!-- ========================================================= -->
    <!-- NOTES                                                      -->
    <!-- ========================================================= -->

    <div
      v-if="reservation.notes"
      class="mt-4 rounded-lg bg-ink/[0.03] px-3.5 py-3"
    >
      <p
        class="mb-1 font-mono text-[0.6rem] tracking-wide text-ink/40 uppercase"
      >
        Note du client
      </p>

      <p class="font-body text-sm leading-relaxed text-ink/70">
        {{ reservation.notes }}
      </p>
    </div>

    <!-- ========================================================= -->
    <!-- META                                                       -->
    <!-- ========================================================= -->

    <div
      class="mt-4 flex items-center justify-between border-t border-ink/10 pt-3"
    >
      <p class="font-mono text-[0.6rem] text-ink/35">
        Demandée
        {{ dayjs(reservation.created_at).format('DD/MM/YYYY HH:mm') }}
      </p>

      <button
        type="button"
        class="font-mono text-xs tracking-wide text-primary uppercase transition hover:opacity-70"
        @click="$emit('view', reservation)"
      >
        Voir les détails →
      </button>
    </div>

    <!-- ========================================================= -->
    <!-- ACTIONS                                                    -->
    <!-- ========================================================= -->

    <div
      v-if="['pending', 'confirmed'].includes(reservation.status)"
      class="mt-4 flex flex-col gap-2 border-t border-ink/10 pt-4 sm:flex-row sm:justify-end"
    >
      <!-- Message -->
      <button
        type="button"
        class="rounded-md border border-ink/15 px-3 py-1.5 font-mono text-xs text-ink/70 transition hover:border-primary hover:text-primary"
        @click="$emit('message', reservation.user?.id ?? 0)"
      >
        Contacter le client
      </button>

      <!-- Confirm -->
      <button
        v-if="reservation.status === 'pending'"
        type="button"
        class="rounded-md border border-status-active px-3 py-1.5 font-mono text-xs text-status-active transition hover:bg-status-active/10"
        @click="$emit('confirm', reservation.id)"
      >
        Confirmer
      </button>

      <!-- Complete -->
      <button
        v-if="reservation.status === 'confirmed'"
        type="button"
        class="rounded-md border border-primary px-3 py-1.5 font-mono text-xs text-primary transition hover:bg-primary/10"
        @click="$emit('complete', reservation.id)"
      >
        Marquer comme terminée
      </button>

      <!-- Cancel -->
      <button
        type="button"
        class="rounded-md border border-ink/15 px-3 py-1.5 font-mono text-xs text-status-reserved transition hover:border-status-reserved"
        @click="$emit('cancel', reservation.id)"
      >
        Annuler
      </button>
    </div>

    <!-- Completed -->
    <div
      v-else-if="reservation.status === 'completed'"
      class="mt-4 border-t border-ink/10 pt-4"
    >
      <div
        class="flex items-center justify-between rounded-md bg-status-active/5 px-3 py-2"
      >
        <p class="font-mono text-xs text-status-active">
          Service terminé
        </p>

        <button
          type="button"
          class="font-mono text-xs text-primary hover:opacity-70"
          @click="$emit('view', reservation)"
        >
          Voir les détails →
        </button>
      </div>
    </div>

    <!-- Cancelled -->
    <div
      v-else-if="reservation.status === 'cancelled'"
      class="mt-4 border-t border-ink/10 pt-4"
    >
      <p
        class="rounded-md bg-status-reserved/5 px-3 py-2 font-mono text-xs text-status-reserved"
      >
        Cette réservation a été annulée.
      </p>
    </div>
  </article>
</template>