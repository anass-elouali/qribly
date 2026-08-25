<script setup lang="ts">
import { computed } from 'vue'
import type { Category } from '@/types/offer'
import { statusLabel } from '@/utils/offer'

export interface OfferInfoData {
  title: string
  description: string
  categoryId: number | null
  type: 'product' | 'service'
  price: string
  isNegotiable: boolean
  status: 'active' | 'reserved' | 'sold' | 'inactive'
  serviceDurationMinutes: number
  atCustomerLocation: boolean
  atProviderLocation: boolean
}

const props = defineProps<{
  modelValue: OfferInfoData
  categories: Category[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: OfferInfoData]
  next: []
}>()

const form = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

function update<K extends keyof OfferInfoData>(key: K, value: OfferInfoData[K]) {
  emit('update:modelValue', {
    ...props.modelValue,
    [key]: value,
  })
}

const canContinue = computed(() => {
  return (
    props.modelValue.title.trim().length > 0 &&
    props.modelValue.description.trim().length > 0 &&
    props.modelValue.categoryId !== null &&
    props.modelValue.price !== '' &&
    (props.modelValue.type !== 'service' ||
      (props.modelValue.serviceDurationMinutes >= 15 &&
        (props.modelValue.atCustomerLocation || props.modelValue.atProviderLocation)))
  )
})

