<script setup lang="ts">
import { computed } from 'vue'

type Variant = 'loading' | 'error' | 'empty'

const props = withDefaults(
  defineProps<{
    variant: Variant
    title: string
    message?: string
    actionLabel?: string
    compact?: boolean
  }>(),
  {
    message: '',
    actionLabel: '',
    compact: false,
  },
)

const emit = defineEmits<{
  action: []
}>()

const role = computed(() => (props.variant === 'error' ? 'alert' : 'status'))
</script>

<template>
  <div
    :role="role"
    :aria-live="variant === 'error' ? 'assertive' : 'polite'"
    :aria-busy="variant === 'loading'"
    :class="[
      variant === 'error'
        ? 'flex flex-col gap-3 rounded-lg bg-status-reserved/10 px-4 py-3 text-left sm:flex-row sm:items-center sm:justify-between'
        : variant === 'empty'
          ? `flex flex-col items-center justify-center rounded-xl border border-dashed border-ink/15 px-6 text-center ${compact ? 'min-h-0 py-8' : 'min-h-64 py-12'}`
          : `flex flex-col items-center justify-center rounded-xl border border-ink/10 bg-surface px-6 text-center ${compact ? 'min-h-0 py-5' : 'min-h-48 py-10'}`,
    ]"
  >
    <template v-if="variant === 'error'">
      <div class="min-w-0">
        <p class="font-body text-sm font-semibold text-status-reserved">
          {{ title }}
        </p>
        <p v-if="message" class="mt-1 font-body text-sm leading-5 text-ink/60">
          {{ message }}
        </p>
      </div>

      <button
        v-if="actionLabel"
        type="button"
        class="self-start font-body text-sm font-semibold text-primary hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary sm:shrink-0 sm:self-center"
        @click="emit('action')"
      >
        {{ actionLabel }}
      </button>
    </template>

    <template v-else>
      <span
        v-if="variant === 'loading'"
        class="h-8 w-8 animate-spin rounded-full border-2 border-primary/20 border-t-primary motion-reduce:animate-none"
        aria-hidden="true"
      />

      <p
        class="font-display font-semibold text-ink"
        :class="[variant === 'loading' ? 'mt-4' : '', compact ? 'text-base' : 'text-lg']"
      >
        {{ title }}
      </p>

      <p v-if="message" class="mt-1 max-w-md font-body text-sm leading-6 text-ink/55">
        {{ message }}
      </p>

      <button
        v-if="actionLabel"
        type="button"
        class="mt-3 font-body text-sm font-semibold text-primary hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        @click="emit('action')"
      >
        {{ actionLabel }}
      </button>
    </template>
  </div>
</template>
