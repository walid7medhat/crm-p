<template>
    <div class="match-ring" :class="`match-ring--${size}`" :aria-label="`${score}% match`">
        <svg :viewBox="`0 0 ${box} ${box}`" aria-hidden="true">
            <circle class="match-ring__track" :cx="box / 2" :cy="box / 2" :r="radius" fill="none" :stroke-width="stroke" />
            <circle
                class="match-ring__fill"
                :cx="box / 2"
                :cy="box / 2"
                :r="radius"
                fill="none"
                :stroke-width="stroke"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="offset"
            />
        </svg>
        <span class="match-ring__value">
            {{ score }}%
            <small v-if="size !== 'sm'">match</small>
        </span>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps({
    score: { type: Number, default: 0 },
    /** sm (lists), md (cards), lg (toasts) */
    size: { type: String, default: 'md' },
})

const box = computed(() => (props.size === 'sm' ? 36 : props.size === 'lg' ? 58 : 62))
const stroke = computed(() => (props.size === 'sm' ? 3.5 : 5))
const radius = computed(() => box.value / 2 - stroke.value)
const circumference = computed(() => 2 * Math.PI * radius.value)

// Starts empty and fills to the score, so each new card "lands" its number.
const shown = ref(0)
const offset = computed(() => circumference.value * (1 - shown.value / 100))

function fill() {
    shown.value = 0
    requestAnimationFrame(() => requestAnimationFrame(() => { shown.value = props.score }))
}
onMounted(fill)
watch(() => props.score, fill)
</script>

<style scoped>
.match-ring {
    position: relative;
    flex: none;
    display: grid;
    place-items: center;
}
.match-ring svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}
.match-ring--sm { width: 36px; height: 36px; }
.match-ring--md { width: 62px; height: 62px; }
.match-ring--lg { width: 58px; height: 58px; }

.match-ring__track { stroke: var(--match-ring-track, rgba(255, 255, 255, 0.14)); }
.match-ring__fill {
    stroke: var(--match-ring-fill, var(--color-primary-bright, #8f5aa3));
    stroke-linecap: round;
    transition: stroke-dashoffset 0.9s ease-out;
}
.match-ring__value {
    position: absolute;
    inset: 0;
    display: grid;
    place-content: center;
    text-align: center;
    font-weight: 800;
    line-height: 1;
    color: var(--match-ring-text, #ede4ff);
    font-size: 15px;
}
.match-ring--sm .match-ring__value { font-size: 10px; }
.match-ring__value small {
    display: block;
    margin-top: 2px;
    font-size: 8.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.75;
}

@media (prefers-reduced-motion: reduce) {
    .match-ring__fill { transition: none; }
}
</style>
