<template>
    <div class="dashboard-main-body">

        <Breadcrumb title="Input Form" :breadcrumbs="[
            { name: 'Input Form' }
        ]" />

        <InputFrom />
    </div>

</template>
<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import InputFrom from '@/components/Table/PropertyForm.vue'

export default {
    name: "PropertyForm",
    components: {
        Breadcrumb,
        InputFrom,
    },
}
</script>