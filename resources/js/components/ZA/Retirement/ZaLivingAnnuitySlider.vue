<template>
  <section class="bg-white rounded-lg shadow-sm border border-horizon-100 p-6">
    <header class="mb-4">
      <h3 class="text-lg font-bold text-horizon-900">Living annuity drawdown</h3>
      <p class="text-sm text-horizon-500 mt-1">
        Model drawdown between 2.5% and 17.5% of capital per Regulation 39. Income is taxed at your marginal rate.
      </p>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
      <div>
        <label class="block text-xs font-semibold text-horizon-700 mb-1">Capital (R)</label>
        <input v-model.number="form.capital" type="number" min="0" step="1000" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" />
      </div>
      <div>
        <label class="block text-xs font-semibold text-horizon-700 mb-1">Your age</label>
        <input v-model.number="form.age" type="number" min="55" max="125" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" />
      </div>
      <div>
        <label class="block text-xs font-semibold text-horizon-700 mb-1">Tax year</label>
        <input v-model="form.tax_year" type="text" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" />
      </div>
    </div>

    <div class="mb-6">
      <div class="flex justify-between items-baseline mb-2">
        <label class="text-sm font-semibold text-horizon-700">Drawdown rate</label>
        <span class="text-2xl font-bold text-raspberry-500 tabular-nums">{{ drawdownPct.toFixed(1) }}%</span>
      </div>
      <input
        v-model.number="form.drawdown_bps"
        type="range"
        min="250"
        max="1750"
        step="50"
        class="w-full accent-raspberry-500"
        aria-label="Drawdown rate percent"
        @input="debouncedQuote"
      />
      <div class="flex justify-between text-xs text-horizon-500 mt-1">
        <span>2.5% min</span>
        <span>17.5% max</span>
      </div>
    </div>

    <div v-if="result" class="bg-eggshell-100 border border-horizon-100 rounded-md p-4 space-y-3" data-testid="za-living-annuity-result">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Gross monthly</p>
          <p class="text-xl font-bold text-horizon-900 tabular-nums">{{ formatZARMinor(result.monthly_income_minor) }}</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Net monthly (after tax)</p>
          <p class="text-xl font-bold text-spring-600 tabular-nums">{{ formatZARMinor(result.net_monthly_income_minor) }}</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Marginal rate</p>
          <p class="text-xl font-bold text-horizon-900 tabular-nums">{{ result.marginal_rate.toFixed(1) }}%</p>
        </div>
      </div>
      <aside v-if="drawdownPct >= 12" class="bg-violet-50 border border-violet-300 rounded-md p-3">
        <p class="text-sm text-violet-800">
          <strong>Warning:</strong> Drawdown at {{ drawdownPct.toFixed(1) }}% carries significant capital depletion risk over a 20–30 year retirement.
        </p>
      </aside>
    </div>

    <p v-else-if="!loading" class="text-sm text-horizon-500">
      Enter capital and adjust the slider to see your projected income.
    </p>

    <p v-if="error" class="mt-4 text-sm text-violet-800">{{ error }}</p>
  </section>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaLivingAnnuitySlider',
  mixins: [zaCurrencyMixin],
  data() {
    return {
      form: {
        capital: 2000000,
        age: 65,
        tax_year: '2026/27',
        drawdown_bps: 500,
      },
      loading: false,
      error: null,
      debounceTimer: null,
    };
  },
  computed: {
    ...mapState('zaRetirement', {
      quote: (s) => s.annuityQuotes.living,
    }),
    result() { return this.quote; },
    drawdownPct() { return this.form.drawdown_bps / 100; },
  },
  watch: {
    'form.capital'() { this.debouncedQuote(); },
    'form.age'() { this.debouncedQuote(); },
    'form.tax_year'() { this.debouncedQuote(); },
  },
  mounted() {
    this.debouncedQuote();
  },
  beforeUnmount() {
    if (this.debounceTimer) clearTimeout(this.debounceTimer);
  },
  methods: {
    ...mapActions('zaRetirement', ['quoteLivingAnnuity']),
    debouncedQuote() {
      if (this.debounceTimer) clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => this.runQuote(), 300);
    },
    async runQuote() {
      if (!this.form.capital || this.form.capital <= 0) return;
      this.error = null;
      this.loading = true;
      try {
        await this.quoteLivingAnnuity({
          capital_minor: Math.round(this.form.capital * 100),
          drawdown_rate_bps: this.form.drawdown_bps,
          age: this.form.age,
          tax_year: this.form.tax_year,
        });
      } catch (e) {
        this.error = e?.response?.data?.message || 'Unable to quote living annuity.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
