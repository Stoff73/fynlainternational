<template>
  <AppLayout>
    <div class="max-w-6xl mx-auto space-y-8 py-6">
      <TfsaDashboard />
      <ZaEmergencyFundGauge />
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import TfsaDashboard from '@/components/ZA/Savings/TfsaDashboard.vue';
import ZaEmergencyFundGauge from '@/components/ZA/Savings/ZaEmergencyFundGauge.vue';

export default {
  name: 'ZaSavingsDashboard',
  components: { AppLayout, TfsaDashboard, ZaEmergencyFundGauge },
};
</script>
