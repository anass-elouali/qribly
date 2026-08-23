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
import { Check, ChevronsUpDown, Globe2, LocateFixed, MapPin } from 'lucide-vue-next'

import { moroccanCities, normalizeCity } from '@/data/moroccanCities'

type LocationOption = {
  key: string
  label: string
  kind: 'all' | 'nearby' | 'city'
}

const props = withDefaults(
  defineProps<{
    modelValue: string
    id?: string
    label?: string
    placeholder?: string
    disabled?: boolean
  }>(),
  {
    id: 'location-scope-combobox',
    label: 'Où chercher ?',
    placeholder: 'Tout le Maroc, ma position ou une ville…',
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [key: string]
}>()

const query = ref('')

const allOptions = computed<LocationOption[]>(() => [
  { key: 'all', label: 'Tout le Maroc', kind: 'all' },
  { key: 'nearby', label: 'Ma position', kind: 'nearby' },
  ...moroccanCities.map((city) => ({
    key: `city:${city.name}`,
    label: city.name,
    kind: 'city' as const,
  })),
])

const selectedOption = computed(
  () => allOptions.value.find((option) => option.key === props.modelValue) ?? allOptions.value[0]!,
)

const filteredOptions = computed(() => {
  const normalizedQuery = normalizeCity(query.value)

  if (!normalizedQuery) return allOptions.value

  return allOptions.value.filter((option) => normalizeCity(option.label).includes(normalizedQuery))
})

function displayOption(item: unknown): string {
  if (!item || typeof item !== 'object' || !('label' in item)) return ''

  return String(item.label)
}

function selectOption(option: LocationOption | null) {
  if (!option) return

  emit('update:modelValue', option.key)
}
</script>

<template>
  <Combobox
    :model-value="selectedOption"
    by="key"
    :disabled="disabled"
    @update:model-value="selectOption"
  >
    <div class="relative">
      <label :for="id" class="sr-only">{{ label }}</label>

      <div
        class="relative flex h-12 w-full items-center overflow-hidden rounded-xl border border-ink/15 bg-ground transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15"
      >
        <MapPin :size="18" class="ml-4 shrink-0 text-primary" aria-hidden="true" />
        <ComboboxInput
          :id="id"
          class="h-full min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-ink outline-none placeholder:text-ink/40"
          :display-value="displayOption"
          :placeholder="placeholder"
          autocomplete="off"
          @change="query = ($event.target as HTMLInputElement).value"
        />
        <ComboboxButton
          class="flex h-full w-11 shrink-0 items-center justify-center text-ink/45 transition hover:text-primary disabled:cursor-wait disabled:opacity-50"
          :aria-label="`Ouvrir les zones pour ${label.toLowerCase()}`"
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
          class="absolute z-[2100] mt-2 max-h-72 w-full overflow-auto rounded-xl border border-ink/10 bg-surface p-1.5 shadow-xl focus:outline-none"
        >
          <div v-if="filteredOptions.length === 0" class="px-3 py-4 text-sm text-ink/50">
            Aucune zone trouvée.
          </div>

          <ComboboxOption
            v-for="option in filteredOptions"
            :key="option.key"
            v-slot="{ active, selected }"
            :value="option"
            as="template"
          >
            <li
              class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm"
              :class="active ? 'bg-primary text-surface' : 'text-ink'"
            >
              <Globe2 v-if="option.kind === 'all'" :size="16" class="shrink-0" aria-hidden="true" />
              <LocateFixed
                v-else-if="option.kind === 'nearby'"
                :size="16"
                class="shrink-0"
                aria-hidden="true"
              />
              <MapPin v-else :size="16" class="shrink-0" aria-hidden="true" />
              <span class="flex-1 truncate" :class="selected ? 'font-semibold' : 'font-normal'">
                {{ option.label }}
              </span>
              <Check v-if="selected" :size="16" class="shrink-0" aria-hidden="true" />
            </li>
          </ComboboxOption>
        </ComboboxOptions>
      </TransitionRoot>
    </div>
  </Combobox>
</template>
