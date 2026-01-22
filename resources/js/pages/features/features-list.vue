<template>
    <div class="dashboard-main-body">
      

        <FeaturesTable />
    </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import FeaturesTable from '@/components/Table/FeaturesTable.vue'

export default {
  name: "FeatursList",
  components: {
    Breadcrumb,
    FeaturesTable,
  },
}
</script>