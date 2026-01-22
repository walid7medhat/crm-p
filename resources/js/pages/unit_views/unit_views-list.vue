<template>
    <div class="dashboard-main-body">
      

        <UnitViewsTable />
    </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import UnitViewsTable from '@/components/Table/UnitViewsTable.vue'

export default {
  name: "UnitViewList",
  components: {
    Breadcrumb,
    UnitViewsTable,
  },
}
</script>