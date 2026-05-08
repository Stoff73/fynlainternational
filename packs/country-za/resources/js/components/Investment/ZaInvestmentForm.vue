<template>
  <div
    class="fixed inset-0 bg-horizon-900/60 z-50 flex items-center justify-center p-4"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
      <header class="mb-6">
        <h2 class="text-2xl font-black text-horizon-700">Add a South African investment account</h2>
        <p class="text-sm text-horizon-500 mt-1">Tax year {{ form.tax_year }}</p>
      </header>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-2">Wrapper type</label>
          <div class="grid grid-cols-3 gap-2">
            <label
              v-for="opt in wrapperOptions"
              :key="opt.value"
              :class="[
                'border-2 rounded-lg px-3 py-2 cursor-pointer text-center',
                form.account_type === opt.value
                  ? 'border-raspberry-500 bg-raspberry-50 text-raspberry-700 font-bold'
                  : 'border-light-gray text-horizon-700 hover:border-horizon-300',
              ]"
            >
              <input type="radio" v-model="form.account_type" :value="opt.value" class="sr-only" />
              {{ opt.label }}
            </label>
          </div>
          <p v-if="form.account_type === 'tfsa'" class="mt-2 text-xs text-horizon-500">
            Tax-Free Savings Account contributions live under
            <router-link to="/za/savings" class="text-raspberry-500 hover:text-raspberry-700 font-semibold">Savings</router-link>.
            You can still record the wrapped account here for portfolio tracking.
          </p>
          <p v-else-if="form.account_type === 'endowment'" class="mt-2 text-xs text-horizon-500">
            Section 29A endowment wrappers have a 5-year restriction on withdrawals.
          </p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Provider</label>
          <input
            v-model="form.provider"
            type="text"
            required
            placeholder="e.g. Allan Gray, Investec, Sygnia"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">
            Linked Investment Service Provider (LISP)
            <span class="text-horizon-400 font-normal">— optional</span>
          </label>
          <input
            v-model="form.platform"
            type="text"
            maxlength="255"
            placeholder="e.g. Allan Gray Platform, Investec Investment Management Services"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Account name (optional)</label>
          <input
            v-model="form.account_name"
            type="text"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Current value (ZAR)</label>
          <input
            v-model.number="form.current_value"
            type="number"
            step="0.01"
            min="0"
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
            :disabled="submitting || !form.account_type"
            class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg disabled:opacity-50"
          >
            {{ submitting ? 'Saving…' : 'Save account' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaInvestmentForm',
  mixins: [zaCurrencyMixin],
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        account_type: 'discretionary',
        provider: '',
        platform: '',
        account_name: '',
        current_value: null,
        tax_year: '2026/27',
      },
      submitting: false,
      wrapperOptions: [
        { value: 'tfsa', label: 'TFSA' },
        { value: 'discretionary', label: 'Discretionary' },
        { value: 'endowment', label: 'Endowment' },
      ],
    };
  },
  methods: {
    async handleSubmit() {
      this.submitting = true;
      try {
        this.$emit('save', {
          account_type: this.form.account_type,
          provider: this.form.provider,
          platform: this.form.platform || null,
          account_name: this.form.account_name || null,
          current_value: this.form.current_value,
          tax_year: this.form.tax_year,
        });
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
