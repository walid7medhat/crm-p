<template>
  <div class="pf-dock" role="region" aria-label="Agent">
    <div class="pf-dock__card">
      <button type="button" class="pf-dock__who" @click="emit('profile')">
        <img
          :src="avatarFailed ? defaultAvatar : (agent.avatar || defaultAvatar)"
          :alt="agentName"
          @error="avatarFailed = true"
        />
        <span>
          <strong>{{ agentName }}</strong>
          <small>Property agent</small>
        </span>
      </button>
      <div class="pf-dock__actions">
        <button v-if="canChat" type="button" class="pf-dock__btn" @click="emit('chat')">
          <i class="ri-chat-3-line"></i>
          Chat
        </button>
        <button v-if="showActions" type="button" class="pf-dock__btn pf-dock__btn--action" @click="emit('actions')">
          Action
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  agent: { type: Object, required: true },
  canChat: { type: Boolean, default: false },
  showActions: { type: Boolean, default: true },
})

const emit = defineEmits(['chat', 'profile', 'actions'])

const defaultAvatar = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'
const avatarFailed = ref(false)

const agentName = computed(() => {
  const a = props.agent
  return a.name || [a.first_name, a.last_name].filter(Boolean).join(' ') || 'Agent'
})
</script>
