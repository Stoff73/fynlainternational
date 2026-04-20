<template>
  <section>
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-bold text-horizon-500">Your policies</h2>
      <button
        v-preview-disabled="'add'"
        type="button"
        class="bg-raspberry-500 text-white px-4 py-2 rounded-md font-semibold hover:bg-raspberry-600"
        @click="openAdd"
      >Add policy</button>
    </div>
    <div v-if="!policies.length" class="text-horizon-300 py-12 text-center">
      No policies yet. Click "Add policy" to start.
    </div>
    <div v-for="(group, type) in policiesByType" :key="type" class="mb-6">
      <h3 class="text-lg font-bold text-horizon-500 mb-2">{{ typeLabel(type) }}</h3>
      <table class="w-full border-collapse">
        <thead>
          <tr class="border-b border-savannah-100 text-horizon-300 text-sm">
            <th class="text-left py-2">Provider</th>
            <th class="text-right py-2">Cover</th>
            <th class="text-right py-2">Premium</th>
            <th class="text-right py-2">Beneficiaries</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in group" :key="p.id" class="border-b border-savannah-100">
            <td class="py-3">{{ p.provider }}</td>
            <td class="py-3 text-right">{{ formatZAR(p.cover_amount_major) }}</td>
            <td class="py-3 text-right">{{ formatZAR(p.premium_amount_major) }} / {{ p.premium_frequency }}</td>
            <td class="py-3 text-right">{{ (p.beneficiaries || []).length }}</td>
            <td class="py-3 text-right">
              <button v-preview-disabled type="button" class="text-raspberry-500 hover:underline mr-2" @click="openEdit(p)">Edit</button>
              <button v-preview-disabled="'delete'" type="button" class="text-raspberry-500 hover:underline" @click="confirmDelete(p)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <ZaProtectionPolicyModal
      :open="modalOpen"
      :policy="editing"
      @save="handleSave"
      @close="modalOpen = false"
    />
  </section>
</template>

<script>
import { mapActions, mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';
import ZaProtectionPolicyModal from './ZaProtectionPolicyModal.vue';

export default {
  name: 'ZaPoliciesTable',
  components: { ZaProtectionPolicyModal },
  mixins: [zaCurrencyMixin],
  data() {
    return { modalOpen: false, editing: null };
  },
  computed: {
    ...mapGetters('zaProtection', ['policiesByType']),
    policies() { return this.$store.state.zaProtection.policies; },
  },
  methods: {
    ...mapActions('zaProtection', ['createPolicy', 'updatePolicy', 'deletePolicy']),
    typeLabel(t) {
      return { life: 'Life cover', whole_of_life: 'Whole of life', dread: 'Dread disease',
               idisability_lump: 'Lump-sum disability', idisability_income: 'Income protection',
               funeral: 'Funeral cover' }[t] || t;
    },
    openAdd() { this.editing = null; this.modalOpen = true; },
    openEdit(p) { this.editing = p; this.modalOpen = true; },
    async confirmDelete(p) {
      if (!confirm(`Delete ${p.provider} ${this.typeLabel(p.product_type)}?`)) return;
      await this.deletePolicy(p.id);
    },
    async handleSave(payload) {
      if (this.editing) {
        await this.updatePolicy({ id: this.editing.id, payload });
      } else {
        await this.createPolicy(payload);
      }
      this.modalOpen = false;
    },
  },
};
</script>
