<template>
  <div class="roi-calc__fld">
    <div v-if="label" class="roi-calc__lbl" v-html="label" />
    <div v-if="hint" class="roi-calc__hint">{{ hint }}</div>
    <div class="roi-calc__ig">
      <span v-if="prefix" class="roi-calc__pre">{{ prefix }}</span>
      <slot />
      <span v-if="suffix" class="roi-calc__suf">{{ suffix }}</span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  label: { type: String, default: '' },
  hint: { type: String, default: '' },
  prefix: { type: String, default: '' },
  suffix: { type: String, default: '' },
})
</script>
