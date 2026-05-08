<template>
  <div
    class="fixed inset-0 bg-horizon-900/60 z-50 flex items-center justify-center p-4"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
      <header class="mb-6">
        <h2 class="text-2xl font-black text-horizon-700">Record a purchase</h2>
        <p class="text-sm text-horizon-500 mt-1">
          Adds a new lot to the weighted-average cost-basis ledger.
        </p>
      </header>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Holding</label>
          <select
            v-model="form.holding_id"
            required
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          >
            <option value="" disabled>Select a holding</option>
            <option v-for="h in holdings" :key="h.id" :value="h.id">
              {{ h.security_name }}<span v-if="h.ticker"> ({{ h.ticker }})</span>
            </option>
          </select>
          <p v-if="!holdings.length" class="mt-1 text-xs text-horizon-400">
            No holdings yet — create one via the account detail view first.
          </p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Units acquired</label>
          <input
            v-model.number="form.quantity"
            type="number"
            step="0.0001"
            min="0.0001"
            required
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Total cost (ZAR)</label>
          <input
            v-model.number="form.cost"
            type="number"
            step="0.01"
            min="0.01"
            required
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Acquisition date</label>
          <input
            v-model="form.acquisition_date"
            type="date"
            required
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
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
            :disabled="submitting || !form.holding_id"
            class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg disabled:opacity-50"
          >
            {{ submitting ? 'Saving…' : 'Save purchase' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaPurchaseModal',
  mixins: [zaCurrencyMixin],
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        holding_id: '',
        quantity: null,
        cost: null,
        acquisition_date: new Date().toISOString().slice(0, 10),
        notes: '',
      },
      submitting: false,
    };
  },
  computed: {
    ...mapGetters('zaInvestment', ['holdings']),
  },
  methods: {
    async handleSubmit() {
      this.submitting = true;
      try {
        this.$emit('save', {
          holding_id: this.form.holding_id,
          quantity: this.form.quantity,
          cost_minor: this.toMinorZAR(this.form.cost || 0),
          acquisition_date: this.form.acquisition_date,
          notes: this.form.notes || null,
        });
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
