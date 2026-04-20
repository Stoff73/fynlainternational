<template>
  <div>
    <table class="w-full">
      <thead>
        <tr class="text-sm text-horizon-300 border-b border-savannah-100">
          <th class="text-left py-2">Type</th>
          <th class="text-left py-2">Name</th>
          <th class="text-left py-2">Relationship</th>
          <th class="text-left py-2">Identity number</th>
          <th class="text-right py-2">Allocation %</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(b, idx) in rows" :key="idx" class="border-b border-savannah-100">
          <td class="py-2"><select v-model="b.beneficiary_type" class="border rounded p-1">
            <option value="estate">Estate</option>
            <option value="spouse">Spouse</option>
            <option value="nominated_individual">Nominated individual</option>
            <option value="testamentary_trust">Testamentary trust</option>
            <option value="inter_vivos_trust">Inter-vivos trust</option>
          </select></td>
          <td class="py-2"><input v-model="b.name" type="text" :disabled="b.beneficiary_type === 'estate'" class="border rounded p-1" /></td>
          <td class="py-2"><input v-model="b.relationship" type="text" class="border rounded p-1" /></td>
          <td class="py-2"><input v-model="b.id_number" type="text" :disabled="b.beneficiary_type !== 'nominated_individual'" class="border rounded p-1 w-32" /></td>
          <td class="py-2 text-right"><input v-model.number="b.allocation_percentage" type="number" step="0.01" min="0" max="100" class="border rounded p-1 w-24 text-right" /></td>
          <td class="py-2 text-right"><button type="button" class="text-raspberry-500" @click="remove(idx)">Remove</button></td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" class="text-right text-sm text-horizon-300 py-2">Sum:</td>
          <td class="py-2 text-right font-bold" :class="sumValid ? 'text-spring-500' : 'text-raspberry-500'">{{ sum.toFixed(2) }}</td>
          <td></td>
        </tr>
      </tfoot>
    </table>
    <div class="flex justify-between items-center mt-4">
      <button type="button" class="text-raspberry-500 hover:underline" @click="add">+ Add beneficiary</button>
      <button
        v-preview-disabled
        type="button"
        :disabled="!sumValid"
        :class="['px-4 py-2 rounded-md font-semibold',
                 sumValid ? 'bg-raspberry-500 text-white hover:bg-raspberry-600' : 'bg-savannah-100 text-horizon-300 cursor-not-allowed']"
        @click="save"
      >Save beneficiaries</button>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';

export default {
  name: 'ZaBeneficiaryEditor',
  props: { policy: { type: Object, required: true } },
  data() {
    return { rows: JSON.parse(JSON.stringify(this.policy.beneficiaries || [])) };
  },
  computed: {
    sum() { return this.rows.reduce((a, r) => a + (Number(r.allocation_percentage) || 0), 0); },
    sumValid() { return Math.abs(this.sum - 100) < 0.01 && this.rows.length > 0; },
  },
  methods: {
    ...mapActions('zaProtection', ['saveBeneficiaries']),
    add() {
      this.rows.push({ beneficiary_type: 'spouse', name: '', relationship: '', id_number: '', allocation_percentage: 0 });
    },
    remove(idx) { this.rows.splice(idx, 1); },
    async save() {
      if (!this.sumValid) return;
      await this.saveBeneficiaries({ policyId: this.policy.id, beneficiaries: this.rows });
    },
  },
};
</script>
