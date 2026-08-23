<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  Combobox,
  ComboboxButton,
  ComboboxInput,
  ComboboxOption,
  ComboboxOptions,
  TransitionRoot,
} from '@headlessui/vue'
import { Check, ChevronsUpDown, MapPin } from 'lucide-vue-next'

import type { CityOption } from '@/data/moroccanCities'
import { moroccanCities, normalizeCity } from '@/data/moroccanCities'

const props = withDefaults(
  defineProps<{
    modelValue: CityOption | null
    id?: string
    label?: string
    placeholder?: string
  }>(),
  {
    id: 'city-combobox',
    label: 'Ville',
    placeholder: 'Rechercher une ville…',
  },
)

const emit = defineEmits<{
  'update:modelValue': [city: CityOption | null]
}>()

const query = ref('')

const filteredCities = computed(() => {
  const normalizedQuery = normalizeCity(query.value)

  if (!normalizedQuery) return moroccanCities

  return moroccanCities.filter((city) => normalizeCity(city.name).includes(normalizedQuery))
})

function displayCity(item: unknown): string {
  if (!item || typeof item !== 'object' || !('name' in item)) return ''

  return String(item.name)
}
</script>

<template>
  <Combobox
    :model-value="modelValue"
    by="name"
    nullable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="relative">
      <label :for="id" class="sr-only">{{ label }}</label>

      <div
        class="relative flex h-11 w-full items-center overflow-hidden rounded-lg border border-ink/15 bg-surface transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15"
      >
        <MapPin :size="17" class="ml-3 shrink-0 text-primary" aria-hidden="true" />
        <ComboboxInput
          :id="id"
          class="h-full min-w-0 flex-1 border-0 bg-transparent px-2.5 text-sm text-ink outline-none placeholder:text-ink/40"
          :display-value="displayCity"
          :placeholder="placeholder"
          autocomplete="off"
          @change="query = ($event.target as HTMLInputElement).value"
        />
        <ComboboxButton
          class="flex h-full w-10 shrink-0 items-center justify-center text-ink/45 transition hover:text-primary"
          :aria-label="`Ouvrir la liste des villes pour ${label.toLowerCase()}`"
        >
          <ChevronsUpDown :size="17" aria-hidden="true" />
        </ComboboxButton>
      </div>

      <TransitionRoot
        leave="transition ease-in duration-100"
        leave-from="opacity-100"
        leave-to="opacity-0"
        @after-leave="query = ''"
      >
        <ComboboxOptions
          class="absolute z-[2100] mt-2 max-h-64 w-full overflow-auto rounded-xl border border-ink/10 bg-surface p-1.5 shadow-xl focus:outline-none"
        >
          <div v-if="filteredCities.length === 0" class="px-3 py-4 text-sm text-ink/50">
            Aucune ville trouvée.
          </div>

          <ComboboxOption
            v-for="city in filteredCities"
            :key="city.name"
            v-slot="{ active, selected }"
            :value="city"
            as="template"
          >
            <li
              class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2.5 text-sm"
              :class="active ? 'bg-primary text-surface' : 'text-ink'"
            >
              <MapPin :size="15" class="shrink-0" aria-hidden="true" />
              <span class="flex-1 truncate" :class="selected ? 'font-semibold' : 'font-normal'">
                {{ city.name }}
              </span>
              <Check v-if="selected" :size="16" class="shrink-0" aria-hidden="true" />
            </li>
          </ComboboxOption>
        </ComboboxOptions>
      </TransitionRoot>
    </div>
  </Combobox>
</template>
