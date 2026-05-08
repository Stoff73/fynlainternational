<template>
  <section>
    <h2 class="text-xl font-bold text-horizon-500 mb-4">Beneficiaries</h2>
    <div v-if="!policies.length" class="text-horizon-300 py-12 text-center">
      Add a policy first to nominate beneficiaries.
    </div>
    <div v-for="policy in policies" :key="policy.id" class="mb-6 bg-white rounded-lg p-6 border border-savannah-100">
      <header class="flex justify-between items-baseline mb-3">
        <div>
          <h3 class="font-bold text-horizon-500">{{ policy.provider }} — {{ typeLabel(policy.product_type) }}</h3>
          <p class="text-sm text-horizon-300">Cover {{ formatZAR(policy.cover_amount_major) }}</p>
        </div>
        <span v-if="hasEstate(policy)" class="text-xs font-bold bg-violet-500 text-white px-2 py-1 rounded">
          Estate nomination — dutiable under s3(3)(a)(ii)
        </span>
      </header>
      <ZaBeneficiaryEditor :policy="policy" />
    </div>
  </section>
</template>

<script>
import { mapState } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';
import ZaBeneficiaryEditor from './ZaBeneficiaryEditor.vue';

export default {
  name: 'ZaBeneficiariesTab',
  components: { ZaBeneficiaryEditor },
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapState('zaProtection', ['policies']),
  },
  methods: {
    typeLabel(t) {
      return { life: 'Life cover', whole_of_life: 'Whole of life', dread: 'Dread disease',
               idisability_lump: 'Lump-sum disability', idisability_income: 'Income protection',
               funeral: 'Funeral cover' }[t] || t;
    },
    hasEstate(p) {
      return (p.beneficiaries || []).some((b) => b.beneficiary_type === 'estate');
    },
  },
};
</script>
