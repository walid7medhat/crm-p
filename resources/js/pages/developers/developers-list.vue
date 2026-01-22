<template>
    <div class="dashboard-main-body">
        

        <DevelopersTable />
    </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import DevelopersTable from '@/components/Table/DevelopersTable.vue'

export default {
  name: "DevelopersList",
  components: {
    Breadcrumb,
    DevelopersTable,
  },
}
</script>