import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'
import type { Offer, PaginatedResponse } from '@/types/offer'

export const useFavoritesStore = defineStore('favorites', () => {
  const ids = ref<Set<number>>(new Set())
  const loaded = ref(false)

  async function load() {
    if (loaded.value) {
      return
    }

    try {
      const allIds = new Set<number>()
      let page = 1
      let lastPage = 1

      do {
        const response = await api.get<PaginatedResponse<Offer>>('/favorites', { params: { page } })
        response.data.data.forEach((offer) => allIds.add(offer.id))
        lastPage = response.data.meta.last_page
        page++
      } while (page <= lastPage)

      ids.value = allIds
      loaded.value = true
    } catch {
      // Not authenticated or request failed — stay empty rather than block the UI.
    }
  }

  function isFavorite(offerId: number) {
    return ids.value.has(offerId)
  }

  async function toggle(offerId: number) {
    if (ids.value.has(offerId)) {
      await api.delete(`/offers/${offerId}/favorite`)
      ids.value.delete(offerId)
    } else {
      await api.post(`/offers/${offerId}/favorite`)
      ids.value.add(offerId)
    }
  }

  function reset() {
    ids.value = new Set()
    loaded.value = false
  }

  return { ids, loaded, load, isFavorite, toggle, reset }
})
