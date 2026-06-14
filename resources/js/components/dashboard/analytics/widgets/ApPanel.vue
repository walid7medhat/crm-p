<template>
    <section class="ap-panel" :class="tint ? `ap-panel--tint-${tint}` : ''">
      <header v-if="title || $slots.actions" class="ap-panel__head">
        <div>
          <p v-if="title" class="ap-panel__title">{{ title }}</p>
          <p v-if="subtitle" class="ap-panel__sub">{{ subtitle }}</p>
        </div>
        <span v-if="tag" class="ap-panel__tag">{{ tag }}</span>
        <slot name="actions" />
      </header>
      <div class="ap-panel__body">
        <slot />
      </div>
    </section>
  </template>
  
  <script setup>
  defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    tag: { type: String, default: '' },
    tint: { type: String, default: '' },
  })
  </script>