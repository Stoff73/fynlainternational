<template>
  <section class="card p-6">
    <header class="mb-2">
      <h2 class="text-xl font-bold text-horizon-700">Year-to-date Capital Gains Tax projection</h2>
      <p class="text-xs text-horizon-400 mt-1">
        Computed from your realised disposals in tax year {{ taxYear || '2026/27' }}.
      </p>
    </header>
    <div v-if="!hasDisposals" class="py-6 text-center text-horizon-400">
      Realised disposals will appear here once you record them. Use the disposal action on the holdings list to start tracking.
    </div>
    <div v-else class="text-2xl font-black text-horizon-700">
      {{ formatZARMinor(projectedTaxMinor) }}
    </div>
    <p v-if="hasDisposals" class="text-xs text-horizon-400 mt-1">
      Indicative — final Capital Gains Tax depends on full income and age at tax-year end.
    </p>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaCgtProjectionPanel',
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapGetters('zaInvestment', ['taxYear']),
    // v1 scope: labelled empty-state placeholder (PRD amendment A10).
    // Functional projection from realised disposals is a v1.1 follow-up.
    hasDisposals() {
      return false;
    },
    projectedTaxMinor() {
      return 0;
    },
  },
};
</script>
