<template>
  <section class="ex-panel">
    <header v-if="title || $slots.actions" class="ex-panel__head">
      <div>
        <span v-if="title" class="ex-panel__title">{{ title }}</span>
        <span v-if="subtitle" class="ex-panel__sub">{{ subtitle }}</span>
      </div>
      <slot name="actions" />
    </header>
    <div class="ex-panel__body">
      <slot />
    </div>
  </section>
</template>

<script setup>
defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})
</script>
