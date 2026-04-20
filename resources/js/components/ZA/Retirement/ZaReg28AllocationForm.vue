<template>
  <section class="bg-white rounded-lg shadow-sm border border-horizon-100 p-6">
    <header class="mb-4">
      <h3 class="text-lg font-bold text-horizon-900">Regulation 28 asset-class look-through</h3>
      <p class="text-sm text-horizon-500 mt-1">
        Enter each asset class as a percentage (0–100). The seven asset classes must sum to 100%.
        Single-entity exposure is the largest single share of any one issuer in your portfolio.
      </p>
    </header>

    <form class="space-y-4" @submit.prevent="handleCheck">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div v-for="key in assetClassKeys" :key="key">
          <label class="block text-xs font-semibold text-horizon-700 mb-1">{{ labels[key] }} (%)</label>
          <input v-model.number="form[key]" type="number" min="0" max="100" step="0.1" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 border-t border-horizon-100 pt-4">
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Single-entity exposure (%)</label>
          <input v-model.number="form.single_entity" type="number" min="0" max="100" step="0.1" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" />
          <p class="text-xs text-horizon-500 mt-1">Largest single issuer exposure.</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Tax year</label>
          <input v-model="form.tax_year" type="text" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" />
        </div>
      </div>

      <div class="flex items-center justify-between bg-eggshell-100 border border-horizon-100 rounded-md p-3">
        <span class="text-sm font-semibold text-horizon-700">Asset-class allocation</span>
        <span
          class="text-sm font-bold tabular-nums"
          :class="sumsTo100 ? 'text-spring-600' : 'text-violet-600'"
          data-testid="za-reg28-sum-indicator"
        >
          {{ classSum.toFixed(1) }}% / 100%
        </span>
      </div>

      <div class="flex gap-3">
        <button
          type="submit"
          class="px-4 py-2 bg-raspberry-500 hover:bg-raspberry-600 text-white rounded-md text-sm font-semibold disabled:bg-horizon-300 disabled:cursor-not-allowed"
          :disabled="!sumsTo100 || loading"
        >
          {{ loading ? 'Checking…' : 'Check compliance' }}
        </button>
        <button
          type="button"
          class="px-4 py-2 bg-horizon-200 hover:bg-horizon-300 text-horizon-900 rounded-md text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="!result || savingSnapshot"
          v-preview-disabled="'add'"
          @click="handleSaveSnapshot"
        >
          {{ savingSnapshot ? 'Saving…' : 'Save as snapshot' }}
        </button>
      </div>
    </form>

    <ZaReg28ComplianceCard v-if="result" :result="result" class="mt-6" />
    <p v-if="error" class="mt-4 text-sm text-violet-800">{{ error }}</p>
  </section>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import ZaReg28ComplianceCard from './ZaReg28ComplianceCard.vue';

const LABELS = {
  offshore: 'Offshore',
  equity: 'Equity',
  property: 'Property',
  private_equity: 'Private equity',
  commodities: 'Commodities',
  hedge_funds: 'Hedge funds',
  other: 'Other',
};

export default {
  name: 'ZaReg28AllocationForm',
  components: { ZaReg28ComplianceCard },
  data() {
    return {
      form: {
        offshore: 0,
        equity: 0,
        property: 0,
        private_equity: 0,
        commodities: 0,
        hedge_funds: 0,
        other: 0,
        single_entity: 0,
        tax_year: '2026/27',
      },
      labels: LABELS,
      assetClassKeys: Object.keys(LABELS),
      loading: false,
      savingSnapshot: false,
      error: null,
    };
  },
  computed: {
    ...mapState('zaRetirement', ['reg28CheckResult']),
    result() { return this.reg28CheckResult; },
    classSum() {
      return this.assetClassKeys.reduce((a, k) => a + (Number(this.form[k]) || 0), 0);
    },
    sumsTo100() {
      return Math.abs(this.classSum - 100) < 0.01;
    },
    payload() {
      const alloc = {};
      this.assetClassKeys.forEach((k) => { alloc[k] = Number(this.form[k]) || 0; });
      alloc.single_entity = Number(this.form.single_entity) || 0;
      return { tax_year: this.form.tax_year, allocation: alloc };
    },
  },
  methods: {
    ...mapActions('zaRetirement', ['checkReg28', 'storeReg28Snapshot', 'fetchReg28Snapshots']),
    async handleCheck() {
      this.loading = true;
      this.error = null;
      try {
        await this.checkReg28(this.payload);
      } catch (e) {
        this.error = e?.response?.data?.message || 'Unable to run check.';
      } finally {
        this.loading = false;
      }
    },
    async handleSaveSnapshot() {
      this.savingSnapshot = true;
      this.error = null;
      try {
        await this.storeReg28Snapshot(this.payload);
        await this.fetchReg28Snapshots({});
      } catch (e) {
        this.error = e?.response?.data?.message || 'Unable to save snapshot.';
      } finally {
        this.savingSnapshot = false;
      }
    },
  },
};
</script>
