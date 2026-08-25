<script setup lang="ts">
type Tab =
  | 'offers'
  | 'favorites'
  | 'reservations'
  | 'service-requests'
  | 'availability'
  | 'requests'
  | 'provider'

defineProps<{
  tabs: {
    key: Tab
    label: string
  }[]
  activeTab: Tab
}>()

const emit = defineEmits<{
  select: [tab: Tab]
}>()
</script>

<template>
  <nav class="mb-8 flex gap-1 overflow-x-auto border-b border-ink/10">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      type="button"
      class="shrink-0 rounded-t-md px-4 py-3 font-mono text-xs tracking-wide transition"
      :class="
        activeTab === tab.key
          ? 'bg-primary text-surface'
          : 'text-ink/50 hover:bg-ink/[0.03] hover:text-ink'
      "
      @click="emit('select', tab.key)"
    >
      {{ tab.label }}
    </button>
  </nav>
</template>
