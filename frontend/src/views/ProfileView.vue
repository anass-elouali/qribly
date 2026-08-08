<script setup lang="ts">
    import { useAuthStore } from '@/stores/auth';
    const authStore = useAuthStore()
</script>

<template>
    <div class="p-8">
    <h1 class="text-3xl font-bold">
      Profile
    </h1>

    <p class="mt-2 text-gray-600">
      Authticated: {{ authStore.isAuthenticated }}
    </p>
  </div>
</template>