<template>
  <section class="bg-white rounded-lg shadow-sm border border-horizon-100 p-6">
    <header class="mb-4">
      <h3 class="text-lg font-bold text-horizon-900">Section 11F tax relief — what-if</h3>
      <p class="text-sm text-horizon-500 mt-1">
        Estimate how much income tax a retirement contribution would save. Section 11F caps the annual deduction at the SARS absolute cap.
      </p>
    </header>

    <form class="space-y-4" @submit.prevent="handleCalculate">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Contribution (R)</label>
          <input v-model.number="form.contribution" type="number" min="1" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
        </div>
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Gross income (R)</label>
          <input v-model.number="form.gross_income" type="number" min="0" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
        </div>
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Tax year</label>
          <input v-model="form.tax_year" type="text" placeholder="2026/27" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
        </div>
      </div>

      <button type="submit" class="px-4 py-2 bg-raspberry-500 hover:bg-raspberry-600 text-white rounded-md text-sm font-semibold" :disabled="loading">
        {{ loading ? 'Calculating…' : 'Calculate relief' }}
      </button>
    </form>

    <div v-if="result" class="mt-6 bg-eggshell-100 border border-horizon-100 rounded-md p-4" data-testid="za-tax-relief-result">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Relief amount</p>
          <p class="text-xl font-bold text-spring-600 tabular-nums">{{ formatZARMinor(result.relief_amount_minor) }}</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Effective relief rate</p>
          <p class="text-xl font-bold text-horizon-900 tabular-nums">{{ (result.relief_rate * 100).toFixed(1) }}%</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Net cost</p>
          <p class="text-xl font-bold text-horizon-900 tabular-nums">{{ formatZARMinor(result.net_cost_minor) }}</p>
        </div>
      </div>
    </div>

    <p v-else-if="!loading" class="mt-4 text-sm text-horizon-500">
      Enter a contribution to see how Section 11F reduces your taxable income.
    </p>
  </section>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaSection11fReliefCalculator',
  mixins: [zaCurrencyMixin],
  data() {
    return {
      form: {
        contribution: 0,
        gross_income: 0,
        tax_year: '2026/27',
      },
      loading: false,
    };
  },
  computed: {
    ...mapState('zaRetirement', ['taxReliefResult']),
    result() { return this.taxReliefResult; },
  },
  methods: {
    ...mapActions('zaRetirement', ['calculateTaxRelief']),
    async handleCalculate() {
      this.loading = true;
      try {
        await this.calculateTaxRelief({
          contribution_minor: Math.round((this.form.contribution || 0) * 100),
          gross_income_minor: Math.round((this.form.gross_income || 0) * 100),
          tax_year: this.form.tax_year,
        });
      } catch (e) {
        // Error state surfaced via Vuex
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
