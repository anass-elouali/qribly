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
  contactError?: string
}>()

const emit = defineEmits<{
  close: []
  message: [userId: number]
  confirm: [id: number]
  cancel: [id: number]
  complete: [id: number]
}>()

function close() {
  emit('close')
}
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4 backdrop-blur-sm"
    @click.self="close"
  >
    <div
      class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-ink/10 bg-surface shadow-xl"
    >
      <!-- Header -->
      <div
        class="flex items-start justify-between border-b border-ink/10 px-5 py-4"
      >
        <div>
          <p
            class="font-mono text-[0.6rem] tracking-[0.2em] text-primary uppercase"
          >
            Réservation #{{ reservation.id }}
          </p>

          <h2 class="mt-1 font-display text-xl font-bold text-ink">
            Détails de la réservation
          </h2>
        </div>

        <button
          type="button"
          class="flex h-8 w-8 items-center justify-center rounded-md text-xl text-ink/40 transition hover:bg-ink/5 hover:text-ink"
          aria-label="Fermer"
          @click="close"
        >
          ×
        </button>
      </div>

      <div class="space-y-6 p-5">
        <!-- Customer -->
        <section>
          <p
            class="mb-3 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
          >
            Client
          </p>

          <div class="flex items-center gap-3">
            <span
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 font-display font-bold text-primary"
            >
              {{ initials(reservation.user?.name ?? '?') }}
            </span>

            <div>
              <p class="font-body font-semibold text-ink">
                {{ reservation.user?.name ?? 'Utilisateur inconnu' }}
              </p>

                <p class="font-mono text-xs text-ink/50">
                    {{ reservation.user?.email ?? 'Email indisponible' }}
                </p>
            </div>
          </div>
        </section>

        <!-- Service -->
        <section class="border-t border-ink/10 pt-5">
          <p
            class="mb-3 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
          >
            Service
          </p>

          <div class="rounded-lg bg-ink/[0.03] p-4">
            <p class="font-body font-semibold text-ink">
              {{ reservation.offer?.title ?? 'Annonce supprimée' }}
            </p>

            <p
              v-if="reservation.offer?.price"
              class="mt-1 font-mono text-sm text-ink/50"
            >
              {{ reservation.offer.price }} DH
            </p>
          </div>
        </section>

        <!-- Appointment -->
        <section class="border-t border-ink/10 pt-5">
          <p
            class="mb-3 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
          >
            Rendez-vous
          </p>

          <div class="grid grid-cols-2 gap-3">
            <div class="rounded-lg bg-ink/[0.03] p-3">
              <p class="font-mono text-[0.6rem] text-ink/40 uppercase">
                Date
              </p>

              <p class="mt-1 font-body font-semibold text-ink">
                {{ dayjs(reservation.scheduled_at).format('DD MMM YYYY') }}
              </p>
            </div>

            <div class="rounded-lg bg-ink/[0.03] p-3">
              <p class="font-mono text-[0.6rem] text-ink/40 uppercase">
                Heure
              </p>

              <p class="mt-1 font-body font-semibold text-ink">
                {{ dayjs(reservation.scheduled_at).format('HH:mm') }}
              </p>
            </div>
          </div>
        </section>

        <!-- Status -->
        <section class="border-t border-ink/10 pt-5">
          <p
            class="mb-3 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
          >
            Statut
          </p>

          <span
            class="inline-flex rounded px-2.5 py-1 font-mono text-xs tracking-wide text-surface uppercase"
            :class="reservationStatusColor[reservation.status]"
          >
            {{ reservationStatusLabel[reservation.status] }}
          </span>
        </section>

        <!-- Notes -->
        <section
          v-if="reservation.notes"
          class="border-t border-ink/10 pt-5"
        >
          <p
            class="mb-3 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
          >
            Note du client
          </p>

          <div class="rounded-lg bg-ink/[0.03] p-4">
            <p class="font-body text-sm leading-relaxed text-ink/70">
              {{ reservation.notes }}
            </p>
          </div>
        </section>

        <!-- Metadata -->
        <section class="border-t border-ink/10 pt-5">
          <div class="flex items-center justify-between">
            <p class="font-mono text-[0.6rem] text-ink/40">
              Demande créée
            </p>

            <p class="font-mono text-[0.6rem] text-ink/50">
              {{ dayjs(reservation.created_at).format('DD/MM/YYYY HH:mm') }}
            </p>
          </div>
        </section>
      </div>

      <!-- Actions -->
      <div
        class="flex flex-wrap gap-2 border-t border-ink/10 bg-ink/[0.02] px-5 py-4"
      >
        <p
          v-if="contactError"
          class="w-full font-mono text-xs text-status-reserved"
        >
          {{ contactError }}
        </p>

        <button
          v-if="['pending', 'confirmed'].includes(reservation.status)"
          type="button"
          class="rounded-md border border-ink/15 px-3 py-2 font-mono text-xs text-ink/70 transition hover:border-primary hover:text-primary"
          @click="emit('message', reservation.user?.id ?? 0)"
        >
          Contacter le client
        </button>

        <div class="ml-auto flex gap-2">
          <button
            v-if="reservation.status === 'pending'"
            type="button"
            class="rounded-md border border-status-active px-3 py-2 font-mono text-xs text-status-active transition hover:bg-status-active/10"
            @click="emit('confirm', reservation.id)"
          >
            Confirmer
          </button>

          <button
            v-if="reservation.status === 'confirmed'"
            type="button"
            class="rounded-md border border-primary px-3 py-2 font-mono text-xs text-primary transition hover:bg-primary/10"
            @click="emit('complete', reservation.id)"
          >
            Terminer
          </button>

          <button
            v-if="['pending', 'confirmed'].includes(reservation.status)"
            type="button"
            class="rounded-md border border-ink/15 px-3 py-2 font-mono text-xs text-status-reserved transition hover:border-status-reserved"
            @click="emit('cancel', reservation.id)"
          >
            Annuler
          </button>
        </div>
      </div>
    </div>
  </div>
</template>