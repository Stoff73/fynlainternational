<template>
  <div class="fixed inset-0 bg-horizon-900 bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="$emit('close')">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
      <header class="px-6 py-4 border-b border-horizon-100">
        <h3 class="text-lg font-bold text-horizon-900">Record contribution</h3>
        <p v-if="fund" class="text-sm text-horizon-500 mt-1">
          {{ fund.fund_type_label }} — {{ fund.provider }}
        </p>
      </header>

      <form class="p-6 space-y-4" @submit.prevent="handleSubmit">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Amount (R)</label>
          <input v-model.number="form.amount" type="number" min="0.01" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm focus:ring-raspberry-500 focus:border-raspberry-500" required />
        </div>
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Contribution date</label>
          <input v-model="form.contribution_date" type="date" :max="today" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
        </div>

        <aside v-if="form.amount > 0 && form.contribution_date" class="bg-eggshell-100 border border-horizon-100 rounded-md p-4" data-testid="za-contribution-split-preview">
          <p class="text-xs font-semibold uppercase tracking-wide text-horizon-500 mb-2">Two-Pot split preview</p>
          <template v-if="isPostTwoPot">
            <p class="text-sm text-horizon-900">
              <span class="font-semibold text-raspberry-500">{{ formatZARMinor(savingsMinor) }}</span>
              (33.3%) → Savings Pot
            </p>
            <p class="text-sm text-horizon-900 mt-1">
              <span class="font-semibold text-spring-600">{{ formatZARMinor(retirementMinor) }}</span>
              (66.7%) → Retirement Pot
            </p>
          </template>
          <template v-else>
            <p class="text-sm text-horizon-900">
              <span class="font-semibold">100% → Vested</span>
              (pre-1 September 2024)
            </p>
          </template>
        </aside>

        <footer class="flex justify-end gap-3 pt-4 border-t border-horizon-100">
          <button type="button" class="px-4 py-2 text-sm font-semibold text-horizon-700 hover:bg-horizon-50 rounded-md" @click="$emit('close')">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-raspberry-500 hover:bg-raspberry-600 text-white rounded-md text-sm font-semibold" v-preview-disabled="'add'">
            Save contribution
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script>
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaContributionModal',
  mixins: [zaCurrencyMixin],
  props: {
    fund: { type: Object, required: true },
  },
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        amount: 0,
        contribution_date: new Date().toISOString().slice(0, 10),
      },
    };
  },
  computed: {
    today() { return new Date().toISOString().slice(0, 10); },
    amountMinor() { return Math.round((this.form.amount || 0) * 100); },
    isPostTwoPot() { return this.form.contribution_date >= '2024-09-01'; },
    savingsMinor() {
      if (!this.isPostTwoPot) return 0;
      return Math.floor(this.amountMinor / 3);
    },
    retirementMinor() {
      if (!this.isPostTwoPot) return 0;
      return this.amountMinor - this.savingsMinor;
    },
  },
  methods: {
    handleSubmit() {
      this.$emit('save', {
        fund_holding_id: this.fund.id,
        amount_minor: this.amountMinor,
        contribution_date: this.form.contribution_date,
      });
    },
  },
};
</script>
