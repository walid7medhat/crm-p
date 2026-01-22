<template>
    <div class="dashboard-main-body">
     

        <ProjectTable />
    </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import ProjectTable from '@/components/Table/ProjectTable.vue'

export default {
  name: "OwnersList",
  components: {
    Breadcrumb,
    ProjectTable,
  },
}
</script>