<template>
  <button
    v-if="visible"
    type="button"
    class="voice-search-btn"
    :class="{
      'voice-search-btn--mobile': variant === 'mobile',
      'voice-search-btn--active': active,
    }"
    :title="title"
    :aria-label="title"
    @click.stop="$emit('click')"
  >
    <i class="ri-mic-line" aria-hidden="true"></i>
  </button>
</template>

<script setup>
defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  active: {
    type: Boolean,
    default: false,
  },
  variant: {
    type: String,
    default: 'desktop', // desktop | mobile
  },
  title: {
    type: String,
    default: 'Voice search',
  },
});

defineEmits(['click']);
</script>

<style scoped>
.voice-search-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #733e87;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
  font-size: 16px;
  padding: 0;
}

.voice-search-btn:hover {
  background: #f5eef8;
  box-shadow: 0 4px 12px rgba(115, 62, 135, 0.12);
}

.voice-search-btn--active {
  background: #733e87;
  color: #fff;
  border-color: #733e87;
}

.voice-search-btn--mobile {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  font-size: 18px;
  background: #f8fafc;
}
</style>
