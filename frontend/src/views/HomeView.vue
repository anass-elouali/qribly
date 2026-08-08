<script>
    import { RouterLink } from 'vue-router';
    import api from '@/services/api';

    async function loadCategories() {
      const response = await api.get('/offers')

      console.log(response.data)
    }

  loadCategories()
</script>

<template>
  <main class="min-h-screen bg-slate-100 flex flex-col gap-4 items-center justify-center">
    <h1 class="text-4xl font-bold text-blue-600">
      Welcome to Qribly
    </h1>
    <div class="flex gap-4">
        <RouterLink :to="{name:'login'}" class="rounded bg-blue-600 px-5 py-3 text-white">
            Login
        </RouterLink>
        <RouterLink :to="{name:'register'}"  class="rounded bg-green-600 px-5 py-3 text-white">
            Register
        </RouterLink>
        <RouterLink
          :to="{
              name: 'offer-details',
              params: {
                id: 1,
            },
          }"
          class="rounded bg-purple-600 px-5 py-3 text-white">
          View Offer #1
        </RouterLink>
    </div>
  </main>
</template>