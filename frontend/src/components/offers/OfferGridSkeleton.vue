<script setup lang="ts">
withDefaults(
  defineProps<{
    count?: number
  }>(),
  {
    count: 6,
  },
)
</script>

<template>
  <div
    class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
    role="status"
    aria-live="polite"
    aria-busy="true"
  >
    <span class="sr-only">Chargement des annonces…</span>

    <div
      v-for="index in count"
      :key="index"
      class="overflow-hidden rounded-xl border border-ink/10 bg-surface"
      aria-hidden="true"
    >
      <div class="aspect-[4/3] animate-pulse bg-primary/10 motion-reduce:animate-none" />

      <div class="space-y-3 p-4">
        <div class="h-3 w-1/3 animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />
        <div class="h-5 w-4/5 animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />

        <div class="flex items-center justify-between pt-2">
          <div class="h-4 w-16 animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />
          <div class="h-7 w-20 animate-pulse rounded bg-accent/40 motion-reduce:animate-none" />
        </div>
      </div>
    </div>
  </div>
</template>
