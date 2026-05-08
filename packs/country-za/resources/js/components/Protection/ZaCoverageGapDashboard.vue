<template>
  <section>
    <h2 class="text-xl font-bold text-horizon-500 mb-4">Coverage gap analysis</h2>
    <div v-if="!categories" class="text-horizon-300 py-12 text-center">Loading…</div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <template v-for="cat in categories" :key="cat.category">
        <ZaMissingInputsEmptyState v-if="cat.missing_inputs.length" :category="cat" />
        <ZaCoverageGaugeCard v-else :category="cat" />
      </template>
    </div>
    <div v-if="inputs" class="mt-6 text-xs text-horizon-300">
      Inputs used: annual income {{ formatZAR(inputs.annual_income / 100) }}, outstanding debts {{ formatZAR(inputs.outstanding_debts / 100) }}, dependants {{ inputs.dependants }}.
    </div>
  </section>
</template>

<script>
import { mapState } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';
import ZaCoverageGaugeCard from './ZaCoverageGaugeCard.vue';
import ZaMissingInputsEmptyState from './ZaMissingInputsEmptyState.vue';

export default {
  name: 'ZaCoverageGapDashboard',
  components: { ZaCoverageGaugeCard, ZaMissingInputsEmptyState },
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapState('zaProtection', ['coverageGap']),
    categories() { return this.coverageGap?.categories || null; },
    inputs() { return this.coverageGap?.inputs || null; },
  },
};
</script>
