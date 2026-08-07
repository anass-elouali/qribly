<script setup lang="ts">
    import { RouterLink } from 'vue-router';
</script>

<template>
    <header class="border-b bg-white shadow-sm">
        <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6">
            <RouterLink
                :to="{ name: 'home' }"
                class="text-2xl font-bold text-blue-600"
            >
                Qribly
            </RouterLink>

            <div class="flex items-center gap-6">
                <RouterLink
                :to="{ name: 'home' }"
                class="text-gray-700 hover:text-blue-600"
                >
                    Home
                </RouterLink>

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
        </nav>
    </header>
</template>