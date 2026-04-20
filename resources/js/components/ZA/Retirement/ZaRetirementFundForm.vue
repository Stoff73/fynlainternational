<template>
  <div class="fixed inset-0 bg-horizon-900 bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="$emit('close')">
    <div class="bg-white rounded-lg shadow-xl max-w-xl w-full max-h-[90vh] overflow-y-auto">
      <header class="px-6 py-4 border-b border-horizon-100">
        <h3 class="text-lg font-bold text-horizon-900">Add South African retirement fund</h3>
        <p class="text-sm text-horizon-500 mt-1">
          Record a Retirement Annuity (RA), Pension Fund (PF), Provident Fund (PvF), or Preservation Fund.
        </p>
      </header>

      <form class="p-6 space-y-4" @submit.prevent="handleSubmit">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-2">Fund type</label>
          <div class="space-y-2">
            <label v-for="opt in fundTypes" :key="opt.value" class="flex items-start gap-2 cursor-pointer">
              <input v-model="form.fund_type" type="radio" :value="opt.value" class="mt-1 text-raspberry-500 focus:ring-raspberry-500" required />
              <span class="text-sm">
                <span class="font-semibold text-horizon-900">{{ opt.label }}</span>
                <span class="text-horizon-500 block text-xs">{{ opt.desc }}</span>
              </span>
            </label>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Provider</label>
          <input v-model="form.provider" type="text" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm focus:ring-raspberry-500 focus:border-raspberry-500" placeholder="e.g. Allan Gray, Old Mutual, Coronation" maxlength="120" required />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Scheme name <span class="text-horizon-400 font-normal">(optional)</span></label>
          <input v-model="form.scheme_name" type="text" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm focus:ring-raspberry-500 focus:border-raspberry-500" maxlength="255" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Member number <span class="text-horizon-400 font-normal">(optional)</span></label>
          <input v-model="form.member_number" type="text" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm focus:ring-raspberry-500 focus:border-raspberry-500" maxlength="60" />
        </div>

        <fieldset class="border-t border-horizon-100 pt-4">
          <legend class="text-sm font-semibold text-horizon-700 mb-2">Starting balances (optional)</legend>
          <p class="text-xs text-horizon-500 mb-3">Enter current balances in rands. Leave at 0 if you're starting fresh.</p>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-horizon-700 mb-1">Vested (R)</label>
              <input v-model.number="form.starting_vested" type="number" min="0" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-horizon-700 mb-1">Savings Pot (R)</label>
              <input v-model.number="form.starting_savings" type="number" min="0" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-horizon-700 mb-1">Retirement Pot (R)</label>
              <input v-model.number="form.starting_retirement" type="number" min="0" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" />
            </div>
          </div>

          <div v-if="form.fund_type === 'provident_fund'" class="mt-3">
            <label class="block text-xs font-semibold text-horizon-700 mb-1">Provident vested pre-2021 (R)</label>
            <input v-model.number="form.provident_vested_pre2021" type="number" min="0" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
            <p class="text-xs text-horizon-500 mt-1">Balance at 1 March 2021 (100% commutable for members 55+ on that date).</p>
          </div>
        </fieldset>

        <footer class="flex justify-end gap-3 pt-4 border-t border-horizon-100">
          <button type="button" class="px-4 py-2 text-sm font-semibold text-horizon-700 hover:bg-horizon-50 rounded-md transition-colors" @click="$emit('close')">
            Cancel
          </button>
          <button
            type="submit"
            class="px-4 py-2 bg-raspberry-500 hover:bg-raspberry-600 text-white rounded-md text-sm font-semibold transition-colors"
            v-preview-disabled="'add'"
          >
            Save fund
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script>
import { toMinorZAR } from '@/utils/zaCurrency';

export default {
  name: 'ZaRetirementFundForm',
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        fund_type: 'retirement_annuity',
        provider: '',
        scheme_name: '',
        member_number: '',
        starting_vested: 0,
        starting_savings: 0,
        starting_retirement: 0,
        provident_vested_pre2021: 0,
      },
      fundTypes: [
        { value: 'retirement_annuity', label: 'Retirement Annuity (RA)', desc: 'Personal retirement product; contributions deductible under Section 11F.' },
        { value: 'pension_fund', label: 'Pension Fund (PF)', desc: 'Employer-sponsored defined contribution or defined benefit fund.' },
        { value: 'provident_fund', label: 'Provident Fund (PvF)', desc: 'Employer-sponsored fund; pre-2021 vested balance fully commutable.' },
        { value: 'preservation_fund', label: 'Preservation Fund', desc: 'Holds balances transferred from prior pension or provident funds.' },
      ],
    };
  },
  methods: {
    handleSubmit() {
      const payload = {
        fund_type: this.form.fund_type,
        provider: this.form.provider.trim(),
        scheme_name: this.form.scheme_name.trim() || null,
        member_number: this.form.member_number.trim() || null,
        starting_vested_minor: toMinorZAR(this.form.starting_vested),
        starting_savings_minor: toMinorZAR(this.form.starting_savings),
        starting_retirement_minor: toMinorZAR(this.form.starting_retirement),
      };
      if (this.form.fund_type === 'provident_fund') {
        payload.provident_vested_pre2021_minor = toMinorZAR(this.form.provident_vested_pre2021);
      }
      this.$emit('save', payload);
    },
  },
};
</script>
