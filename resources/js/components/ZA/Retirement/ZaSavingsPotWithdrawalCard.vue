<template>
  <section class="bg-white rounded-lg shadow-sm border border-horizon-100 p-6">
    <header class="mb-4">
      <h3 class="text-lg font-bold text-horizon-900">Savings-Pot withdrawal simulator</h3>
      <p class="text-sm text-horizon-500 mt-1">
        Preview the marginal-tax impact of withdrawing from your Savings Pot. Minimum R 2 000,00 per SARS regulation.
      </p>
    </header>

    <form class="space-y-4" @submit.prevent="handleSimulate">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Fund</label>
          <select v-model="form.fund_holding_id" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required>
            <option value="">Select a fund…</option>
            <option v-for="f in funds" :key="f.id" :value="f.id">{{ f.fund_type_label }} — {{ f.provider }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Amount (R)</label>
          <input v-model.number="form.amount" type="number" min="20" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
          <p class="text-xs text-horizon-500 mt-1">Minimum R 2 000,00.</p>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Current annual income (R)</label>
          <input v-model.number="form.income" type="number" min="0" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
        </div>
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Your age</label>
          <input v-model.number="form.age" type="number" min="18" max="125" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
        </div>
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Tax year</label>
          <input v-model="form.tax_year" type="text" placeholder="2026/27" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
        </div>
      </div>

      <div class="flex gap-3">
        <button type="submit" class="px-4 py-2 bg-horizon-200 hover:bg-horizon-300 text-horizon-900 rounded-md text-sm font-semibold" :disabled="simulating">
          {{ simulating ? 'Simulating…' : 'Simulate' }}
        </button>
        <button
          v-if="result && !error"
          type="button"
          class="px-4 py-2 bg-raspberry-500 hover:bg-raspberry-600 text-white rounded-md text-sm font-semibold"
          :disabled="confirming"
          v-preview-disabled="'delete'"
          @click="handleConfirm"
        >
          {{ confirming ? 'Processing…' : 'Confirm withdrawal' }}
        </button>
      </div>
    </form>

    <div v-if="error" class="mt-4 bg-violet-50 border border-violet-300 rounded-md p-4" data-testid="za-savings-pot-error">
      <p class="text-sm text-violet-800">{{ error }}</p>
    </div>

    <div v-else-if="result" class="mt-6 bg-eggshell-100 border border-horizon-100 rounded-md p-4 space-y-3" data-testid="za-savings-pot-result">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Tax on withdrawal</p>
          <p class="text-xl font-bold text-raspberry-500 tabular-nums">{{ formatZARMinor(result.tax_delta_minor) }}</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Net to you</p>
          <p class="text-xl font-bold text-spring-600 tabular-nums">{{ formatZARMinor(result.net_received_minor) }}</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Marginal rate</p>
          <p class="text-xl font-bold text-horizon-900 tabular-nums">{{ (result.marginal_rate).toFixed(1) }}%</p>
        </div>
      </div>
      <aside v-if="result.crosses_bracket" class="bg-violet-50 border border-violet-300 rounded-md p-3">
        <p class="text-sm text-violet-800">
          <strong>Warning:</strong> This withdrawal would push you into a higher tax bracket.
        </p>
      </aside>
    </div>
  </section>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';
import { toMinorZAR } from '@/utils/zaCurrency';

export default {
  name: 'ZaSavingsPotWithdrawalCard',
  mixins: [zaCurrencyMixin],
  data() {
    return {
      form: {
        fund_holding_id: '',
        amount: 0,
        income: 0,
        age: 40,
        tax_year: '2026/27',
      },
      simulating: false,
      confirming: false,
      error: null,
    };
  },
  computed: {
    ...mapState('zaRetirement', ['funds', 'simulatorResult']),
    result() { return this.simulatorResult; },
  },
  methods: {
    ...mapActions('zaRetirement', ['simulateSavingsPotWithdrawal', 'withdrawSavingsPot', 'fetchDashboard']),
    payload() {
      return {
        fund_holding_id: this.form.fund_holding_id,
        amount_minor: toMinorZAR(this.form.amount),
        current_annual_income_minor: toMinorZAR(this.form.income),
        age: this.form.age,
        tax_year: this.form.tax_year,
      };
    },
    async handleSimulate() {
      this.error = null;
      this.simulating = true;
      try {
        await this.simulateSavingsPotWithdrawal(this.payload());
      } catch (e) {
        this.error = e?.response?.data?.message || 'Unable to simulate.';
      } finally {
        this.simulating = false;
      }
    },
    async handleConfirm() {
      this.confirming = true;
      try {
        await this.withdrawSavingsPot(this.payload());
        await this.fetchDashboard({});
      } catch (e) {
        this.error = e?.response?.data?.message || 'Unable to confirm withdrawal.';
      } finally {
        this.confirming = false;
      }
    },
  },
};
</script>
