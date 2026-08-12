<script setup lang="ts">
import { Star } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    rating: number
    size?: number
  }>(),
  {
    size: 14,
  },
)
</script>

<template>
  <span class="inline-flex items-center gap-0.5 text-accent">
    <Star
      v-for="n in 5"
      :key="n"
      :size="size"
      :fill="n <= Math.round(rating) ? 'currentColor' : 'none'"
      :stroke-width="1.5"
    />
  </span>
</template>
