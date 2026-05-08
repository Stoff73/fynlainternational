<template>
  <div class="bg-white rounded-lg p-6 border border-savannah-100">
    <h3 class="text-xl font-bold text-horizon-500">{{ policy.provider }}</h3>
    <dl class="mt-4 grid grid-cols-2 gap-4">
      <div><dt class="text-sm text-horizon-300">Cover amount</dt><dd class="font-bold">{{ formatZAR(policy.cover_amount_major) }}</dd></div>
      <div><dt class="text-sm text-horizon-300">Premium</dt><dd class="font-bold">{{ formatZAR(policy.premium_amount_major) }} / {{ policy.premium_frequency }}</dd></div>
      <div v-if="policy.severity_tier"><dt class="text-sm text-horizon-300">Severity tier</dt><dd class="font-bold">{{ policy.severity_tier }}</dd></div>
    </dl>
    <div v-if="taxTreatment" class="mt-6 p-4 bg-eggshell-500 rounded-md">
      <h4 class="font-bold text-horizon-500 mb-2">Tax treatment</h4>
      <p class="text-sm text-horizon-300">{{ taxTreatment.notes }}</p>
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaPolicyDetailCard',
  mixins: [zaCurrencyMixin],
  props: { policy: { type: Object, required: true } },
  computed: {
    ...mapState('zaProtection', ['taxTreatments']),
    taxTreatment() { return this.taxTreatments[this.policy.product_type] || null; },
  },
  async mounted() { await this.fetchTaxTreatment(this.policy.product_type); },
  methods: { ...mapActions('zaProtection', ['fetchTaxTreatment']) },
};
</script>
