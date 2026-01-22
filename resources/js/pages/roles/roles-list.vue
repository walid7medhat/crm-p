<template>
    <div class="dashboard-main-body">
     

        <RoleTable />
    </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import RoleTable from '@/components/Table/RolesTable.vue'

export default {
  name: "AreasList",
  components: {
    Breadcrumb,
    RoleTable,
  },
}
</script>