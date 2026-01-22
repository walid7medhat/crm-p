<template>
    <div class="dashboard-main-body">
        <Breadcrumb title="Property Details" :breadcrumbs="[
            { name: 'Property Details' }
        ]" />

        <div class="row">
            <BlogOne />
        </div>
    </div>
</template>
<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import BlogOne from '@/components/alllisting/PropertyDetails/BlogOne.vue'
export default {
    name: "PropertyDetails",
    components: {
        Breadcrumb,
        BlogOne,
    },
}
</script>