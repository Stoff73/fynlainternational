<template>
  <div class="fixed inset-0 bg-horizon-900/60 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
      <header class="mb-6">
        <h2 class="text-2xl font-black text-horizon-700">Record a TFSA contribution</h2>
        <p class="text-sm text-horizon-500 mt-1">Tax year {{ taxYear || '2026/27' }}</p>
      </header>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Amount (ZAR)</label>
          <input
            v-model.number="form.amount"
            type="number"
            step="0.01"
            min="0.01"
            required
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
          />
          <p v-if="willBreachAnnual" class="mt-1 text-sm text-violet-600">
            Warning: this exceeds your annual allowance by {{ formatZARMinor(amountMinor - annualRemainingMinor) }}. A 40% penalty applies to the excess.
          </p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Contribution date</label>
          <input
            v-model="form.contribution_date"
            type="date"
            required
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Type</label>
          <select
            v-model="form.source_type"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          >
            <option value="contribution">New contribution</option>
            <option value="transfer_in">Transfer in from another TFSA</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Notes (optional)</label>
          <input
            v-model="form.notes"
            type="text"
            maxlength="500"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div class="flex items-center justify-end gap-3 pt-4">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 rounded-lg text-horizon-700 hover:bg-savannah-500 font-semibold"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="submitting"
            class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg disabled:opacity-50"
          >
            {{ submitting ? 'Saving…' : 'Save contribution' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'TfsaContributionModal',
  mixins: [zaCurrencyMixin],
  props: {
    taxYear: { type: String, default: '2026/27' },
    annualRemainingMinor: { type: Number, default: 0 },
    lifetimeRemainingMinor: { type: Number, default: 0 },
  },
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        amount: null,
        contribution_date: new Date().toISOString().slice(0, 10),
        source_type: 'contribution',
        notes: '',
      },
      submitting: false,
    };
  },
  computed: {
    amountMinor() {
      return this.toMinorZAR(this.form.amount || 0);
    },
    willBreachAnnual() {
      return this.amountMinor > this.annualRemainingMinor && this.annualRemainingMinor >= 0;
    },
  },
  methods: {
    async handleSubmit() {
      this.submitting = true;
      try {
        this.$emit('save', {
          tax_year: this.taxYear,
          amount_minor: this.amountMinor,
          contribution_date: this.form.contribution_date,
          source_type: this.form.source_type,
          notes: this.form.notes || null,
        });
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
