<template>
    <div class="dashboard-main-body">
        <Breadcrumb title="Kanban" :breadcrumbs="[
            { name: 'Kanban' }
        ]" />
        
        <KanbanData />
    </div>

</template>
<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import { Icon } from "@iconify/vue"; 
import KanbanData from "@/components/kanban/Kanban.vue"

export default {
  name: "Kanban",
  components: {
    Breadcrumb,
    Icon,
    KanbanData,
  },
  mounted() {
    document.body.classList.add('kanban-page-hide-right-menu');
  },
  unmounted() {
    document.body.classList.remove('kanban-page-hide-right-menu');
  },
};
</script>

<style scoped>
/* Kanban page: transparent body so header/sidebar style shows through; text readable on background */
.dashboard-main-body {
    color: rgba(255, 255, 255, 0.95);
    background: transparent;
}

@media (max-width: 991px) {
    :global(body.kanban-page-hide-right-menu .navbar-header) {
        display: none !important;
    }

    :global(body.kanban-page-hide-right-menu .app-navbar-spacer) {
        display: none !important;
        height: 0 !important;
        min-height: 0 !important;
    }

    :global(body.kanban-page-hide-right-menu .navbar-header-right) {
        display: none !important;
    }

    :global(body.kanban-page-hide-right-menu .sidebar) {
        display: none !important;
    }
}
</style>