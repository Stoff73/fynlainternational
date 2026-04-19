<template>
  <div
    v-if="combinedThresholdBreached"
    class="card p-4 bg-violet-50 border border-violet-200"
  >
    <div class="font-bold text-violet-700">
      South African Reserve Bank (SARB) special approval required
    </div>
    <p class="text-sm text-horizon-600 mt-1">
      You've moved {{ formatZARMinor(consumed.totalMinor) }} offshore in {{ calendarYear }} — above the South African Reserve Bank combined threshold of {{ formatZARMinor(sarbThresholdMinor) }}. Further transfers this calendar year require special approval through your authorised dealer.
    </p>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaCombinedThresholdBanner',
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapGetters('zaExchangeControl', ['consumed', 'sarbThresholdMinor', 'calendarYear']),
    combinedThresholdBreached() {
      return this.sarbThresholdMinor > 0 && this.consumed.totalMinor > this.sarbThresholdMinor;
    },
  },
};
</script>
