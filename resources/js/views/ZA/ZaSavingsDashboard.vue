<template>
  <AppLayout>
    <div class="module-gradient py-2 sm:py-6">
      <ModuleStatusBar />
      <div class="max-w-7xl mx-auto space-y-8 px-4 py-6">
        <TfsaDashboard />
        <ZaEmergencyFundGauge />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';
import TfsaDashboard from '@za/components/Savings/TfsaDashboard.vue';
import ZaEmergencyFundGauge from '@za/components/Savings/ZaEmergencyFundGauge.vue';

export default {
  name: 'ZaSavingsDashboard',
  components: { AppLayout, ModuleStatusBar, TfsaDashboard, ZaEmergencyFundGauge },
};
</script>
