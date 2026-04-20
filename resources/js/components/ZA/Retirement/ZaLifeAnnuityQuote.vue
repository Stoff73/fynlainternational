<template>
  <section class="bg-white rounded-lg shadow-sm border border-horizon-100 p-6">
    <header class="mb-4">
      <h3 class="text-lg font-bold text-horizon-900">Life annuity quote</h3>
      <p class="text-sm text-horizon-500 mt-1">
        Model a guaranteed-income annuity. Section 10C exempts the slice attributable to non-deductible contributions.
      </p>
    </header>

    <form class="space-y-4" @submit.prevent="handleQuote">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Quoted annual annuity (R)</label>
          <input v-model.number="form.annual_annuity" type="number" min="1" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
        </div>
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Declared Section 10C pool (R)</label>
          <input v-model.number="form.section_10c_pool" type="number" min="0" step="0.01" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
          <p class="text-xs text-horizon-500 mt-1">
            Your cumulative non-deductible retirement contributions. Ask your fund administrator if unsure. New members: usually R 0.
          </p>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Your age</label>
          <input v-model.number="form.age" type="number" min="55" max="125" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
        </div>
        <div>
          <label class="block text-xs font-semibold text-horizon-700 mb-1">Tax year</label>
          <input v-model="form.tax_year" type="text" class="w-full border border-horizon-300 rounded-md px-3 py-2 text-sm" required />
        </div>
      </div>

      <button type="submit" class="px-4 py-2 bg-raspberry-500 hover:bg-raspberry-600 text-white rounded-md text-sm font-semibold" :disabled="loading">
        {{ loading ? 'Quoting…' : 'Get quote' }}
      </button>
    </form>

    <div v-if="result" class="mt-6 bg-eggshell-100 border border-horizon-100 rounded-md p-4 space-y-3" data-testid="za-life-annuity-result">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Section 10C exempt</p>
          <p class="text-xl font-bold text-spring-600 tabular-nums">{{ formatZARMinor(result.section_10c_exempt_minor) }}</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Taxable portion</p>
          <p class="text-xl font-bold text-horizon-900 tabular-nums">{{ formatZARMinor(result.taxable_minor) }}</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Tax due</p>
          <p class="text-xl font-bold text-raspberry-500 tabular-nums">{{ formatZARMinor(result.tax_due_minor) }}</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-wide text-horizon-500">Remaining Section 10C pool</p>
          <p class="text-xl font-bold text-horizon-900 tabular-nums">{{ formatZARMinor(result.section_10c_remaining_pool_minor) }}</p>
        </div>
      </div>
      <aside v-if="result.pool_exhausted" class="bg-spring-50 border border-spring-300 rounded-md p-3">
        <p class="text-sm text-spring-800">
          Section 10C pool fully exhausted — the remaining annuity is fully taxable.
        </p>
      </aside>
    </div>

    <p v-if="error" class="mt-4 text-sm text-violet-800">{{ error }}</p>
  </section>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaLifeAnnuityQuote',
  mixins: [zaCurrencyMixin],
  data() {
    return {
      form: {
        annual_annuity: 0,
        section_10c_pool: 0,
        age: 65,
        tax_year: '2026/27',
      },
      loading: false,
      error: null,
    };
  },
  computed: {
    ...mapState('zaRetirement', {
      quote: (s) => s.annuityQuotes.life,
    }),
    result() { return this.quote; },
  },
  methods: {
    ...mapActions('zaRetirement', ['quoteLifeAnnuity']),
    async handleQuote() {
      this.loading = true;
      this.error = null;
      try {
        await this.quoteLifeAnnuity({
          annual_annuity_minor: Math.round((this.form.annual_annuity || 0) * 100),
          declared_section_10c_pool_minor: Math.round((this.form.section_10c_pool || 0) * 100),
          age: this.form.age,
          tax_year: this.form.tax_year,
        });
      } catch (e) {
        this.error = e?.response?.data?.message || 'Unable to quote life annuity.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
