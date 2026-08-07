<script setup lang="ts">
    import { Heart } from 'lucide-vue-next';
    import Card from '../ui/Card.vue';

    defineProps<{
        id:number ,title: string, price:number, city:string
    }>()

    const emit = defineEmits<{
        (e:'delete', id:number):void
        (e:'favorite', id:number):void
    }>()

</script>

<template>
    <Card>
        <h2 class="text-xl font-semibold">
            {{ title }}
        </h2>
        <p class="text-gray-600">
            {{ price }}$
        </p>
        <p class="text-sm text-gray-500">
            {{ city }}
        </p>

        <button @click="emit('favorite',id)"  class="mt-4 rounded-full p-2 text-red-500 transition hover:bg-red-100">
            <Heart :size="18" />
        </button>

        <button
            class="m-4 rounded bg-red-500 px-4 py-2 text-white cursor-pointer"
            @click="emit('delete', id)"
        >
            Delete
        </button>
    </Card>
</template>