<script setup lang="ts">
defineProps<{
  currentStep: number
}>()

const steps = [
  {
    number: 1,
    label: 'Informations',
  },
  {
    number: 2,
    label: 'Photos',
  },
  {
    number: 3,
    label: 'Emplacement',
  },
]
</script>

<template>
  <div class="mb-8">
    <div class="flex items-center justify-between">
      <template
        v-for="(step, index) in steps"
        :key="step.number"
      >
        <!-- Step -->
        <div class="flex items-center gap-3">
          <div
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border text-sm font-semibold transition"
            :class="
              currentStep === step.number
                ? 'border-primary bg-primary text-surface'
                : currentStep > step.number
                  ? 'border-primary bg-primary/10 text-primary'
                  : 'border-ink/15 bg-surface text-ink/40'
            "
          >
            <span v-if="currentStep > step.number">✓</span>
            <span v-else>{{ step.number }}</span>
          </div>

          <span
            class="hidden font-mono text-xs tracking-wide sm:block"
            :class="
              currentStep >= step.number
                ? 'text-ink'
                : 'text-ink/40'
            "
          >
            {{ step.label }}
          </span>
        </div>

        <!-- Connector -->
        <div
          v-if="index < steps.length - 1"
          class="mx-3 h-px flex-1 transition"
          :class="
            currentStep > step.number
              ? 'bg-primary'
              : 'bg-ink/10'
          "
        ></div>
      </template>
    </div>

    <!-- Mobile progress -->
    <div class="mt-4 sm:hidden">
      <div class="mb-2 flex items-center justify-between">
        <span class="font-mono text-xs text-ink/50">
          Étape {{ currentStep }} sur {{ steps.length }}
        </span>

        <span class="font-mono text-xs font-semibold text-primary">
          {{ steps[currentStep - 1]?.label }}
        </span>
      </div>

      <div class="h-1 overflow-hidden rounded-full bg-ink/10">
        <div
          class="h-full rounded-full bg-primary transition-all duration-300"
          :style="{
            width: `${(currentStep / steps.length) * 100}%`,
          }"
        ></div>
      </div>
    </div>
  </div>
</template>