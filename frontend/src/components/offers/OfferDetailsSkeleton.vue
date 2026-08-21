<template>
  <div role="status" aria-live="polite" aria-busy="true">
    <span class="sr-only">Chargement de l’annonce…</span>

    <div
      class="h-80 animate-pulse rounded-lg bg-primary/10 motion-reduce:animate-none sm:h-[26rem]"
      aria-hidden="true"
    />

    <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_23rem]" aria-hidden="true">
      <div class="space-y-4">
        <div class="h-3 w-40 animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />
        <div class="h-10 w-4/5 animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />
        <div class="flex gap-2">
          <div
            v-for="index in 4"
            :key="index"
            class="h-7 w-24 animate-pulse rounded bg-ink/10 motion-reduce:animate-none"
          />
        </div>
        <div class="h-8 w-28 animate-pulse rounded bg-accent/40 motion-reduce:animate-none" />
        <div class="space-y-2 pt-2">
          <div class="h-4 w-full animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />
          <div class="h-4 w-11/12 animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />
          <div class="h-4 w-3/4 animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />
        </div>
        <div class="h-20 animate-pulse rounded-xl bg-ink/5 motion-reduce:animate-none" />
        <div class="h-52 animate-pulse rounded-xl bg-ink/5 motion-reduce:animate-none" />
      </div>

      <div class="h-96 animate-pulse rounded-xl bg-surface motion-reduce:animate-none" />
    </div>
  </div>
</template>
