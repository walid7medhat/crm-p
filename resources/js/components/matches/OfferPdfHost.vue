<template>
    <!-- Off-screen: BlogOne in offerOnly mode renders nothing; its offer generator draws its own slides. -->
    <div class="offer-pdf-host" aria-hidden="true">
        <BlogOne v-if="job" :key="job.id" ref="blogRef" :listing-id="job.listingId" offer-only />
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import BlogOne from '@/components/alllisting/PropertyDetails/BlogOne.vue'
import { offerPdfJobs } from '@/composables/useOfferPdf.js'

const blogRef = ref(null)
const job = computed(() => offerPdfJobs.value[0] || null)

async function run(current) {
    await nextTick()
    try {
        if (!blogRef.value?.buildOfferPdf) throw new Error("The offer generator didn't load.")
        current.resolve(await blogRef.value.buildOfferPdf({ clientName: current.clientName, onProgress: current.onProgress }))
    } catch (e) {
        current.reject(e)
    } finally {
        offerPdfJobs.value = offerPdfJobs.value.filter((j) => j.id !== current.id)
    }
}

watch(job, (current) => { if (current) run(current) }, { immediate: true })
</script>

<style scoped>
.offer-pdf-host {
    position: fixed;
    left: -10000px;
    top: 0;
    width: 1200px;
    height: 1px;
    overflow: hidden;
    pointer-events: none;
    opacity: 0;
}
</style>
