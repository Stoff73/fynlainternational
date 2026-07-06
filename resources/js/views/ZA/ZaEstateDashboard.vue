<template>
  <AppLayout>
    <div class="max-w-5xl mx-auto py-6 space-y-6">
      <header>
        <h1 class="text-2xl font-display text-horizon-500">Estate Planning (South Africa)</h1>
        <p class="text-sm text-neutral-500">Estate duty, capital gains on death, and your lifetime donations register.</p>
      </header>

      <!-- Estate-duty calculator -->
      <section class="card p-6">
        <h2 class="text-lg font-semibold text-horizon-500 mb-4">Estate-duty estimate</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label class="block">
            <span class="text-sm text-neutral-600">Gross estate (R)</span>
            <input v-model.number="estate.gross" type="number" min="0" class="mt-1 w-full rounded border-light-gray" />
          </label>
          <label class="block">
            <span class="text-sm text-neutral-600">Liabilities (R)</span>
            <input v-model.number="estate.liabilities" type="number" min="0" class="mt-1 w-full rounded border-light-gray" />
          </label>
          <label class="block">
            <span class="text-sm text-neutral-600">Bequest to spouse (R)</span>
            <input v-model.number="estate.spouseTransfer" type="number" min="0" class="mt-1 w-full rounded border-light-gray" />
          </label>
          <label class="flex items-center gap-2 mt-6">
            <input v-model="estate.hasPredeceasedSpouse" type="checkbox" />
            <span class="text-sm text-neutral-600">Predeceased spouse (abatement portability)</span>
          </label>
        </div>
        <button type="button" class="btn-primary mt-4" :disabled="loadingSummary" @click="runSummary">
          {{ loadingSummary ? 'Calculating…' : 'Calculate estate duty' }}
        </button>

        <div v-if="summary" class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
          <div class="text-center p-4 rounded-lg bg-savannah-100">
            <p class="text-sm text-neutral-500 mb-1">Estate duty</p>
            <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(summary.tax_due / 100) }}</p>
          </div>
          <div class="text-center p-4 rounded-lg bg-savannah-100">
            <p class="text-sm text-neutral-500 mb-1">Net estate</p>
            <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(summary.net_estate / 100) }}</p>
          </div>
          <div class="text-center p-4 rounded-lg bg-savannah-100">
            <p class="text-sm text-neutral-500 mb-1">Executor fees</p>
            <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(summary.breakdown.executor_fees / 100) }}</p>
          </div>
        </div>
      </section>

      <!-- Donations register -->
      <section class="card p-6">
        <h2 class="text-lg font-semibold text-horizon-500 mb-4">Donations register</h2>
        <p v-if="donationsTax" class="text-sm text-neutral-600 mb-4">
          This tax year: <strong>{{ formatCurrency(donationsTax.this_year_minor / 100) }}</strong> ·
          Donations tax due: <strong>{{ formatCurrency(donationsTax.tax_due_minor / 100) }}</strong>
          <span class="text-neutral-400">(R100,000 annual exemption applied)</span>
        </p>

        <form class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4" @submit.prevent="submitDonation">
          <input v-model.number="newDonation.amount" type="number" min="1" placeholder="Amount (R)" class="rounded border-light-gray" />
          <input v-model="newDonation.date" type="date" class="rounded border-light-gray" />
          <input v-model="newDonation.recipient" type="text" placeholder="Recipient" class="rounded border-light-gray" />
          <button type="submit" class="btn-primary" :disabled="savingDonation">Add donation</button>
        </form>

        <table v-if="donations.length" class="w-full text-sm">
          <thead>
            <tr class="text-left text-neutral-500 border-b border-light-gray">
              <th class="py-2">Date</th><th>Recipient</th><th class="text-right">Amount</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in donations" :key="d.id" class="border-b border-light-gray">
              <td class="py-2">{{ d.donation_date }}</td>
              <td>{{ d.recipient || '—' }}</td>
              <td class="text-right">{{ formatCurrency(d.amount_minor / 100) }}</td>
              <td class="text-right">
                <button type="button" class="text-raspberry-500 text-xs" @click="removeDonation(d.id)">Remove</button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-sm text-neutral-400">No donations recorded yet.</p>
      </section>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import zaEstateService from '@/services/zaEstateService';

export default {
  name: 'ZaEstateDashboard',
  components: { AppLayout },
  mixins: [currencyMixin],
  data() {
    return {
      estate: { gross: 0, liabilities: 0, spouseTransfer: 0, hasPredeceasedSpouse: false },
      summary: null,
      loadingSummary: false,
      donations: [],
      donationsTax: null,
      newDonation: { amount: null, date: '', recipient: '' },
      savingDonation: false,
    };
  },
  async mounted() {
    await this.refreshDonations();
  },
  methods: {
    async runSummary() {
      this.loadingSummary = true;
      try {
        const res = await zaEstateService.getEstateSummary({
          gross_estate_minor: Math.round((this.estate.gross || 0) * 100),
          liabilities_minor: Math.round((this.estate.liabilities || 0) * 100),
          spouse_transfer_minor: Math.round((this.estate.spouseTransfer || 0) * 100),
          has_predeceased_spouse: this.estate.hasPredeceasedSpouse,
        });
        this.summary = res.data;
      } finally {
        this.loadingSummary = false;
      }
    },
    async refreshDonations() {
      const [list, tax] = await Promise.all([
        zaEstateService.listDonations(),
        zaEstateService.getDonationsTax(),
      ]);
      this.donations = list.data || [];
      this.donationsTax = tax.data || null;
    },
    async submitDonation() {
      if (!this.newDonation.amount || !this.newDonation.date) return;
      this.savingDonation = true;
      try {
        await zaEstateService.addDonation({
          amount_minor: Math.round(this.newDonation.amount * 100),
          donation_date: this.newDonation.date,
          recipient: this.newDonation.recipient || null,
        });
        this.newDonation = { amount: null, date: '', recipient: '' };
        await this.refreshDonations();
      } finally {
        this.savingDonation = false;
      }
    },
    async removeDonation(id) {
      await zaEstateService.deleteDonation(id);
      await this.refreshDonations();
    },
  },
};
</script>
