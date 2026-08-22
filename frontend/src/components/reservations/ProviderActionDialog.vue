<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { Ban, CheckCircle2, CircleCheckBig, X } from 'lucide-vue-next'

import type { ProviderReservationAction, Reservation } from '@/types/reservation'

const props = defineProps<{
  reservation: Reservation
  action: ProviderReservationAction
  busy?: boolean
  error?: string
}>()

const emit = defineEmits<{
  close: []
  confirm: []
}>()

const dialog = ref<HTMLElement | null>(null)
const cancelButton = ref<HTMLButtonElement | null>(null)
let previouslyFocusedElement: HTMLElement | null = null
let previousBodyOverflow = ''

const content = computed(() => {
  const customer = props.reservation.user?.name ?? 'ce client'
  const service = props.reservation.offer?.title ?? 'ce service'

  if (props.action === 'confirm') {
    return {
      eyebrow: 'Confirmation',
      title: 'Confirmer cette réservation ?',
      description: `Tu confirmes le rendez-vous de ${customer} pour « ${service} ». Le client sera immédiatement informé.`,
      confirmLabel: 'Oui, confirmer',
      busyLabel: 'Confirmation…',
      icon: CircleCheckBig,
      iconClass: 'bg-status-active/10 text-status-active',
      buttonClass: 'bg-status-active text-surface hover:opacity-90',
    }
  }

  if (props.action === 'complete') {
    return {
      eyebrow: 'Service réalisé',
      title: 'Marquer ce service comme terminé ?',
      description: `Tu confirmes que le service « ${service} » a été réalisé pour ${customer}. Le client pourra ensuite laisser un avis.`,
      confirmLabel: 'Oui, terminer',
      busyLabel: 'Finalisation…',
      icon: CheckCircle2,
      iconClass: 'bg-primary/10 text-primary',
      buttonClass: 'bg-primary text-surface hover:opacity-90',
    }
  }

  return {
    eyebrow: 'Annulation',
    title: 'Annuler cette réservation ?',
    description: `Le rendez-vous de ${customer} pour « ${service} » sera annulé et le client en sera informé.`,
    confirmLabel: 'Oui, annuler',
    busyLabel: 'Annulation…',
    icon: Ban,
    iconClass: 'bg-status-reserved/10 text-status-reserved',
    buttonClass: 'bg-status-reserved text-surface hover:opacity-90',
  }
})

function close() {
  if (!props.busy) {
    emit('close')
  }
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    event.preventDefault()
    close()
    return
  }

  if (event.key !== 'Tab' || !dialog.value) {
    return
  }

  const focusableElements = Array.from(
    dialog.value.querySelectorAll<HTMLElement>(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  )

  if (focusableElements.length === 0) {
    event.preventDefault()
    dialog.value.focus()
    return
  }

  const first = focusableElements[0]
  const last = focusableElements[focusableElements.length - 1]

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last?.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first?.focus()
  }
}

onMounted(async () => {
  previouslyFocusedElement = document.activeElement as HTMLElement | null
  previousBodyOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'

  await nextTick()
  cancelButton.value?.focus()
})

onBeforeUnmount(() => {
  document.body.style.overflow = previousBodyOverflow
  previouslyFocusedElement?.focus()
})
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-[60] flex items-center justify-center bg-ink/50 p-4 backdrop-blur-sm"
      @click.self="close"
    >
      <section
        ref="dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="provider-action-title"
        aria-describedby="provider-action-description"
        :aria-busy="busy"
        tabindex="-1"
        class="w-full max-w-md rounded-2xl border border-ink/10 bg-surface p-5 shadow-2xl sm:p-6"
        @keydown="handleKeydown"
      >
        <div class="flex items-start justify-between gap-4">
          <span
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
            :class="content.iconClass"
          >
            <component :is="content.icon" :size="21" aria-hidden="true" />
          </span>

          <button
            type="button"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-ink/45 transition hover:bg-ink/5 hover:text-ink disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="busy"
            aria-label="Fermer la confirmation"
            @click="close"
          >
            <X :size="18" aria-hidden="true" />
          </button>
        </div>

        <p class="mt-5 font-mono text-[0.65rem] tracking-[0.18em] text-primary uppercase">
          {{ content.eyebrow }}
        </p>

        <h2 id="provider-action-title" class="mt-1 font-display text-2xl font-bold text-ink">
          {{ content.title }}
        </h2>

        <p id="provider-action-description" class="mt-3 font-body text-sm leading-6 text-ink/60">
          {{ content.description }}
        </p>

        <p
          v-if="error"
          class="mt-4 rounded-lg border border-status-reserved/20 bg-status-reserved/5 px-3.5 py-3 font-body text-sm text-status-reserved"
          role="alert"
        >
          {{ error }}
        </p>

        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button
            ref="cancelButton"
            type="button"
            class="rounded-lg border border-ink/15 px-4 py-2.5 font-mono text-xs text-ink/70 transition hover:border-ink/30 hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="busy"
            @click="close"
          >
            Retour
          </button>

          <button
            type="button"
            class="rounded-lg px-4 py-2.5 font-mono text-xs font-semibold transition disabled:cursor-wait disabled:opacity-60"
            :class="content.buttonClass"
            :disabled="busy"
            @click="emit('confirm')"
          >
            {{ busy ? content.busyLabel : content.confirmLabel }}
          </button>
        </div>
      </section>
    </div>
  </Teleport>
</template>
