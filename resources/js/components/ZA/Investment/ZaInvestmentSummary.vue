<template>
  <section class="space-y-4">
    <header class="flex items-end justify-between">
      <div>
        <h1 class="text-3xl font-black text-horizon-700">Investments</h1>
        <p class="text-sm text-horizon-500 mt-1">
          Tax year {{ taxYear || '2026/27' }} — Tax-Free Savings Account, Discretionary, and Endowment wrappers.
        </p>
      </div>
      <button
        type="button"
        class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg transition-colors"
        @click="$emit('add-account')"
      >
        Add account
      </button>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div v-for="w in wrappers" :key="w.code" class="card p-6">
        <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">{{ w.name }}</div>
        <div class="text-2xl font-black text-horizon-700 mt-2">
          {{ formatAllowance(w.code) }}
        </div>
        <p class="mt-2 text-xs text-horizon-500">{{ w.tax_treatment }}</p>
      </div>
    </div>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaInvestmentSummary',
  mixins: [zaCurrencyMixin],
  emits: ['add-account'],
  computed: {
    ...mapGetters('zaInvestment', ['wrappers', 'allowances', 'taxYear']),
  },
  methods: {
    formatAllowance(code) {
      const a = this.allowances?.[code];
      if (a === undefined || a === null) return '—';
      const limit = typeof a === 'object' ? (a.annual_limit ?? a) : a;
      if (limit > 1_000_000_000_000) return 'No cap';
      return `${this.formatZARMinor(limit)} annual cap`;
    },
  },
};
</script>
