<template>
    <span v-if="reasons.length" class="match-by">
        <span v-for="r in reasons" :key="r" class="match-by__chip" :class="`match-by__chip--${r}`">
            <iconify-icon :icon="r === 'email' ? 'lucide:mail' : 'lucide:phone'" />
            Same {{ r }}
        </span>
    </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    matchBy: { type: Array, default: () => [] },
})

const reasons = computed(() => (Array.isArray(props.matchBy) ? props.matchBy : []).filter((r) => r === 'phone' || r === 'email'))
</script>

<style scoped>
.match-by {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
}
.match-by__chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    line-height: 16px;
    white-space: nowrap;
}
.match-by__chip iconify-icon {
    font-size: 11px;
}
.match-by__chip--phone {
    color: var(--color-primary);
    background: rgba(var(--color-primary-rgb), 0.12);
}
.match-by__chip--email {
    color: #0369a1;
    background: #e0f2fe;
}
</style>
