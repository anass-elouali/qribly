<script setup lang="ts">
    import { RouterLink, useRouter } from 'vue-router';
    import { useAuthStore } from '@/stores/auth';
    import QriblyLogo from '@/components/branding/QriblyLogo.vue';

    const authStore = useAuthStore()
    const router = useRouter()

    async function handelLogout() {
        await authStore.logout()

        await router.push({
            name: 'home',
        })
    }
</script>

<template>
    <header class="border-b bg-white shadow-sm">
        <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6">
            <RouterLink
                :to="{ name: 'home' }"
                class="flex items-center text-xl font-bold group"
                aria-label="QRIBLY home"
            >
                <QriblyLogo />
                

            </RouterLink>

        

            <div v-if="!authStore.isAuthenticated" class="flex items-center gap-6">
               

                <RouterLink
                    :to="{ name: 'login' }"
                    class="text-gray-700 hover:text-blue-600"
                >
                    Login
                </RouterLink>

                <RouterLink
                :to="{ name: 'register' }"
                class="text-gray-700 hover:text-blue-600"
                >
                    Register
                </RouterLink>

             
            </div>

            <div  v-if="authStore.isAuthenticated" class="">
                <button 
                    class="cursor-pointer"
                    @click="handelLogout"
                >
                    Logout
                </button>

            </div>

            

        </nav>
    </header>
</template>