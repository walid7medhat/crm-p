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
}

@media (max-width: 768px) {
    :deep(.breadcrumb) {
        display: none !important;
    }
}
</style>