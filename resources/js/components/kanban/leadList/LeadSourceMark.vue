<template>
    <span
        v-if="mark"
        class="lead-source-mark"
        :class="mark.badge ? 'lead-source-mark--badge' : 'lead-source-mark--icon'"
        :style="mark.bg ? { background: mark.bg } : null"
        :title="mark.title"
    >
        <iconify-icon v-if="mark.icon" :icon="mark.icon" width="16" height="16" />
        <span v-else>{{ mark.badge }}</span>
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { leadSourceMark } from './leadSourceMark.js'

const props = defineProps({
    source: { type: String, default: '' },
})

const mark = computed(() => leadSourceMark(props.source))
</script>

<style scoped>
.lead-source-mark {
    width: 20px;
    height: 20px;
    border-radius: 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    line-height: 1;
    color: #fff;
}

.lead-source-mark--icon:not([style]) {
    background: transparent;
    width: 20px;
    height: 20px;
}

.lead-source-mark--badge {
    font-size: 8px;
    font-weight: 800;
    letter-spacing: -0.2px;
}

.lead-source-mark :deep(svg) {
    width: 16px;
    height: 16px;
}
</style>
