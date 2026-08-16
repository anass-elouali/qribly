<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { ArrowUp, Sparkles, X } from 'lucide-vue-next'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const isOpen = ref(false)
const query = ref('')
const queryInput = ref<HTMLInputElement | null>(null)

const visibleOnCurrentPage = computed(() =>
  ['home', 'search', 'nearby'].includes(String(route.name)),
)

const suggestions = [
  'Un service près de moi',
  'Un produit d’occasion',
  'Une livraison locale',
]

async function openAssistant() {
  isOpen.value = true

  await nextTick()
  queryInput.value?.focus()
}

function closeAssistant() {
  isOpen.value = false
}

function chooseSuggestion(suggestion: string) {
  query.value = suggestion
  queryInput.value?.focus()
}

async function submitSearch() {
  const normalizedQuery = query.value.trim()

  if (!normalizedQuery) {
    queryInput.value?.focus()
    return
  }

  await router.push({
    name: 'search',
    query: { q: normalizedQuery, ai: '1' },
  })

  query.value = ''
  closeAssistant()
}
</script>

<template>
  <div v-if="visibleOnCurrentPage" class="fixed right-4 bottom-4 z-40 sm:right-6 sm:bottom-6">
    <section
      v-if="isOpen"
      role="dialog"
      aria-label="Qrib, assistant local"
      class="absolute right-0 bottom-16 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-ink/15 bg-surface shadow-xl sm:bottom-[4.5rem]"
      @keydown.esc="closeAssistant"
    >
      <header class="flex items-center justify-between gap-3 border-b border-ink/10 bg-primary px-4 py-3 text-surface">
        <div class="flex items-center gap-2.5">
          <span class="flex h-8 w-8 items-center justify-center rounded-[0.65rem_0.65rem_0.65rem_0.2rem] bg-accent text-primary">
            <Sparkles :size="17" aria-hidden="true" />
          </span>
          <div>
            <p class="font-display text-base font-semibold">Qrib</p>
            <p class="font-mono text-[0.62rem] tracking-wide text-surface/70 uppercase">Assistant local</p>
          </div>
        </div>

        <button
          type="button"
          class="rounded-md p-1.5 text-surface/80 transition-colors hover:bg-surface/10 hover:text-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-accent"
          aria-label="Fermer Qrib"
          @click="closeAssistant"
        >
          <X :size="18" aria-hidden="true" />
        </button>
      </header>

      <div class="p-4">
        <div class="flex items-start gap-2.5">
          <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.55rem_0.55rem_0.55rem_0.15rem] bg-primary text-accent">
            <Sparkles :size="14" aria-hidden="true" />
          </span>
          <p class="rounded-xl rounded-tl-sm bg-ground px-3 py-2.5 text-sm leading-5 text-ink">
            Bonjour ! Que cherches-tu près de toi ?
          </p>
        </div>

        <div class="mt-4 flex flex-wrap gap-2" aria-label="Suggestions de recherche">
          <button
            v-for="suggestion in suggestions"
            :key="suggestion"
            type="button"
            class="rounded-full border border-ink/15 px-2.5 py-1.5 text-left font-mono text-[0.65rem] text-ink/65 transition-colors hover:border-primary hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            @click="chooseSuggestion(suggestion)"
          >
            {{ suggestion }}
          </button>
        </div>

        <form class="mt-4 flex items-center gap-2" @submit.prevent="submitSearch">
          <label class="sr-only" for="qrib-query">Votre demande à Qrib</label>
          <input
            id="qrib-query"
            ref="queryInput"
            v-model="query"
            type="text"
            placeholder="Ex. un plombier disponible…"
            class="min-w-0 flex-1 rounded-lg border border-ink/15 bg-surface px-3 py-2.5 text-sm text-ink outline-none placeholder:text-ink/40 focus:border-primary"
          />
          <button
            type="submit"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary transition-opacity hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
            aria-label="Lancer la recherche"
          >
            <ArrowUp :size="18" aria-hidden="true" />
          </button>
        </form>

        <p class="mt-3 font-mono text-[0.62rem] leading-4 text-ink/45">
          Qrib vous aide à formuler votre recherche locale.
        </p>
      </div>
    </section>

    <button
      type="button"
      class="group flex h-12 items-center gap-2 rounded-full bg-primary py-1.5 pr-4 pl-1.5 font-body text-sm font-semibold text-surface shadow-lg transition-transform hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
      :aria-expanded="isOpen"
      aria-controls="qrib-query"
      @click="isOpen ? closeAssistant() : openAssistant()"
    >
      <span class="flex h-9 w-9 items-center justify-center rounded-[0.75rem_0.75rem_0.75rem_0.2rem] bg-accent text-primary transition-transform group-hover:rotate-6">
        <Sparkles :size="18" aria-hidden="true" />
      </span>
      {{ isOpen ? 'Réduire' : 'Parler à Qrib' }}
    </button>
  </div>
</template>
