<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/api'
import type { Review } from '@/types/review'
import { extractErrorMessage } from '@/utils/errors'

const props = defineProps<{
  reservationId: number
}>()

const emit = defineEmits<{
  submitted: [review: Review]
}>()

const rating = ref(5)
const comment = ref('')
const submitting = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  submitting.value = true

  try {
    const response = await api.post<{ data: Review }>('/reviews', {
      reservation_id: props.reservationId,
      rating: rating.value,
      comment: comment.value || undefined,
    })

    emit('submitted', response.data.data)
  } catch (err) {
    error.value = extractErrorMessage(err, "Impossible d'enregistrer ton avis.")
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <form class="flex flex-col gap-2" @submit.prevent="submit">
    <div class="flex items-center gap-2">
      <label for="rating" class="font-mono text-xs tracking-wide text-ink/60 uppercase">Note</label>
      <select
        id="rating"
        v-model.number="rating"
        class="rounded-md border border-ink/15 bg-ground px-2 py-1 font-body text-sm text-ink"
      >
        <option v-for="n in 5" :key="n" :value="n">{{ n }} / 5</option>
      </select>
    </div>

    <textarea
      v-model="comment"
      rows="2"
      placeholder="Un commentaire sur ce service ? (optionnel)"
      class="w-full rounded-lg border border-ink/15 bg-ground px-3 py-2 font-body text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
    ></textarea>

    <p v-if="error" class="font-body text-sm text-status-reserved">{{ error }}</p>

    <button
      type="submit"
      :disabled="submitting"
      class="self-start rounded-md bg-accent px-3 py-1.5 text-sm font-semibold text-ink transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
    >
      {{ submitting ? 'Envoi…' : "Envoyer l'avis" }}
    </button>
  </form>
</template>
