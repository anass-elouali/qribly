<script setup lang="ts">
    import Footer from '@/components/layout/Footer.vue';
    import Navbar from '@/components/layout/Navbar.vue';
    import { RouterView } from 'vue-router';
</script>

<template>
    <div class="min-h-screen flex flex-col bg-slate-100">
        <Navbar />
        <main class="flex-1">
            <RouterView />
        </main>
        <Footer />
    </div>
</template>