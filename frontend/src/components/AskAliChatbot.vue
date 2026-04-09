<template>
  <div class="fixed bottom-6 left-6 z-40">
    <!-- Chat Panel -->
    <Transition
      enter-active-class="transition-all duration-500 ease-spring"
      enter-from-class="opacity-0 scale-95 translate-y-4"
      enter-to-class="opacity-100 scale-100 translate-y-0"
      leave-active-class="transition-all duration-300 ease-spring"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95 translate-y-4"
    >
      <div
        v-if="isOpen"
        class="absolute bottom-16 left-0 w-80 md:w-[22rem] h-[28rem]"
      >
        <div class="bezel-shell h-full">
          <div class="bezel-core h-full flex flex-col overflow-hidden">
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-border-hairline">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-accent-gold/10 border border-accent-gold/20 flex items-center justify-center">
                  <svg class="w-4 h-4 text-accent-gold" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z"/>
                  </svg>
                </div>
                <div>
                  <h3 class="font-display font-semibold text-fg-primary text-sm">Ask Ali</h3>
                  <p class="text-[10px] text-fg-dim font-mono tracking-wider uppercase">AI Assistant</p>
                </div>
              </div>
              <button @click="isOpen = false" class="w-7 h-7 rounded-full bg-white/4 border border-border-hairline flex items-center justify-center text-fg-muted hover:text-fg-primary hover:border-border-hover transition-all duration-700 ease-spring" aria-label="Close chat">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Messages -->
            <div ref="messagesRef" class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
              <!-- Empty State — Suggested Questions -->
              <div v-if="messages.length === 0" class="flex flex-col items-center justify-center h-full text-center">
                <svg class="w-10 h-10 text-fg-dim mb-4" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                </svg>
                <p class="text-fg-muted text-sm mb-5 font-light">Ask me about Ali's projects, skills, or experience.</p>
                <div class="space-y-2 w-full">
                  <button
                    v-for="q in suggestedQuestions"
                    :key="q"
                    @click="askQuestion(q)"
                    class="block w-full text-left px-4 py-2.5 rounded-xl text-xs text-fg-muted hover:text-accent-gold bg-white/3 border border-border-hairline hover:border-accent-gold/20 transition-all duration-700 ease-spring"
                  >
                    {{ q }}
                  </button>
                </div>
              </div>

              <!-- Messages list -->
              <div
                v-for="(msg, i) in messages"
                :key="i"
                class="flex"
                :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
              >
                <div
                  class="max-w-[85%] px-4 py-2.5 text-sm leading-relaxed"
                  :class="[
                    msg.role === 'user'
                      ? 'bg-accent-gold/12 text-fg-primary rounded-2xl rounded-tr-sm'
                      : 'bg-white/4 text-fg-muted rounded-2xl rounded-tl-sm border border-border-hairline'
                  ]"
                >
                  {{ msg.content }}
                </div>
              </div>

              <!-- Typing indicator -->
              <div v-if="isLoading" class="flex justify-start">
                <div class="bg-white/4 border border-border-hairline rounded-2xl rounded-tl-sm px-4 py-3 flex gap-1.5">
                  <span class="w-1.5 h-1.5 bg-fg-dim rounded-full animate-bounce" style="animation-delay:0ms" />
                  <span class="w-1.5 h-1.5 bg-fg-dim rounded-full animate-bounce" style="animation-delay:150ms" />
                  <span class="w-1.5 h-1.5 bg-fg-dim rounded-full animate-bounce" style="animation-delay:300ms" />
                </div>
              </div>
            </div>

            <!-- Input -->
            <div class="px-4 py-3 border-t border-border-hairline">
              <form @submit.prevent="handleSend" class="flex gap-2">
                <input
                  v-model="inputText"
                  type="text"
                  placeholder="Type a message..."
                  class="flex-1 px-4 py-2.5 rounded-full bg-white/4 border border-border-hairline text-sm text-fg-primary placeholder:text-fg-dim focus:outline-none focus:border-accent-gold/30 transition-all duration-700 ease-spring"
                  :disabled="isLoading"
                />
                <button
                  type="submit"
                  class="w-9 h-9 rounded-full bg-accent-gold text-bg-deep flex items-center justify-center disabled:opacity-40 transition-all duration-700 ease-spring hover:shadow-glow-gold active:scale-95"
                  :disabled="!inputText.trim() || isLoading"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                  </svg>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Floating Button -->
    <button
      @click="isOpen = !isOpen"
      class="w-12 h-12 rounded-full bg-accent-gold text-bg-deep flex items-center justify-center shadow-glow-gold transition-all duration-700 ease-spring hover:shadow-glow-gold-lg active:scale-95"
      :aria-label="isOpen ? 'Close chat' : 'Open chat with Ali'"
    >
      <svg v-if="!isOpen" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
      </svg>
      <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>
</template>

<script setup>
import { ref, nextTick, watch } from 'vue'
import { useChatbot } from '@/composables/useChatbot'

const { messages, isLoading, sendMessage } = useChatbot()
const isOpen = ref(false)
const inputText = ref('')
const messagesRef = ref(null)

const suggestedQuestions = [
  'What AI skills does Ali have?',
  'Tell me about Ali\'s projects',
  'Can Ali build automation for my business?'
]

function handleSend() {
  if (!inputText.value.trim()) return
  sendMessage(inputText.value)
  inputText.value = ''
}

function askQuestion(q) {
  sendMessage(q)
}

watch(() => messages.value.length, async () => {
  await nextTick()
  if (messagesRef.value) {
    messagesRef.value.scrollTop = messagesRef.value.scrollHeight
  }
})
</script>
