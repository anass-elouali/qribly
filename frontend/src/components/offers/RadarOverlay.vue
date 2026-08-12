<script setup lang="ts"></script>

<template>
  <div class="pointer-events-none absolute inset-0 z-[500] overflow-hidden rounded-md">
    <div class="radar-sweep absolute inset-0"></div>
    <div
      class="absolute top-1/2 left-1/2 aspect-square h-[30%] -translate-x-1/2 -translate-y-1/2 rounded-full border border-dashed border-primary/20"
    ></div>
    <div
      class="absolute top-1/2 left-1/2 aspect-square h-[55%] -translate-x-1/2 -translate-y-1/2 rounded-full border border-dashed border-primary/20"
    ></div>
    <div
      class="absolute top-1/2 left-1/2 aspect-square h-[78%] -translate-x-1/2 -translate-y-1/2 rounded-full border border-dashed border-primary/20"
    ></div>
  </div>
</template>

<style scoped>
.radar-sweep {
  background: conic-gradient(from 0deg, var(--color-accent) 0deg, transparent 26deg, transparent 360deg);
  opacity: 0.16;
}

@media (prefers-reduced-motion: no-preference) {
  .radar-sweep {
    animation: radar-spin 7s linear infinite;
  }
}

@keyframes radar-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
