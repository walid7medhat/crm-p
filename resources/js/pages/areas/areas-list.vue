<template>
    <div class="dashboard-main-body">
       
        <AreaTable />
    </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import AreaTable from '@/components/Table/AreasTable.vue'

export default {
  name: "AreasList",
  components: {
    Breadcrumb,
    AreaTable,
  },
}
</script>