function next() {
  if (!canContinue.value) {
    return
  }

  emit('next')
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Header -->
    <div>
      <p class="mb-2 font-mono text-xs tracking-widest text-primary uppercase">Étape 1 sur 3</p>

      <h2 class="font-display text-2xl font-bold text-primary">Informations</h2>

      <p class="mt-1 font-body text-sm text-ink/60">Présente ton produit ou ton service.</p>
    </div>

    <!-- Title -->
    <div>
      <label for="title" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
        Titre
      </label>

      <input
        id="title"
        :value="form.title"
        type="text"
        required
        maxlength="255"
        placeholder="Ex. Livraison locale rapide"
        class="w-full rounded-xl border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
        @input="update('title', ($event.target as HTMLInputElement).value)"
      />
    </div>

    <!-- Category + type -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div>
        <label
          for="category"
          class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase"
        >
          Catégorie
        </label>

        <select
          id="category"
          :value="form.categoryId ?? ''"
          required
          class="w-full rounded-xl border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          @change="
            update(
              'categoryId',
              ($event.target as HTMLSelectElement).value
                ? Number(($event.target as HTMLSelectElement).value)
                : null,
            )
          "
        >
          <option value="" disabled>Choisir une catégorie</option>

          <option v-for="category in categories" :key="category.id" :value="category.id">
            {{ category.name }}
          </option>
        </select>
      </div>

      <div>
        <label for="type" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
          Type
        </label>

        <select
          id="type"
          :value="form.type"
          required
          class="w-full rounded-xl border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          @change="
            update('type', ($event.target as HTMLSelectElement).value as 'product' | 'service')
          "
        >
          <option value="product">Produit</option>

          <option value="service">Service</option>
        </select>
      </div>
    </div>

    <!-- Price -->
    <div>
      <label for="price" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
        Prix
      </label>

      <div class="relative">
        <input
          id="price"
          :value="form.price"
          type="number"
          step="0.01"
          min="0"
          required
          placeholder="0.00"
          class="w-full rounded-xl border border-ink/15 bg-ground px-4 py-3 pr-14 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          @input="update('price', ($event.target as HTMLInputElement).value)"
        />

        <span class="absolute top-1/2 right-4 -translate-y-1/2 font-mono text-xs text-ink/40">
          DH
        </span>
      </div>

      <label class="mt-3 flex cursor-pointer items-center gap-2">
        <input
          :checked="form.isNegotiable"
          type="checkbox"
          class="h-4 w-4 accent-primary"
          @change="update('isNegotiable', ($event.target as HTMLInputElement).checked)"
        />

        <span class="font-body text-sm text-ink"> Prix négociable </span>
      </label>
    </div>

    <div v-if="form.type === 'service'" class="space-y-5">
      <div>
        <label
          for="service-duration"
          class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase"
        >
          Durée du service
        </label>

        <select
          id="service-duration"
          :value="form.serviceDurationMinutes"
          required
          class="w-full rounded-xl border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          @change="
            update('serviceDurationMinutes', Number(($event.target as HTMLSelectElement).value))
          "
        >
          <option :value="30">30 minutes</option>
          <option :value="45">45 minutes</option>
          <option :value="60">1 heure</option>
          <option :value="90">1 h 30</option>
          <option :value="120">2 heures</option>
          <option :value="180">3 heures</option>
          <option :value="240">4 heures</option>
        </select>

        <p class="mt-1.5 font-body text-xs text-ink/45">
          Cette durée sert à calculer les créneaux libres et à éviter les rendez-vous qui se
          chevauchent.
        </p>
      </div>

      <fieldset>
        <legend class="mb-2 font-mono text-xs tracking-wide text-ink/60 uppercase">
          Où réalises-tu ce service ?
        </legend>

        <div class="grid gap-3 sm:grid-cols-2">
          <label
            class="flex cursor-pointer items-start gap-3 rounded-xl border border-ink/15 bg-ground p-4 transition hover:border-primary/40"
          >
            <input
              :checked="form.atCustomerLocation"
              type="checkbox"
              class="mt-0.5 h-4 w-4 shrink-0 accent-primary"
              @change="update('atCustomerLocation', ($event.target as HTMLInputElement).checked)"
            />

            <span>
              <span class="block font-body text-sm font-semibold text-ink">
                Je me déplace chez le client
              </span>
              <span class="mt-1 block font-body text-xs text-ink/50">
                Le service peut être réalisé à son domicile.
              </span>
            </span>
          </label>

          <label
            class="flex cursor-pointer items-start gap-3 rounded-xl border border-ink/15 bg-ground p-4 transition hover:border-primary/40"
          >
            <input
              :checked="form.atProviderLocation"
              type="checkbox"
              class="mt-0.5 h-4 w-4 shrink-0 accent-primary"
              @change="update('atProviderLocation', ($event.target as HTMLInputElement).checked)"
            />

            <span>
              <span class="block font-body text-sm font-semibold text-ink">
                Je reçois le client chez moi ou dans mon local
              </span>
              <span class="mt-1 block font-body text-xs text-ink/50">
                Le client se déplace pour recevoir le service.
              </span>
            </span>
          </label>
        </div>

        <p
          class="mt-2 font-body text-xs"
          :class="
            form.atCustomerLocation || form.atProviderLocation ? 'text-ink/45' : 'text-amber-700'
          "
        >
          Sélectionne au moins une option. Tu peux cocher les deux.
        </p>
      </fieldset>
    </div>

    <!-- Status -->
    <div>
      <label for="status" class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase">
        Statut
      </label>

      <select
        id="status"
        :value="form.status"
        required
        class="w-full rounded-xl border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
        @change="
          update('status', ($event.target as HTMLSelectElement).value as OfferInfoData['status'])
        "
      >
        <option v-for="(label, value) in statusLabel" :key="value" :value="value">
          {{ label }}
        </option>
      </select>
    </div>

    <!-- Description -->
    <div>
      <label
        for="description"
        class="mb-2 block font-mono text-xs tracking-wide text-ink/60 uppercase"
      >
        Description
      </label>

      <textarea
        id="description"
        :value="form.description"
        required
        rows="6"
        maxlength="5000"
        placeholder="Décris ton produit ou ton service..."
        class="w-full resize-none rounded-xl border border-ink/15 bg-ground px-4 py-3 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
        @input="update('description', ($event.target as HTMLTextAreaElement).value)"
      ></textarea>
    </div>

    <!-- Next -->
    <div class="flex justify-end border-t border-ink/10 pt-5">
      <button
        type="button"
        :disabled="!canContinue"
        class="rounded-xl bg-primary px-6 py-3 font-semibold text-surface transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
        @click="next"
      >
        Continuer
        <span class="ml-2">→</span>
      </button>
    </div>
  </div>
</template>
