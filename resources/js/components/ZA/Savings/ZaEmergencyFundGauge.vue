<template>
  <div class="card p-6">
    <h2 class="text-xl font-bold text-horizon-700 mb-4">Emergency fund</h2>

    <form @submit.prevent="assess" class="space-y-4">
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Current balance (ZAR)</label>
        <input v-model.number="form.current_balance" type="number" step="0.01" min="0" required
               class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
      </div>
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Essential monthly expenditure (ZAR)</label>
        <input v-model.number="form.monthly" type="number" step="0.01" min="0" required
               class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Household earners</label>
          <select v-model.number="form.earners"
                  class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500">
            <option :value="1">1 (single earner)</option>
            <option :value="2">2 (dual earner)</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">UIF eligible</label>
          <select v-model="form.uif"
                  class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500">
            <option :value="true">Yes</option>
            <option :value="false">No</option>
          </select>
        </div>
      </div>
      <button type="submit"
              class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg">
        Assess adequacy
      </button>
    </form>

    <div v-if="result" class="mt-6 pt-6 border-t border-light-gray">
      <div class="flex items-end justify-between mb-3">
        <div>
          <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">Target</div>
          <div class="text-2xl font-black text-horizon-700">{{ formatZARMinor(result.target_minor) }}</div>
          <div class="text-xs text-horizon-400">{{ result.target_months }} months</div>
        </div>
        <div class="text-right">
          <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">Covered</div>
          <div class="text-2xl font-black" :class="statusColor">
            {{ result.months_covered }} months
          </div>
        </div>
      </div>
      <div class="h-3 bg-horizon-100 rounded-full overflow-hidden">
        <div class="h-full transition-all duration-500"
             :class="result.status === 'adequate' ? 'bg-spring-500' : 'bg-violet-500'"
             :style="{ width: progressPercent + '%' }" />
      </div>
      <p class="mt-3 text-sm text-horizon-500">Weighting reason: {{ result.weighting_reason }}</p>
      <p v-if="result.status === 'shortfall'" class="mt-2 text-sm font-semibold text-raspberry-600">
        Shortfall: {{ formatZARMinor(result.shortfall_minor) }}
      </p>
    </div>
  </div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaEmergencyFundGauge',
  mixins: [zaCurrencyMixin],
  data() {
    return {
      form: {
        current_balance: null,
        monthly: null,
        earners: 1,
        uif: true,
      },
    };
  },
  computed: {
    ...mapGetters('zaSavings', ['emergencyFund']),
    result() {
      return this.emergencyFund;
    },
    progressPercent() {
      if (!this.result || !this.result.target_minor) return 0;
      const targetMajor = this.result.target_minor / 100;
      const currentMajor = Number(this.form.current_balance) || 0;
      if (targetMajor === 0) return 0;
      return Math.min(100, (currentMajor / targetMajor) * 100);
    },
    statusColor() {
      if (!this.result) return 'text-horizon-700';
      return this.result.status === 'adequate' ? 'text-spring-600' : 'text-violet-600';
    },
  },
  methods: {
    ...mapActions('zaSavings', ['assessEmergencyFund']),
    async assess() {
      await this.assessEmergencyFund({
        current_balance_minor: this.toMinorZAR(this.form.current_balance || 0),
        essential_monthly_expenditure_minor: this.toMinorZAR(this.form.monthly || 0),
        income_stability: 'stable',
        household_income_earners: this.form.earners,
        uif_eligible: this.form.uif,
      });
    },
  },
};
</script>
