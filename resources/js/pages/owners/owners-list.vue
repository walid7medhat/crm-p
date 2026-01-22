<template>
    <div class="dashboard-main-body">
     

        <OwnersTable />
    </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import OwnersTable from '@/components/Table/OwnersTable.vue'

export default {
  name: "OwnersList",
  components: {
    Breadcrumb,
    OwnersTable,
  },
}
</script>