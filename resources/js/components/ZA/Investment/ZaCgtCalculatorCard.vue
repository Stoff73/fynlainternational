<template>
  <section class="card p-6">
    <header class="mb-4">
      <h2 class="text-xl font-bold text-horizon-700">Capital Gains Tax — what-if</h2>
      <p class="text-xs text-horizon-400 mt-1">
        Estimates SA Capital Gains Tax on a one-off disposal. The R40,000 annual exclusion applies to the discretionary path only.
      </p>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Wrapper</label>
        <select
          v-model="form.wrapper_code"
          class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
        >
          <option value="discretionary">Discretionary</option>
          <option value="endowment">Endowment</option>
          <option value="tfsa">Tax-Free Savings Account</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Realised gain (ZAR)</label>
        <input
          v-model.number="form.gain"
          type="number"
          step="0.01"
          min="0"
          class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
        />
      </div>

      <template v-if="form.wrapper_code === 'discretionary'">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Other taxable income (ZAR)</label>
          <input
            v-model.number="form.income"
            type="number"
            step="0.01"
            min="0"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Age</label>
          <input
            v-model.number="form.age"
            type="number"
            min="18"
            max="120"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>
      </template>
    </div>

    <button
      type="button"
      class="mt-4 bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2 rounded-lg disabled:opacity-50"
      :disabled="!canCalculate || calculating"
      @click="calculate"
    >
      {{ calculating ? 'Calculating…' : 'Calculate' }}
    </button>

    <div v-if="cgtScenario" class="mt-4 p-4 bg-savannah-100 rounded-lg">
      <div class="text-sm text-horizon-500">Estimated tax due</div>
      <div class="text-2xl font-black text-horizon-700">
        {{ formatZARMinor(cgtScenario.result?.tax_due_minor || 0) }}
      </div>
      <div v-if="cgtScenario.result?.exclusion_applied_minor !== undefined" class="text-xs text-horizon-400 mt-1">
        Exclusion applied: {{ formatZARMinor(cgtScenario.result.exclusion_applied_minor) }}
        <span v-if="cgtScenario.result?.marginal_rate">
          · marginal rate {{ Number(cgtScenario.result.marginal_rate).toFixed(1) }}%
        </span>
      </div>
      <div v-if="cgtScenario.result?.note" class="text-xs text-horizon-400 mt-1">
        {{ cgtScenario.result.note }}
      </div>
    </div>
  </section>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaCgtCalculatorCard',
  mixins: [zaCurrencyMixin],
  data() {
    return {
      form: {
        wrapper_code: 'discretionary',
        gain: null,
        income: null,
        age: 40,
      },
      calculating: false,
    };
  },
  computed: {
    ...mapGetters('zaInvestment', ['cgtScenario', 'taxYear']),
    canCalculate() {
      if (!this.form.gain) return false;
      if (this.form.wrapper_code === 'discretionary') {
        return this.form.income !== null && this.form.age >= 18;
      }
      return true;
    },
  },
  methods: {
    ...mapActions('zaInvestment', ['calculateCgt']),
    async calculate() {
      this.calculating = true;
      try {
        await this.calculateCgt({
          wrapper_code: this.form.wrapper_code,
          gain_minor: this.toMinorZAR(this.form.gain || 0),
          income_minor: this.toMinorZAR(this.form.income || 0),
          age: this.form.age,
          tax_year: this.taxYear || '2026/27',
        });
      } finally {
        this.calculating = false;
      }
    },
  },
};
</script>
