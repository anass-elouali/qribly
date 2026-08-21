import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'
import type { Offer, PaginatedResponse } from '@/types/offer'

export const useFavoritesStore = defineStore('favorites', () => {
  const ids = ref<Set<number>>(new Set())
  const loaded = ref(false)
  const pendingIds = ref<Set<number>>(new Set())

  // Several FavoriteButton instances can mount around the same time and all
  // call load() before the first fetch finishes — share the in-flight
  // request instead of firing one per button.
  let loadPromise: Promise<void> | null = null

  function load() {
    if (loaded.value) {
      return Promise.resolve()
    }

    if (loadPromise) {
      return loadPromise
    }

    loadPromise = (async () => {
      try {
        const allIds = new Set<number>()
        let page = 1
        let lastPage = 1

        do {
          const response = await api.get<PaginatedResponse<Offer>>('/favorites', {
            params: { page },
          })
          response.data.data.forEach((offer) => allIds.add(offer.id))
          lastPage = response.data.meta.last_page
          page++
        } while (page <= lastPage)

        ids.value = allIds
        loaded.value = true
      } catch {
        // Not authenticated or request failed — stay empty rather than block the UI.
      } finally {
        loadPromise = null
      }
    })()

    return loadPromise
  }

  function isFavorite(offerId: number) {
    return ids.value.has(offerId)
  }

  function isPending(offerId: number) {
    return pendingIds.value.has(offerId)
  }

  async function toggle(offerId: number) {
    if (isPending(offerId)) {
      return
    }

    pendingIds.value = new Set(pendingIds.value).add(offerId)

    try {
      if (ids.value.has(offerId)) {
        await api.delete(`/offers/${offerId}/favorite`)
        ids.value.delete(offerId)
      } else {
        await api.post(`/offers/${offerId}/favorite`)
        ids.value.add(offerId)
      }
    } finally {
      const nextPendingIds = new Set(pendingIds.value)
      nextPendingIds.delete(offerId)
      pendingIds.value = nextPendingIds
    }
  }

  function reset() {
    ids.value = new Set()
    pendingIds.value = new Set()
    loaded.value = false
    loadPromise = null
  }

  return { ids, loaded, pendingIds, load, isFavorite, isPending, toggle, reset }
})
