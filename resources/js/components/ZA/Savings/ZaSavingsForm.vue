<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Bank name</label>
        <input v-model="form.institution" type="text" required maxlength="100"
               class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
      </div>
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Account name</label>
        <input v-model="form.account_name" type="text" required maxlength="100"
               class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Account type</label>
        <select v-model="form.account_type" required
                class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500">
          <option value="current">Current (cheque)</option>
          <option value="savings">Savings</option>
          <option value="tfsa">Tax-Free Savings (TFSA)</option>
          <option value="notice">Notice deposit</option>
          <option value="money_market">Money market</option>
          <option value="fixed_deposit">Fixed deposit</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Current balance (ZAR)</label>
        <input v-model.number="form.current_balance" type="number" step="0.01" min="0" required
               class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Interest rate (%)</label>
        <input v-model.number="form.interest_rate" type="number" step="0.01" min="0" max="100"
               class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
      </div>
      <div class="flex items-end">
        <label class="inline-flex items-center gap-3">
          <input v-model="form.is_tfsa" type="checkbox"
                 class="h-5 w-5 rounded border-horizon-300 text-raspberry-500 focus:ring-violet-500" />
          <span class="text-sm font-semibold text-horizon-700">This is a TFSA account</span>
        </label>
      </div>
    </div>

    <div class="flex items-center justify-end gap-3 pt-4">
      <button type="button" @click="$emit('close')"
              class="px-4 py-2 rounded-lg text-horizon-700 hover:bg-savannah-500 font-semibold">Cancel</button>
      <button type="submit" :disabled="submitting"
              class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg disabled:opacity-50">
        {{ submitting ? 'Saving…' : 'Save account' }}
      </button>
    </div>
  </form>
</template>

<script>
export default {
  name: 'ZaSavingsForm',
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        institution: '',
        account_name: '',
        account_type: 'savings',
        current_balance: null,
        interest_rate: null,
        is_tfsa: false,
      },
      submitting: false,
    };
  },
  watch: {
    'form.account_type'(v) {
      if (v === 'tfsa') this.form.is_tfsa = true;
    },
  },
  methods: {
    async handleSubmit() {
      this.submitting = true;
      try {
        this.$emit('save', { ...this.form });
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
