<template>
  <section class="bg-white rounded-lg shadow-sm border border-horizon-100 p-6">
    <header class="mb-4">
      <h3 class="text-lg font-bold text-horizon-900">Compulsory annuitisation at retirement</h3>
      <p class="text-sm text-horizon-500 mt-1">
        At retirement: 1/3 Pension Commencement Lump Sum (PCLS) + 2/3 compulsory annuity on vested balances.
        If the commutable total (vested + provident pre-2021) is under the R165 000 de-minimis threshold, full commutation is allowed.
      </p>
    </header>

    <form class="space-y-4" @submit.prevent="handleApportion">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Vested balance (R)</label>
          <input v-model.number="form.vested" type="number" min="0" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Provident vested pre-2021 (R)</label>
          <input v-model.number="form.provident_pre2021" type="number" min="0" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Retirement Pot (R)</label>
          <input v-model.number="form.retirement" type="number" min="0" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" />
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-horizon-700 mb-1">Tax year</label>
        <input v-model="form.tax_year" type="text" class="w-full sm:w-1/3 border border-horizon-300 rounded-md px-3 py-2 text-sm" />
      </div>

      <button type="submit" class="px-4 py-2 bg-raspberry-500 hover:bg-raspberry-600 text-white rounded-md text-sm font-semibold" :disabled="loading">
        {{ loading ? 'Calculating…' : 'Apportion at retirement' }}
      </button>
    </form>

    <div v-if="result" class="mt-6 bg-eggshell-100 border border-horizon-100 rounded-md p-4 space-y-3" data-testid="za-compulsory-apportion-result">
      <div class="flex items-start justify-between mb-2">
        <span
          class="inline-block px-3 py-1 text-xs font-semibold rounded-full"
          :class="result.de_minimis_applied ? 'bg-spring-100 text-spring-800' : 'bg-raspberry-100 text-raspberry-800'"
        >
          {{ result.de_minimis_applied
            ? `Below ${formatZARMinor(result.de_minimis_threshold_minor)} de minimis — full commutation allowed`
            : 'Standard 1/3 PCLS + 2/3 compulsory annuity' }}
        </span>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Pension Commencement Lump Sum (PCLS)</p>
          <p class="text-xl font-bold text-horizon-900 tabular-nums">{{ formatZARMinor(result.pcls_minor) }}</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Compulsory annuity</p>
          <p class="text-xl font-bold text-horizon-900 tabular-nums">{{ formatZARMinor(result.compulsory_annuity_minor) }}</p>
        </div>
      </div>
    </div>

    <p v-if="error" class="mt-4 text-sm text-violet-800">{{ error }}</p>
  </section>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaCompulsoryAnnuitisationCard',
  mixins: [zaCurrencyMixin],
  data() {
    return {
      form: {
        vested: 0,
        provident_pre2021: 0,
        retirement: 0,
        tax_year: '2026/27',
      },
      loading: false,
      error: null,
    };
  },
  computed: {
    ...mapState('zaRetirement', {
      quote: (s) => s.annuityQuotes.compulsoryApportion,
    }),
    result() { return this.quote; },
  },
  methods: {
    ...mapActions('zaRetirement', ['apportionCompulsory']),
    async handleApportion() {
      this.loading = true;
      this.error = null;
      try {
        await this.apportionCompulsory({
          vested_minor: Math.round((this.form.vested || 0) * 100),
          provident_vested_pre2021_minor: Math.round((this.form.provident_pre2021 || 0) * 100),
          retirement_minor: Math.round((this.form.retirement || 0) * 100),
          tax_year: this.form.tax_year,
        });
      } catch (e) {
        this.error = e?.response?.data?.message || 'Unable to apportion.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
