<template>
  <form @submit.prevent="handleSubmit" class="space-y-4 p-6">
    <div>
      <label class="block text-sm font-bold text-horizon-500 mb-1">Policy type</label>
      <ZaPolicyTypeSelector v-model="form.product_type" :disabled="!!existingPolicy" />
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Provider</label>
        <input v-model="form.provider" type="text" required class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Policy number (optional)</label>
        <input v-model="form.policy_number" type="text" class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Cover amount (R)</label>
        <input v-model.number="form.cover_amount_major" type="number" step="1" min="0" required class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Premium (R)</label>
        <input v-model.number="form.premium_amount_major" type="number" step="0.01" min="0" required class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Premium frequency</label>
        <select v-model="form.premium_frequency" required class="w-full border border-savannah-200 rounded-md p-2">
          <option value="monthly">Monthly</option>
          <option value="quarterly">Quarterly</option>
          <option value="annual">Annual</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Start date</label>
        <input v-model="form.start_date" type="date" required class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
    </div>
    <div v-if="form.product_type === 'dread'">
      <label class="block text-sm font-bold text-horizon-500 mb-1">Severity tier (Critical Illness classification)</label>
      <select v-model="form.severity_tier" required class="w-full border border-savannah-200 rounded-md p-2">
        <option value="A">A — Most severe (100% payout)</option>
        <option value="B">B — Severe (75%)</option>
        <option value="C">C — Moderate (50%)</option>
        <option value="D">D — Mild (25%)</option>
      </select>
    </div>
    <div v-if="form.product_type === 'idisability_income'" class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Waiting period (months)</label>
        <input v-model.number="form.waiting_period_months" type="number" min="0" max="60" required class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Benefit term (months)</label>
        <input v-model.number="form.benefit_term_months" type="number" min="0" max="600" required class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
    </div>
    <div class="flex justify-end gap-2 pt-4 border-t border-savannah-100">
      <button type="button" class="px-4 py-2 text-horizon-300 hover:text-horizon-500" @click="$emit('close')">Cancel</button>
      <button type="submit" class="px-4 py-2 bg-raspberry-500 text-white rounded-md font-semibold hover:bg-raspberry-600">Save</button>
    </div>
  </form>
</template>

<script>
import { toMinorZAR } from '@/utils/zaCurrency';
import ZaPolicyTypeSelector from './ZaPolicyTypeSelector.vue';

export default {
  name: 'ZaProtectionPolicyForm',
  components: { ZaPolicyTypeSelector },
  props: {
    existingPolicy: { type: Object, default: null },
  },
  emits: ['save', 'close'],
  data() {
    const base = {
      product_type: 'life',
      provider: '',
      policy_number: '',
      cover_amount_major: 0,
      premium_amount_major: 0,
      premium_frequency: 'monthly',
      start_date: new Date().toISOString().split('T')[0],
      severity_tier: null,
      waiting_period_months: null,
      benefit_term_months: null,
    };
    const fromExisting = this.existingPolicy ? {
      product_type: this.existingPolicy.product_type,
      provider: this.existingPolicy.provider,
      policy_number: this.existingPolicy.policy_number || '',
      cover_amount_major: this.existingPolicy.cover_amount_major,
      premium_amount_major: this.existingPolicy.premium_amount_major,
      premium_frequency: this.existingPolicy.premium_frequency,
      start_date: this.existingPolicy.start_date?.substring(0, 10) || base.start_date,
      severity_tier: this.existingPolicy.severity_tier,
      waiting_period_months: this.existingPolicy.waiting_period_months,
      benefit_term_months: this.existingPolicy.benefit_term_months,
    } : {};
    return { form: { ...base, ...fromExisting } };
  },
  methods: {
    handleSubmit() {
      const payload = {
        product_type: this.form.product_type,
        provider: this.form.provider,
        policy_number: this.form.policy_number || null,
        cover_amount_minor: toMinorZAR(this.form.cover_amount_major || 0),
        premium_amount_minor: toMinorZAR(this.form.premium_amount_major || 0),
        premium_frequency: this.form.premium_frequency,
        start_date: this.form.start_date,
      };
      if (this.form.product_type === 'dread') payload.severity_tier = this.form.severity_tier;
      if (this.form.product_type === 'idisability_income') {
        payload.waiting_period_months = this.form.waiting_period_months;
        payload.benefit_term_months = this.form.benefit_term_months;
      }
      this.$emit('save', payload);
    },
  },
};
</script>
