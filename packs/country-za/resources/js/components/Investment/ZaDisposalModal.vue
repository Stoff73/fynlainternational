<template>
  <div
    class="fixed inset-0 bg-horizon-900/60 z-50 flex items-center justify-center p-4"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
      <header class="mb-6">
        <h2 class="text-2xl font-black text-horizon-700">Record a disposal</h2>
        <p class="text-sm text-horizon-500 mt-1">
          Draws down weighted-average cost basis across open lots.
        </p>
      </header>

      <div v-if="holding" class="mb-4 text-sm text-horizon-500">
        Holding: <strong class="text-horizon-700">{{ holding.security_name }}</strong> — open quantity
        {{ holding.open_quantity ?? holding.quantity }}, cost basis {{ formatZAR(holding.cost_basis) }}.
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Units disposed</label>
          <input
            v-model.number="form.quantity"
            type="number"
            step="0.0001"
            min="0.0001"
            :max="maxUnits"
            required
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
          <p v-if="exceedsOpen" class="mt-1 text-sm text-violet-600">
            Warning: exceeds open quantity ({{ maxUnits }} available).
          </p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Disposal date</label>
          <input
            v-model="form.disposal_date"
            type="date"
            required
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
            :disabled="submitting || exceedsOpen"
            class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg disabled:opacity-50"
          >
            {{ submitting ? 'Saving…' : 'Save disposal' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaDisposalModal',
  mixins: [zaCurrencyMixin],
  props: {
    holding: { type: Object, required: true },
  },
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        quantity: null,
        disposal_date: new Date().toISOString().slice(0, 10),
      },
      submitting: false,
    };
  },
  computed: {
    maxUnits() {
      return this.holding?.open_quantity ?? this.holding?.quantity ?? 0;
    },
    exceedsOpen() {
      return (this.form.quantity || 0) > this.maxUnits + 1e-6;
    },
  },
  methods: {
    async handleSubmit() {
      this.submitting = true;
      try {
        this.$emit('save', {
          holding_id: this.holding.id,
          quantity: this.form.quantity,
          disposal_date: this.form.disposal_date,
        });
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
