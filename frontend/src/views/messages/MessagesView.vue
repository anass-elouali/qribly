<script setup lang="ts">
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from 'vue'
import { useRoute, useRouter } from 'vue-router'
import dayjs from 'dayjs'

import api from '@/services/api'
import echo from '@/echo'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import { initials } from '@/utils/user'

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

const activeId = computed(() =>
  route.params.id ? Number(route.params.id) : null,
)

const activeConversation = computed<Conversation | undefined>(() =>
  chatStore.conversations.find((conversation) => conversation.id === activeId.value),
)

/*
|--------------------------------------------------------------------------
| Participants
|--------------------------------------------------------------------------
*/

function otherParticipant(conversation: Conversation) {
  return conversation.user_one_id === authStore.user?.id
    ? conversation.user_two
    : conversation.user_one
}

function otherName(conversation: Conversation) {
  return otherParticipant(conversation)?.name ?? 'Utilisateur'
}

function otherInitials(conversation: Conversation) {
  const participant = otherParticipant(conversation)

  return participant ? initials(participant.name) : '?'
}

/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

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
    // Non-blocking.
  }
}

/*
|--------------------------------------------------------------------------
| Realtime
|--------------------------------------------------------------------------
*/

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

  echo
    .private(subscribedChannel)
    .listen('MessageSent', (event: { message: Message }) => {
      const message = event.message

      if (messages.value.some((item) => item.id === message.id)) {
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

/*
|--------------------------------------------------------------------------
| Load conversation
|--------------------------------------------------------------------------
*/

async function loadThread(conversationId: number) {
  loadingMessages.value = true
  messages.value = []

  subscribe(conversationId)

  try {
    const response = await api.get<PaginatedResponse<Message>>(
      `/conversations/${conversationId}/messages`,
    )

    const history = [...response.data.data].reverse()

    for (const historyMessage of history) {
      if (!messages.value.some((message) => message.id === historyMessage.id)) {
        messages.value.push(historyMessage)
      }
    }

    messages.value.sort((a, b) => a.id - b.id)

    scrollToBottom()

    const unread = messages.value.filter(
      (message) =>
        message.sender_id !== authStore.user?.id &&
        !message.read_at,
    )

    await Promise.all(unread.map((message) => markMessageRead(message)))
  } catch {
    // Keep live messages if history fails.
  } finally {
    loadingMessages.value = false
  }
}

/*
|--------------------------------------------------------------------------
| Send message
|--------------------------------------------------------------------------
*/

async function sendMessage() {
  const body = newMessage.value.trim()

  if (!body || !activeId.value) {
    return
  }

  sending.value = true

  try {
    const response = await api.post<Message>(
      `/conversations/${activeId.value}/messages`,
      { body },
    )

    const message = response.data

    if (!messages.value.some((item) => item.id === message.id)) {
      messages.value.push(message)
      scrollToBottom()
    }

    chatStore.upsertFromMessage(activeId.value, message)

    newMessage.value = ''
  } catch {
    // Keep draft.
  } finally {
    sending.value = false
  }
}

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

function openConversation(id: number) {
  router.push({
    name: 'conversation',
    params: { id },
  })
}

function closeConversation() {
  router.push({
    name: 'conversations',
  })
}

/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

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
  <div
    class="mx-auto flex h-[calc(100vh-4rem)] max-w-6xl overflow-hidden border-x border-ink/10 bg-ground"
  >
    <!-- ========================================================= -->
    <!-- CONVERSATIONS SIDEBAR                                     -->
    <!-- ========================================================= -->

    <aside
      class="w-full shrink-0 flex-col bg-surface md:flex md:w-[320px] md:border-r md:border-ink/10"
      :class="activeId ? 'hidden' : 'flex'"
    >
      <!-- Sidebar header -->
      <div class="border-b border-ink/10 px-5 py-5">
        <div class="flex items-center justify-between">
          <div>
            <p
              class="font-mono text-[0.6rem] tracking-[0.2em] text-primary uppercase"
            >
              Communication
            </p>

            <h1 class="mt-1 font-display text-2xl font-bold text-ink">
              Messages
            </h1>
          </div>

          <span
            v-if="chatStore.unreadCount"
            class="flex h-6 min-w-6 items-center justify-center rounded-full bg-primary px-1.5 font-mono text-[0.65rem] font-bold text-surface"
          >
            {{ chatStore.unreadCount }}
          </span>
        </div>
      </div>

      <!-- Conversation list -->
      <div class="min-h-0 flex-1 overflow-y-auto">
        <!-- Empty -->
        <div
          v-if="chatStore.conversations.length === 0"
          class="flex h-full items-center justify-center px-8 text-center"
        >
          <div>
            <div
              class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-ink/10 bg-ground"
            >
              <span class="h-2 w-2 rounded-full bg-primary" />
            </div>

            <p class="mt-4 font-display font-semibold text-ink">
              Aucune conversation
            </p>

            <p class="mt-1 font-body text-sm leading-relaxed text-ink/45">
              Vos conversations avec les autres utilisateurs apparaîtront ici.
            </p>
          </div>
        </div>

        <!-- Conversations -->
        <ul v-else class="space-y-1 p-2">
          <li
            v-for="conversation in chatStore.conversations"
            :key="conversation.id"
          >
            <button
              type="button"
              class="group flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition"
              :class="
                conversation.id === activeId
                  ? 'bg-primary/[0.08]'
                  : 'hover:bg-ink/[0.04]'
              "
              @click="openConversation(conversation.id)"
            >
              <!-- Avatar -->
              <span
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full font-mono text-xs font-bold transition"
                :class="
                  conversation.id === activeId
                    ? 'bg-primary text-surface'
                    : 'bg-ground text-ink/65 group-hover:bg-primary/10 group-hover:text-primary'
                "
              >
                {{ otherInitials(conversation) }}
              </span>

              <!-- Content -->
              <span class="min-w-0 flex-1">
                <span
                  class="block truncate font-body text-sm"
                  :class="
                    chatStore.isUnread(conversation)
                      ? 'font-bold text-ink'
                      : 'font-semibold text-ink'
                  "
                >
                  {{ otherName(conversation) }}
                </span>

                <span
                  class="mt-0.5 block truncate font-body text-xs"
                  :class="
                    chatStore.isUnread(conversation)
                      ? 'font-medium text-ink/80'
                      : 'text-ink/45'
                  "
                >
                  {{ conversation.messages?.[0]?.body ?? 'Nouvelle conversation' }}
                </span>
              </span>

              <!-- Meta -->
              <span class="flex shrink-0 flex-col items-end gap-1.5">
                <span class="font-mono text-[0.6rem] text-ink/35">
                  {{
                    conversation.messages?.[0]
                      ? dayjs(conversation.messages[0].created_at).fromNow()
                      : ''
                  }}
                </span>

                <span
                  v-if="chatStore.isUnread(conversation)"
                  class="h-1.5 w-1.5 rounded-full bg-accent"
                />
              </span>
            </button>
          </li>
        </ul>
      </div>
    </aside>

    <!-- ========================================================= -->
    <!-- CHAT THREAD                                               -->
    <!-- ========================================================= -->

    <section
      class="min-w-0 flex-1 flex-col bg-ground"
      :class="activeId ? 'flex' : 'hidden md:flex'"
    >
      <template v-if="activeConversation">
        <!-- Chat header -->
        <header
          class="flex shrink-0 items-center gap-3 border-b border-ink/10 bg-surface px-4 py-4 sm:px-6"
        >
          <button
            type="button"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-ink/50 transition hover:bg-ink/5 hover:text-ink md:hidden"
            aria-label="Retour aux conversations"
            @click="closeConversation"
          >
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              class="h-5 w-5"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M15 18l-6-6 6-6"
              />
            </svg>
          </button>

          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary font-mono text-xs font-bold text-surface"
          >
            {{ otherInitials(activeConversation) }}
          </span>

          <div class="min-w-0">
            <p class="truncate font-body font-semibold text-ink">
              {{ otherName(activeConversation) }}
            </p>

            <p class="font-mono text-[0.6rem] tracking-wide text-ink/35 uppercase">
              Conversation
            </p>
          </div>
        </header>

        <!-- Messages -->
        <div
          ref="threadBody"
          class="min-h-0 flex-1 overflow-y-auto px-4 py-6 sm:px-6"
        >
          <!-- Loading -->
          <div
            v-if="loadingMessages"
            class="flex h-full items-center justify-center"
          >
            <p class="font-mono text-xs tracking-wide text-ink/40 uppercase">
              Chargement…
            </p>
          </div>

          <!-- Empty thread -->
          <div
            v-else-if="messages.length === 0"
            class="flex h-full items-center justify-center"
          >
            <div class="max-w-xs text-center">
              <div
                class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-ink/10 bg-surface"
              >
                <span class="h-2 w-2 rounded-full bg-primary" />
              </div>

              <p class="mt-4 font-display font-semibold text-ink">
                Commencez la conversation
              </p>

              <p class="mt-1 font-body text-sm leading-relaxed text-ink/45">
                Envoyez un message pour commencer à échanger.
              </p>
            </div>
          </div>

          <!-- Message list -->
          <div
            v-else
            class="mx-auto flex w-full max-w-3xl flex-col gap-4"
          >
            <div
              v-for="message in messages"
              :key="message.id"
              class="flex max-w-[80%] flex-col sm:max-w-[70%]"
              :class="
                message.sender_id === authStore.user?.id
                  ? 'ml-auto items-end'
                  : 'items-start'
              "
            >
              <div
                class="px-4 py-2.5 text-sm leading-relaxed"
                :class="
                  message.sender_id === authStore.user?.id
                    ? 'rounded-2xl rounded-br-md bg-primary text-surface'
                    : 'rounded-2xl rounded-bl-md border border-ink/10 bg-surface text-ink'
                "
              >
                {{ message.body }}
              </div>

              <span
                class="mt-1.5 px-1 font-mono text-[0.6rem] text-ink/30"
              >
                {{ dayjs(message.created_at).format('HH:mm') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Composer -->
        <div class="shrink-0 border-t border-ink/10 bg-surface px-4 py-4 sm:px-6">
          <form
            class="mx-auto flex max-w-3xl items-end gap-2"
            @submit.prevent="sendMessage"
          >
            <input
              v-model="newMessage"
              type="text"
              autocomplete="off"
              placeholder="Écrire un message…"
              class="min-w-0 flex-1 rounded-xl border border-ink/10 bg-ground px-4 py-3 font-body text-sm text-ink outline-none transition placeholder:text-ink/35 focus:border-primary focus:ring-2 focus:ring-primary/10"
            />

            <button
              type="submit"
              :disabled="sending || !newMessage.trim()"
              class="flex h-11 shrink-0 items-center justify-center rounded-xl bg-primary px-5 font-body text-sm font-semibold text-surface transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
            >
              {{ sending ? '…' : 'Envoyer' }}
            </button>
          </form>
        </div>
      </template>

      <!-- No active conversation -->
      <div
        v-else
        class="flex flex-1 items-center justify-center px-6 text-center"
      >
        <div class="max-w-sm">
          <div
            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-ink/10 bg-surface"
          >
            <span class="h-2 w-2 rounded-full bg-primary" />
          </div>

          <h2 class="mt-5 font-display text-xl font-bold text-ink">
            Vos messages
          </h2>

          <p class="mt-2 font-body text-sm leading-relaxed text-ink/45">
            Sélectionnez une conversation pour afficher vos messages.
          </p>
        </div>
      </div>
    </section>
  </div>
</template>