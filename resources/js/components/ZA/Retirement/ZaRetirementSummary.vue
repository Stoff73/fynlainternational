<template>
  <section class="bg-white rounded-lg shadow-sm border border-horizon-100 p-6 mb-6">
    <header class="mb-4">
      <h2 class="text-xl font-bold text-horizon-900">
        Retirement <span v-if="taxYear" class="font-normal text-horizon-500">— Tax year {{ taxYear }}</span>
      </h2>
      <p class="text-sm text-horizon-500 mt-1">
        Total across all South African retirement funds (Retirement Annuity, Pension Fund, Provident Fund, Preservation Fund).
      </p>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div>
        <p class="text-xs uppercase tracking-wide text-horizon-500">Total balance</p>
        <p class="text-2xl font-bold text-horizon-900 mt-1">{{ formatZARMinor(totalBalanceMinor) }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-wide text-horizon-500">Section 11F annual cap</p>
        <p class="text-2xl font-bold text-horizon-900 mt-1">{{ formatZARMinor(annualAllowanceMinor) }}</p>
        <p class="text-xs text-horizon-500 mt-1">SARS absolute cap on deductible retirement contributions.</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-wide text-horizon-500">Funds recorded</p>
        <p class="text-2xl font-bold text-horizon-900 mt-1">{{ fundCount }}</p>
      </div>
    </div>
  </section>
</template>

<script>
import { mapState } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaRetirementSummary',
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapState('zaRetirement', ['taxYear', 'totalBalanceMinor', 'annualAllowanceMinor', 'fundCount']),
  },
};
</script>
