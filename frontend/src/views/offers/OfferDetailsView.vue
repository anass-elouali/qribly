<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import dayjs from 'dayjs'
import api from '@/services/api'
import type { Offer } from '@/types/offer'
import { statusLabel, statusColor, formatPrice } from '@/utils/offer'

const route = useRoute()
const offer = ref<Offer | null>(null)
const loading = ref(true)
const error = ref('')

function initials(name: string) {
  return name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

async function loadOffer() {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get<{ data: Offer }>(`/offers/${route.params.id}`)
    offer.value = response.data.data
  } catch {
    error.value = "Cette annonce n'existe pas ou plus."
  } finally {
    loading.value = false
  }
}

onMounted(loadOffer)
watch(() => route.params.id, loadOffer)
</script>

<template>
  <div class="mx-auto max-w-4xl px-6 py-8">
    <p v-if="loading" class="font-mono text-sm text-ink/50">Chargement…</p>

    <p v-else-if="error" class="rounded-md bg-status-reserved/10 px-4 py-3 text-sm text-status-reserved">
      {{ error }}
    </p>

    <div v-else-if="offer" class="grid gap-8 md:grid-cols-2">
      <div
        class="flex aspect-[4/3] items-center justify-center rounded-md bg-primary font-mono text-xs tracking-wide text-surface/70 uppercase"
      >
        Photo
      </div>

      <div class="flex flex-col gap-4">
        <p v-if="offer.category" class="font-mono text-xs tracking-wide text-ink/50 uppercase">
          {{ offer.category.name }}
        </p>

        <h1 class="font-display text-2xl font-bold text-ink">{{ offer.title }}</h1>

        <div class="flex flex-wrap items-center gap-3">
          <span class="-rotate-2 rounded bg-accent px-3 py-1 font-mono text-lg font-bold text-ink">
            {{ formatPrice(offer.price) }} DH
          </span>
          <span
            class="rounded px-2 py-0.5 font-mono text-xs tracking-wide text-surface uppercase"
            :class="statusColor[offer.status]"
          >
            {{ statusLabel[offer.status] }}
          </span>
          <span v-if="offer.is_negotiable" class="font-mono text-xs text-ink/50">Prix négociable</span>
        </div>

        <p class="font-body text-ink/80">{{ offer.description }}</p>

        <p class="font-mono text-xs text-ink/40">Publié {{ dayjs(offer.created_at).fromNow() }}</p>

        <div v-if="offer.owner" class="flex items-center gap-3 rounded-md border border-ink/10 bg-surface p-3">
          <span
            class="flex h-10 w-10 items-center justify-center rounded-full bg-primary font-mono text-xs font-bold text-surface"
          >
            {{ initials(offer.owner.name) }}
          </span>
          <p class="font-body font-semibold text-ink">{{ offer.owner.name }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
