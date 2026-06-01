<template>
  <div class="listings-dashboard-ai">
    <Breadcrumb
      v-if="showBreadcrumb"
      title="Listings Overview"
      :breadcrumbs="[{ name: 'Listings' }, { name: 'Overview' }]"
    />

    <div class="ld-metrics">
      <AiWidgets />
    </div>

    <div class="ld-grid">
      <SalesStatistic class="ld-area-stats" />
      <UserOverview class="ld-area-requests" />
      <TopPerformer class="ld-area-agents" />
      <propertyType class="ld-area-property" />
      <LatestUser class="ld-area-table" />
    </div>
  </div>
</template>

<script setup>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import AiWidgets from '@/components/dashboard/Ai/AiWidgets.vue'
import SalesStatistic from '@/components/dashboard/Ai/SalesStatistic.vue'
import UserOverview from '@/components/dashboard/Ai/UserOverview.vue'
import LatestUser from '@/components/dashboard/Ai/LatestUser.vue'
import propertyType from '@/components/dashboard/Ai/propertyType.vue'
import TopPerformer from '@/components/dashboard/Ai/TopPerformer.vue'

defineProps({
  showBreadcrumb: { type: Boolean, default: false },
})
</script>
