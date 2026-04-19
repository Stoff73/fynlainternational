<template>
  <div
    class="fixed inset-0 bg-horizon-900/60 z-50 flex items-center justify-center p-4"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
      <header class="mb-6">
        <h2 class="text-2xl font-black text-horizon-700">Record an offshore transfer</h2>
        <p class="text-sm text-horizon-500 mt-1">
          The Single Discretionary Allowance covers any purpose under R2m. The Foreign Investment Allowance covers R2m–R10m and requires a South African Revenue Service Approval for International Transfer (AIT).
        </p>
      </header>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-2">Allowance type</label>
          <div class="grid grid-cols-1 gap-2">
            <label
              v-for="opt in allowanceOptions"
              :key="opt.value"
              :class="[
                'border-2 rounded-lg px-3 py-2 cursor-pointer',
                form.allowance_type === opt.value
                  ? 'border-raspberry-500 bg-raspberry-50 text-raspberry-700 font-bold'
                  : 'border-light-gray text-horizon-700 hover:border-horizon-300',
              ]"
            >
              <input
                type="radio"
                v-model="form.allowance_type"
                :value="opt.value"
                class="sr-only"
              />
              {{ opt.label }}
            </label>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Amount (ZAR)</label>
          <input
            v-model.number="form.amount"
            type="number"
            step="0.01"
            min="0.01"
            required
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
          <p v-if="willExceedAllowance" class="mt-1 text-sm text-violet-600">
            Warning: this exceeds your remaining {{ allowanceLabelShort }} allowance ({{ formatZARMinor(remainingForType) }}).
          </p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Transfer date</label>
          <input
            v-model="form.transfer_date"
            type="date"
            required
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Destination country</label>
          <input
            v-model="form.destination_country"
            type="text"
            maxlength="120"
            placeholder="e.g. United Kingdom"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Purpose</label>
          <input
            v-model="form.purpose"
            type="text"
            maxlength="255"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Authorised dealer (optional)</label>
          <input
            v-model="form.authorised_dealer"
            type="text"
            maxlength="255"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <template v-if="form.allowance_type === 'fia'">
          <div>
            <label class="block text-sm font-semibold text-horizon-700 mb-1">
              Approval for International Transfer (AIT) reference
            </label>
            <input
              v-model="form.ait_reference"
              type="text"
              maxlength="120"
              class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
            />
          </div>
          <div>
            <label class="block text-sm font-semibold text-horizon-700 mb-2">
              Approval for International Transfer (AIT) documentation checklist
            </label>
            <div class="space-y-2 bg-savannah-100 p-3 rounded-lg">
              <label
                v-for="item in checklistItems"
                :key="item.key"
                class="flex items-start gap-2 text-sm text-horizon-700"
              >
                <input type="checkbox" v-model="form.ait_documents[item.key]" class="mt-1" />
                <span>{{ item.label }}</span>
              </label>
            </div>
          </div>
        </template>

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
            :disabled="submitting"
            class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg disabled:opacity-50"
          >
            {{ submitting ? 'Saving…' : 'Save transfer' }}
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
  name: 'ZaTransferModal',
  mixins: [zaCurrencyMixin],
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        allowance_type: 'sda',
        amount: null,
        transfer_date: new Date().toISOString().slice(0, 10),
        destination_country: '',
        purpose: '',
        authorised_dealer: '',
        ait_reference: '',
        ait_documents: {
          tax_clearance_issued: false,
          source_of_funds_documented: false,
          recipient_kyc_complete: false,
          dealer_notified: false,
        },
      },
      submitting: false,
      allowanceOptions: [
        { value: 'sda', label: 'Single Discretionary Allowance — under R2m' },
        {
          value: 'fia',
          label:
            'Foreign Investment Allowance — R2m – R10m, requires SARS Approval for International Transfer (AIT)',
        },
      ],
      checklistItems: [
        { key: 'tax_clearance_issued', label: 'Tax clearance certificate issued by SARS' },
        { key: 'source_of_funds_documented', label: 'Source-of-funds documentation prepared' },
        { key: 'recipient_kyc_complete', label: 'Recipient account KYC complete' },
        { key: 'dealer_notified', label: 'Authorised dealer notified' },
      ],
    };
  },
  computed: {
    ...mapGetters('zaExchangeControl', ['remaining']),
    amountMinor() {
      return this.toMinorZAR(this.form.amount || 0);
    },
    remainingForType() {
      return this.form.allowance_type === 'sda' ? this.remaining.sdaMinor : this.remaining.fiaMinor;
    },
    allowanceLabelShort() {
      return this.form.allowance_type === 'sda'
        ? 'Single Discretionary Allowance'
        : 'Foreign Investment Allowance';
    },
    willExceedAllowance() {
      return this.amountMinor > this.remainingForType && this.remainingForType >= 0;
    },
  },
  methods: {
    async handleSubmit() {
      this.submitting = true;
      try {
        const payload = {
          allowance_type: this.form.allowance_type,
          amount_minor: this.amountMinor,
          transfer_date: this.form.transfer_date,
          destination_country: this.form.destination_country || null,
          purpose: this.form.purpose || null,
          authorised_dealer: this.form.authorised_dealer || null,
        };
        if (this.form.allowance_type === 'fia') {
          payload.ait_reference = this.form.ait_reference || null;
          payload.ait_documents = this.form.ait_documents;
        }
        this.$emit('save', payload);
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
