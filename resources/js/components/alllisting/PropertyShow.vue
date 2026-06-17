<template>
    <div class="dashboard-main-body property-show-page" :class="{ 'property-show-page--mobile': isMobile }">
        <Breadcrumb
            v-if="!isMobile"
            title="Property Details"
            :breadcrumbs="[{ name: 'Property Details' }]"
        />

        <div class="row">
            <BlogOne />
        </div>
    </div>
</template>
<script>
import { ref, onMounted, onUnmounted } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import BlogOne from '@/components/alllisting/PropertyDetails/BlogOne.vue'

const MOBILE_MAX = 768

export default {
    name: "PropertyDetails",
    components: {
        Breadcrumb,
        BlogOne,
    },
    setup() {
        const isMobile = ref(typeof window !== 'undefined' && window.innerWidth <= MOBILE_MAX)

        const onResize = () => {
            isMobile.value = window.innerWidth <= MOBILE_MAX
        }

        onMounted(() => window.addEventListener('resize', onResize))
        onUnmounted(() => window.removeEventListener('resize', onResize))

        return { isMobile }
    },
}
</script>
