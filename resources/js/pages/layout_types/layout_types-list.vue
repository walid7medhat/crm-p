<template>
    <div class="dashboard-main-body">
      

        <LayoutTypeTable />
    </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import LayoutTypeTable from '@/components/Table/LayoutTypesTable.vue'

export default {
  name: "LayoutTypesList",
  components: {
    Breadcrumb,
    LayoutTypeTable,
  },
}
</script>