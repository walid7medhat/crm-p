<template>
  <div class="dashboard-main-body">
    <Breadcrumb title="Listings Overview" :breadcrumbs="[{ name: 'Listings' }, { name: 'Overview' }]" />

    <AiWidgets />

    <div class="row gy-4 mt-1">
      <SalesStatistic />
      <propertyType />
      <UserOverview />
      <LatestUser />
      <TopPerformer />
    </div>
  </div>
</template>

<script>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import ApexCharts from 'vue3-apexcharts';
import { Icon } from '@iconify/vue';
import AiWidgets from '@/components/dashboard/Ai/AiWidgets.vue';
import SalesStatistic from '@/components/dashboard/Ai/SalesStatistic.vue';
import UserOverview from '@/components/dashboard/Ai/UserOverview.vue';
import LatestUser from '@/components/dashboard/Ai/LatestUser.vue';
import propertyType from '@/components/dashboard/Ai/propertyType.vue';
import TopPerformer from '@/components/dashboard/Ai/TopPerformer.vue';

export default {
  name: 'ListingsOverview',
  components: {
    Breadcrumb,
    apexchart: ApexCharts,
    Icon,
    AiWidgets,
    LatestUser,
    SalesStatistic,
    UserOverview,
    TopPerformer,
    propertyType,
  },
};
</script>
