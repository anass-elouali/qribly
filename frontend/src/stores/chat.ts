import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import type { Conversation, Message } from '@/types/conversation'

export const useChatStore = defineStore('chat', () => {
  const conversations = ref<Conversation[]>([])
  const loaded = ref(false)

  async function load(force = false) {
    if (loaded.value && !force) {
      return
    }

    try {
      const response = await api.get<Conversation[]>('/conversations')
      conversations.value = response.data
      loaded.value = true
    } catch {
      // Not authenticated or request failed — stay empty rather than block the UI.
    }
  }

  function isUnread(conversation: Conversation) {
    const authStore = useAuthStore()
    const lastMessage = conversation.messages?.[0]
    return !!lastMessage && lastMessage.sender_id !== authStore.user?.id && !lastMessage.read_at
  }

  const unreadCount = computed(() => conversations.value.filter(isUnread).length)

  function upsertFromMessage(conversationId: number, message: Message) {
    const conversation = conversations.value.find((c) => c.id === conversationId)
    if (!conversation) {
      return
    }

    conversation.messages = [message]
    conversation.updated_at = message.created_at
    conversations.value.sort((a, b) => (a.updated_at < b.updated_at ? 1 : -1))
  }

  function upsertConversation(conversation: Conversation) {
    const index = conversations.value.findIndex((c) => c.id === conversation.id)
    if (index === -1) {
      conversations.value.unshift(conversation)
    } else {
      conversations.value[index] = conversation
    }
  }

  function markRead(conversationId: number) {
    const conversation = conversations.value.find((c) => c.id === conversationId)
    const lastMessage = conversation?.messages?.[0]
    if (lastMessage) {
      lastMessage.read_at = new Date().toISOString()
    }
  }

  function reset() {
    conversations.value = []
    loaded.value = false
  }

  return {
    conversations,
    loaded,
    load,
    isUnread,
    unreadCount,
    upsertFromMessage,
    upsertConversation,
    markRead,
    reset,
  }
})
