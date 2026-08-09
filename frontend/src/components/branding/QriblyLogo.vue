<script setup lang="ts">
import { motion } from 'motion-v'
</script>

<template>
  <div class="flex items-center font-display text-xl font-bold text-primary">
    <!-- QRIB: the magnet, gets a tiny recoil when LY lands -->
    <motion.span
      class="inline-block"
      :animate="{ x: [0, 0, -2, 1, 0] }"
      :transition="{
        duration: 5,
        repeat: Infinity,
        repeatDelay: 30,
        times: [0, 0.9, 0.94, 0.97, 1],
        ease: 'easeOut',
      }"
    >
      QRIB
    </motion.span>

    <!-- LY: slow escape (longer now), snaps back with overshoot -->
    <motion.span
      class="inline-block"
      :animate="{
        x: [0, 13, -3, 1, 0],
        // rotate: [0, 6, -2, 0, 0],
        scale: [1, 1.03, 0.97, 1, 1],
      }"
      :transition="{
        duration: 5,
        repeat: Infinity,
        repeatDelay: 30,
        times: [0, 0.9, 0.94, 0.97, 1],
        ease: 'easeOut',
      }"
    >
      LY
    </motion.span>
  </div>
</template>