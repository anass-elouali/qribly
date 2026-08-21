<template>
  <div class="grid gap-8 md:grid-cols-2" role="status" aria-live="polite" aria-busy="true">
    <span class="sr-only">Chargement de l’annonce…</span>

    <div
      class="aspect-[4/3] animate-pulse rounded-xl bg-primary/10 motion-reduce:animate-none"
      aria-hidden="true"
    />

    <div class="space-y-4" aria-hidden="true">
      <div class="h-3 w-24 animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />
      <div class="h-8 w-4/5 animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />
      <div class="h-8 w-28 animate-pulse rounded bg-accent/40 motion-reduce:animate-none" />
      <div class="space-y-2 pt-2">
        <div class="h-4 w-full animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />
        <div class="h-4 w-11/12 animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />
        <div class="h-4 w-3/4 animate-pulse rounded bg-ink/10 motion-reduce:animate-none" />
      </div>
      <div class="mt-6 h-20 animate-pulse rounded-xl bg-ink/5 motion-reduce:animate-none" />
    </div>
  </div>
</template>
