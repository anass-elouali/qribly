<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import dayjs from 'dayjs'
import api from '@/services/api'
import echo from '@/echo'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import type { Conversation, Message } from '@/types/conversation'
import type { PaginatedResponse } from '@/types/offer'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const chatStore = useChatStore()

const messages = ref<Message[]>([])
const loadingMessages = ref(false)
const sending = ref(false)
const newMessage = ref('')
const threadBody = ref<HTMLElement | null>(null)

const activeId = computed(() => (route.params.id ? Number(route.params.id) : null))

const activeConversation = computed<Conversation | undefined>(() =>
  chatStore.conversations.find((c) => c.id === activeId.value),
)

function otherParticipant(conversation: Conversation) {
  return conversation.user_one_id === authStore.user?.id ? conversation.user_two : conversation.user_one
}

function initials(name: string) {
  return name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

function otherName(conversation: Conversation) {
  return otherParticipant(conversation)?.name ?? 'Utilisateur'
}

function otherInitials(conversation: Conversation) {
  const participant = otherParticipant(conversation)
  return participant ? initials(participant.name) : '?'
}

function scrollToBottom() {
  nextTick(() => {
    if (threadBody.value) {
      threadBody.value.scrollTop = threadBody.value.scrollHeight
    }
  })
}

async function markMessageRead(message: Message) {
  try {
    await api.patch(`/messages/${message.id}/read`)
    message.read_at = new Date().toISOString()
    if (activeId.value) {
      chatStore.markRead(activeId.value)
    }
  } catch {
    // Non-bloquant — le statut lu est un plus, pas critique.
  }
}

let subscribedChannel: string | null = null

function unsubscribe() {
  if (subscribedChannel) {
    echo.leave(subscribedChannel)
    subscribedChannel = null
  }
}

function subscribe(conversationId: number) {
  unsubscribe()
  subscribedChannel = `conversation.${conversationId}`

  echo.private(subscribedChannel).listen('MessageSent', (event: { message: Message }) => {
    const message = event.message

    if (messages.value.some((m) => m.id === message.id)) {
      return
    }

    messages.value.push(message)
    chatStore.upsertFromMessage(conversationId, message)
    scrollToBottom()

    if (message.sender_id !== authStore.user?.id) {
      markMessageRead(message)
    }
  })
}

async function loadThread(conversationId: number) {
  loadingMessages.value = true
  messages.value = []

  // Subscribe before fetching history so no message sent by the other party
  // during the (potentially slow) history fetch is missed.
  subscribe(conversationId)

  try {
    const response = await api.get<PaginatedResponse<Message>>(`/conversations/${conversationId}/messages`)
    const history = [...response.data.data].reverse()

    for (const historyMessage of history) {
      if (!messages.value.some((m) => m.id === historyMessage.id)) {
        messages.value.push(historyMessage)
      }
    }
    // A message can arrive live (pushed by the listener) while this request is
    // still in flight — re-sort by id (chronological) so it lands in place
    // instead of staying wherever it happened to be pushed.
    messages.value.sort((a, b) => a.id - b.id)
    scrollToBottom()

    const unread = messages.value.filter((m) => m.sender_id !== authStore.user?.id && !m.read_at)
    await Promise.all(unread.map((m) => markMessageRead(m)))
  } catch {
    // Keep whatever arrived live even if the history fetch itself failed.
  } finally {
    loadingMessages.value = false
  }
}

async function sendMessage() {
  const body = newMessage.value.trim()
  if (!body || !activeId.value) {
    return
  }

  sending.value = true

  try {
    const response = await api.post<Message>(`/conversations/${activeId.value}/messages`, { body })
    const message = response.data

    if (!messages.value.some((m) => m.id === message.id)) {
      messages.value.push(message)
      scrollToBottom()
    }

    chatStore.upsertFromMessage(activeId.value, message)
    newMessage.value = ''
  } catch {
    // On garde le brouillon pour permettre de réessayer.
  } finally {
    sending.value = false
  }
}

function openConversation(id: number) {
  router.push({ name: 'conversation', params: { id } })
}

onMounted(async () => {
  await chatStore.load()
  if (activeId.value) {
    loadThread(activeId.value)
  }
})

watch(activeId, (id) => {
  if (id) {
    loadThread(id)
  } else {
    messages.value = []
    unsubscribe()
  }
})

onBeforeUnmount(unsubscribe)
</script>

<template>
  <div class="mx-auto grid h-[calc(100vh-4rem)] max-w-6xl grid-cols-1 md:grid-cols-[320px_1fr]">
    <aside class="min-h-0 flex-col border-ink/10 md:flex md:border-r" :class="activeId ? 'hidden' : 'flex'">
      <div class="px-6 py-5">
        <h1 class="font-display text-xl font-bold text-ink">Messages</h1>
      </div>

      <p v-if="chatStore.conversations.length === 0" class="px-6 font-mono text-sm text-ink/50">
        Aucune conversation pour le moment.
      </p>

      <ul v-else class="flex-1 overflow-y-auto px-2 pb-4">
        <li v-for="conversation in chatStore.conversations" :key="conversation.id">
          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left transition-colors hover:bg-ink/5"
            :class="conversation.id === activeId ? 'bg-ground' : ''"
            @click="openConversation(conversation.id)"
          >
            <span
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary font-mono text-xs font-bold text-surface"
            >
              {{ otherInitials(conversation) }}
            </span>

            <span class="min-w-0 flex-1">
              <span
                class="block truncate font-body text-sm text-ink"
                :class="chatStore.isUnread(conversation) ? 'font-bold' : 'font-semibold'"
              >
                {{ otherName(conversation) }}
              </span>
              <span
                class="block truncate font-body text-sm"
                :class="chatStore.isUnread(conversation) ? 'text-ink' : 'text-ink/60'"
              >
                {{ conversation.messages?.[0]?.body ?? '' }}
              </span>
            </span>

            <span class="flex shrink-0 flex-col items-end gap-1">
              <span class="font-mono text-[0.65rem] text-ink/40">
                {{ conversation.messages?.[0] ? dayjs(conversation.messages[0].created_at).fromNow() : '' }}
              </span>
              <span v-if="chatStore.isUnread(conversation)" class="h-2 w-2 rounded-full bg-accent"></span>
            </span>
          </button>
        </li>
      </ul>
    </aside>

    <section class="min-h-0 flex-col md:flex" :class="activeId ? 'flex' : 'hidden'">
      <template v-if="activeConversation">
        <div class="flex items-center gap-3 border-b border-ink/10 px-6 py-4">
          <button
            type="button"
            class="mr-1 text-ink/60 transition-colors hover:text-primary md:hidden"
            aria-label="Retour"
            @click="router.push({ name: 'conversations' })"
          >
            ←
          </button>
          <span
            class="flex h-10 w-10 items-center justify-center rounded-full bg-primary font-mono text-xs font-bold text-surface"
          >
            {{ otherInitials(activeConversation) }}
          </span>
          <p class="font-body font-semibold text-ink">{{ otherName(activeConversation) }}</p>
        </div>

        <div ref="threadBody" class="flex-1 overflow-y-auto px-6 py-5">
          <p v-if="loadingMessages" class="font-mono text-sm text-ink/50">Chargement…</p>

          <div v-else class="flex flex-col gap-3">
            <div
              v-for="message in messages"
              :key="message.id"
              class="flex max-w-[65%] flex-col"
              :class="message.sender_id === authStore.user?.id ? 'ml-auto items-end' : 'items-start'"
            >
              <p
                class="rounded-2xl px-4 py-2 text-sm"
                :class="
                  message.sender_id === authStore.user?.id
                    ? 'rounded-br-md bg-primary text-surface'
                    : 'rounded-bl-md border border-ink/10 bg-ground text-ink'
                "
              >
                {{ message.body }}
              </p>
              <span class="mt-1 px-1 font-mono text-[0.65rem] text-ink/40">
                {{ dayjs(message.created_at).format('HH:mm') }}
              </span>
            </div>
          </div>
        </div>

        <form class="flex gap-2 border-t border-ink/10 px-6 py-4" @submit.prevent="sendMessage">
          <input
            v-model="newMessage"
            type="text"
            placeholder="Écrire un message…"
            class="flex-1 rounded-lg border border-ink/15 bg-ground px-3 py-2 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
          <button
            type="submit"
            :disabled="sending || !newMessage.trim()"
            class="rounded-lg bg-accent px-4 py-2 font-semibold text-ink transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
          >
            Envoyer
          </button>
        </form>
      </template>

      <div v-else class="flex flex-1 items-center justify-center px-6 text-center">
        <p class="font-mono text-sm text-ink/50">Sélectionne une conversation pour commencer à discuter.</p>
      </div>
    </section>
  </div>
</template>
