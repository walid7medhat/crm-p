<template>
    <div class="match-photo">
        <img v-if="src && !broken" :src="src" alt="" loading="lazy" draggable="false" @error="broken = true" />
        <canvas v-else ref="canvasEl" aria-hidden="true"></canvas>
    </div>
</template>

<script setup>
import { nextTick, onMounted, ref, watch } from 'vue'

/**
 * Listing photo, or a drawn skyline when the listing has no image yet. The skyline
 * is seeded by the listing id, so the same unit always gets the same picture.
 */
const props = defineProps({
    src: { type: String, default: null },
    seed: { type: Number, default: 1 },
    sea: { type: Boolean, default: false },
})

const broken = ref(false)
const canvasEl = ref(null)

const SKIES = [
    ['#c58fd6', '#5c3270'],
    ['#7cc4e8', '#d9c8f0'],
    ['#3b2a63', '#b07cc6'],
    ['#9fd3c7', '#e6dcf2'],
    ['#d6a6c9', '#b98fd0'],
    ['#b8a6e0', '#e7b8d8'],
]

function rng(seed) {
    let s = seed | 0
    return () => {
        s = (s + 0x6d2b79f5) | 0
        let t = Math.imul(s ^ (s >>> 15), 1 | s)
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296
    }
}

function draw() {
    const cv = canvasEl.value
    if (!cv) return
    const w = cv.clientWidth || 320
    const h = cv.clientHeight || 200
    const dpr = Math.min(2, window.devicePixelRatio || 1)
    cv.width = w * dpr
    cv.height = h * dpr
    const c = cv.getContext('2d')
    c.scale(dpr, dpr)
    const r = rng(props.seed * 7919)
    const [bottom, top] = SKIES[props.seed % SKIES.length]

    const sky = c.createLinearGradient(0, 0, 0, h)
    sky.addColorStop(0, top)
    sky.addColorStop(1, bottom)
    c.fillStyle = sky
    c.fillRect(0, 0, w, h)

    const sx = w * (0.2 + r() * 0.6)
    const glow = c.createRadialGradient(sx, h * 0.42, 0, sx, h * 0.42, h * 0.5)
    glow.addColorStop(0, 'rgba(255,240,250,.95)')
    glow.addColorStop(0.12, 'rgba(240,210,250,.6)')
    glow.addColorStop(1, 'rgba(240,210,250,0)')
    c.fillStyle = glow
    c.fillRect(0, 0, w, h)

    const ground = props.sea ? h * 0.72 : h * 0.86
    let x = -10
    while (x < w) {
        const bw = w * (0.06 + r() * 0.1)
        const bh = h * (0.25 + r() * 0.45)
        const shade = 30 + Math.floor(r() * 25)
        c.fillStyle = `rgb(${shade + 20},${shade},${shade + 30})`
        c.fillRect(x, ground - bh, bw, bh)
        for (let wy = ground - bh + 6; wy < ground - 6; wy += 7) {
            for (let wx = x + 4; wx < x + bw - 4; wx += 6) {
                if (r() > 0.62) {
                    c.fillStyle = r() > 0.5 ? 'rgba(233,213,255,.85)' : 'rgba(255,255,255,.55)'
                    c.fillRect(wx, wy, 2.5, 3.5)
                }
            }
        }
        x += bw + w * (r() * 0.02)
    }
    c.fillStyle = props.sea ? 'rgba(30,50,95,.95)' : 'rgba(25,15,30,.9)'
    c.fillRect(0, ground, w, h - ground)
}

onMounted(() => nextTick(draw))
watch(() => [props.src, props.seed, broken.value], () => nextTick(draw))
</script>

<style scoped>
.match-photo {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: #2a1832;
}
.match-photo img,
.match-photo canvas {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>
