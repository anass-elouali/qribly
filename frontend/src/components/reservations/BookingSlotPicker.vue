<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import dayjs from 'dayjs'
import { CalendarDays, Clock3, RotateCcw } from 'lucide-vue-next'

import api from '@/services/api'
import type { OfferAvailabilityResponse } from '@/types/reservation'
import { extractErrorMessage } from '@/utils/errors'

const props = defineProps<{
  offerId: number
  modelValue: string
  minScheduledAt: string
  errorMessage?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  touch: []
}>()

const availability = ref<OfferAvailabilityResponse | null>(null)
const selectedDate = ref('')
const loading = ref(true)
const loadError = ref('')
let requestSequence = 0

const bookableDays = computed(() =>
  (availability.value?.days ?? []).filter((day) => day.slots.length > 0),
)

const selectedDay = computed(
  () => bookableDays.value.find((day) => day.date === selectedDate.value) ?? null,
)

const durationLabel = computed(() => {
  const duration = availability.value?.duration_minutes ?? 60

  if (duration < 60) {
    return `${duration} min`
  }

  const hours = Math.floor(duration / 60)
  const remainingMinutes = duration % 60

  return remainingMinutes ? `${hours} h ${remainingMinutes}` : `${hours} h`
})

function formatDate(date: string) {
  const formatted = dayjs(date).format('ddd D MMM')
  return formatted.charAt(0).toUpperCase() + formatted.slice(1)
}

function selectSlot(startsAt: string) {
  emit('update:modelValue', startsAt)
  emit('touch')
}

function selectDate(date: string) {
  if (selectedDate.value !== date) {
    emit('update:modelValue', '')
  }

  selectedDate.value = date
}

async function loadAvailability() {
  const sequence = ++requestSequence
  loading.value = true
  loadError.value = ''

  try {
    const response = await api.get<OfferAvailabilityResponse>(
      `/offers/${props.offerId}/availability`,
      {
        params: {
          from: dayjs().format('YYYY-MM-DD'),
          days: 14,
        },
      },
    )

    if (sequence !== requestSequence) {
      return
    }

    availability.value = response.data

    const firstDate = response.data.days.find((day) => day.slots.length > 0)?.date ?? ''
    const selectedStillExists = response.data.days.some(
      (day) =>
        day.date === selectedDate.value &&
        day.slots.some((slot) => slot.starts_at === props.modelValue),
    )

    selectedDate.value = selectedStillExists ? selectedDate.value : firstDate

    if (response.data.configured && !selectedStillExists && props.modelValue) {
      emit('update:modelValue', '')
    }
  } catch (exception) {
    if (sequence === requestSequence) {
      loadError.value = extractErrorMessage(
        exception,
        'Impossible de charger les créneaux disponibles.',
      )
    }
  } finally {
    if (sequence === requestSequence) {
      loading.value = false
    }
  }
}

watch(() => props.offerId, loadAvailability, { immediate: true })

defineExpose({ refresh: loadAvailability })
</script>

<template>
  <div>
    <div class="mb-2 flex items-center justify-between gap-3">
      <label class="font-body text-sm font-medium text-ink">Date et heure</label>
      <span
        v-if="availability?.configured"
        class="inline-flex items-center gap-1 font-mono text-[0.65rem] text-ink/45"
      >
        <Clock3 :size="13" aria-hidden="true" />
        {{ durationLabel }}
      </span>
    </div>

    <div
      v-if="loading"
      class="rounded-lg border border-ink/10 bg-ground px-4 py-5 text-center font-body text-sm text-ink/50"
      role="status"
    >
      Recherche des créneaux libres…
    </div>

    <div
      v-else-if="loadError"
      class="rounded-lg border border-status-reserved/20 bg-status-reserved/5 px-4 py-4"
    >
      <p class="font-body text-sm text-status-reserved" role="alert">{{ loadError }}</p>
      <button
        type="button"
        class="mt-2 inline-flex items-center gap-2 font-mono text-xs text-primary"
        @click="loadAvailability"
      >
        <RotateCcw :size="14" aria-hidden="true" />
        Réessayer
      </button>
    </div>

    <template v-else-if="availability?.configured">
      <div
        v-if="bookableDays.length"
        class="booking-days -mx-1 flex gap-2 overflow-x-auto px-1 pb-2"
        aria-label="Dates disponibles"
      >
        <button
          v-for="day in bookableDays"
          :key="day.date"
          type="button"
          class="shrink-0 rounded-lg border px-3 py-2 text-left transition"
          :class="
            selectedDate === day.date
              ? 'border-primary bg-primary text-surface'
              : 'border-ink/10 bg-ground text-ink hover:border-primary/40'
          "
          :aria-pressed="selectedDate === day.date"
          @click="selectDate(day.date)"
        >
          <span class="block font-mono text-[0.65rem]">{{ formatDate(day.date) }}</span>
          <span
            class="mt-0.5 block font-body text-[0.65rem]"
            :class="selectedDate === day.date ? 'text-surface/70' : 'text-ink/40'"
          >
            {{ day.slots.length }} créneau<span v-if="day.slots.length > 1">x</span>
          </span>
        </button>
      </div>

      <div v-if="selectedDay" class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
        <button
          v-for="slot in selectedDay.slots"
          :key="slot.starts_at"
          type="button"
          class="rounded-lg border px-2 py-2.5 font-mono text-xs font-semibold transition"
          :class="
            modelValue === slot.starts_at
              ? 'border-accent bg-accent text-ink'
              : 'border-ink/10 bg-ground text-ink/70 hover:border-primary hover:text-primary'
          "
          :aria-pressed="modelValue === slot.starts_at"
          @click="selectSlot(slot.starts_at)"
        >
          {{ slot.time }}
        </button>
      </div>

      <div
        v-else
        class="rounded-lg border border-dashed border-ink/15 bg-ground px-4 py-5 text-center"
      >
        <CalendarDays :size="22" class="mx-auto text-ink/30" aria-hidden="true" />
        <p class="mt-2 font-body text-sm font-semibold text-ink">Aucun créneau prochainement</p>
        <p class="mt-1 font-body text-xs leading-5 text-ink/50">
          Le prestataire n’a pas de créneau libre dans les 14 prochains jours.
        </p>
      </div>

      <p class="mt-2 font-body text-xs text-ink/45">Horaires affichés selon l’heure du Maroc.</p>
    </template>

    <div v-else>
      <p class="mb-2 rounded-md bg-accent/10 px-3 py-2 font-body text-xs leading-5 text-ink/60">
        Le prestataire n’a pas encore publié ses horaires. Tu peux lui proposer une date.
      </p>
      <input
        :id="`scheduled-${offerId}`"
        :value="modelValue"
        type="datetime-local"
        :min="minScheduledAt"
        class="w-full rounded-lg border border-ink/15 bg-ground px-3 py-2 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
        :class="errorMessage ? '!border-status-reserved' : ''"
        :aria-invalid="Boolean(errorMessage)"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        @blur="emit('touch')"
      />
      <p class="mt-1 font-body text-xs text-ink/45">Heure locale de ton appareil.</p>
    </div>

    <p v-if="errorMessage" class="mt-1 font-body text-xs text-status-reserved" role="alert">
      {{ errorMessage }}
    </p>
  </div>
</template>

<style scoped>
.booking-days {
  scrollbar-width: none;
}

.booking-days::-webkit-scrollbar {
  display: none;
}
</style>
