<template>
  <div class="roi-calc__sec">
    <div class="roi-calc__sec-row">
      <span class="roi-calc__sec-bar" />
      <span class="roi-calc__sec-title" v-html="title" />
    </div>
    <div class="roi-calc__sec-hr" />
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
})
</script>
