<template>
  <section class="card p-6">
    <header class="mb-4">
      <h2 class="text-xl font-bold text-horizon-700">Will I need approval?</h2>
      <p class="text-xs text-horizon-400 mt-1">
        Quick check whether a one-off offshore transfer needs South African Revenue Service Approval for International Transfer (AIT) or South African Reserve Bank (SARB) special approval.
      </p>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Amount (ZAR)</label>
        <input
          v-model.number="form.amount"
          type="number"
          step="0.01"
          min="0.01"
          class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
        />
      </div>
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Transfer type</label>
        <select
          v-model="form.type"
          class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
        >
          <option value="investment">Investment</option>
          <option value="emigration">Emigration</option>
          <option value="gift">Gift</option>
          <option value="other">Other</option>
        </select>
      </div>
    </div>

    <button
      type="button"
      class="mt-4 bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2 rounded-lg disabled:opacity-50"
      :disabled="!form.amount || checking"
      @click="check"
    >
      {{ checking ? 'Checking…' : 'Check requirement' }}
    </button>

    <div
      v-if="approvalCheck"
      class="mt-4 p-4 rounded-lg"
      :class="approvalCheck.result.requires_approval ? 'bg-violet-50' : 'bg-spring-50'"
    >
      <div
        class="font-bold"
        :class="approvalCheck.result.requires_approval ? 'text-violet-700' : 'text-spring-700'"
      >
        {{ approvalCheck.result.requires_approval ? 'Approval required' : 'No special approval needed' }}
      </div>
      <p class="text-sm text-horizon-600 mt-1">
        For {{ formatZARMinor(approvalCheck.result.amount_minor) }} ({{ approvalCheck.result.type }}).
        <span v-if="approvalCheck.result.requires_approval">
          Submit your South African Revenue Service Approval for International Transfer (AIT) request, or contact your authorised dealer for South African Reserve Bank (SARB) approval.
        </span>
        <span v-else>Falls within the Single Discretionary Allowance.</span>
      </p>
    </div>
  </section>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaApprovalCheckCard',
  mixins: [zaCurrencyMixin],
  data() {
    return {
      form: { amount: null, type: 'investment' },
      checking: false,
    };
  },
  computed: {
    ...mapGetters('zaExchangeControl', ['approvalCheck']),
  },
  methods: {
    ...mapActions('zaExchangeControl', ['checkApproval']),
    async check() {
      this.checking = true;
      try {
        await this.checkApproval({
          amount_minor: this.toMinorZAR(this.form.amount || 0),
          type: this.form.type,
        });
      } finally {
        this.checking = false;
      }
    },
  },
};
</script>
