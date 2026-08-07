<script setup lang="ts">
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-slate-100">
    <div class="rounded-lg bg-white p-8 shadow">
      <h1 class="text-3xl font-bold">Offer Details</h1>

      <p class="mt-4 text-lg">
        Offer ID: {{ route.params.id }}
      </p>
    </div>
  </main>
</template>