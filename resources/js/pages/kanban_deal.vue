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
import KanbanData from "@/components/kanban/kanban_deal.vue"

export default {
  name: "Kanban",
  components: {
    Breadcrumb,
    Icon,
    KanbanData,
  },
};
</script>
<style scoped>
/* Kanban page: transparent body so header/sidebar style shows through; text readable on background */
.dashboard-main-body {
    color: rgba(255, 255, 255, 0.95);
    background: transparent;
    margin: 4px 4px 6px 4px;
    /* Fill the dashboard-main-router flex column so list/kanban can size themselves.
       Without this, deals list mode (height:100% + min-height:0 + overflow:hidden)
       collapses to a blank page under the Primary/Secondary/Rental header. */
    flex: 1 1 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.dashboard-main-body :deep(.kanban-main-wrapper) {
    flex: 1 1 0;
    min-height: 0;
}
@media (max-width: 768px) {
    :deep(.breadcrumb) {
        display: none !important;
    }
}
</style>