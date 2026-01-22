<template>
    <div class="dashboard-main-body">
        <Breadcrumb title="Users" :breadcrumbs="[
            { name: 'Users' }
        ]" />

        <UsersTable />
    </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import UsersTable from '@/components/Table/UsersTable.vue'

export default {
  name: "UsersList",
  components: {
    Breadcrumb,
    UsersTable,
  },
}
</script>