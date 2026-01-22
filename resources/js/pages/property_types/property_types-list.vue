<template>
    <div class="dashboard-main-body">
     

        <PropertyTypesTable />
    </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import PropertyTypesTable from '@/components/Table/PropertyTypesTable.vue'

export default {
  name: "PropertyTypeList",
  components: {
    Breadcrumb,
    PropertyTypesTable,
  },
}
</script>