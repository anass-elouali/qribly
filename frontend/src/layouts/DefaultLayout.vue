<script setup lang="ts">
    import Footer from '@/components/layout/Footer.vue';
    import Navbar from '@/components/layout/Navbar.vue';
    import QribAssistant from '@/components/assistant/QribAssistant.vue';
    import { RouterView } from 'vue-router';
</script>

<template>
    <div class="min-h-screen flex flex-col bg-ground font-body text-ink">
        <Navbar />
        <main class="flex-1">
            <RouterView />
        </main>
        <Footer />
        <QribAssistant />
    </div>
</template>
