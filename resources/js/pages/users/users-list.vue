<template>
    <div class="dashboard-main-body">

      

        <UserList />
    </div>
</template>
<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import UserList from '@/components/Users/UserList.vue'

export default {
    name: "UsersList",
    components: {
        Breadcrumb,
        UserList,
    },
}
</script>