<template>
  <section class="bg-white rounded-lg shadow-sm border border-horizon-100 p-6">
    <header class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-lg font-bold text-horizon-900">Retirement funds</h3>
        <p class="text-sm text-horizon-500 mt-1">
          Retirement Annuity (RA), Pension Fund (PF), Provident Fund (PvF), and Preservation Fund accounts.
        </p>
      </div>
      <button
        type="button"
        class="px-4 py-2 bg-raspberry-500 hover:bg-raspberry-600 text-white rounded-md text-sm font-semibold transition-colors"
        @click="$emit('add-fund')"
      >
        Add fund
      </button>
    </header>

    <div v-if="funds.length === 0" class="py-8 text-center">
      <p class="text-horizon-500 text-sm">
        Record your first South African retirement fund to see Two-Pot balances, contributions, and tax relief.
      </p>
    </div>

    <div v-else class="space-y-4">
      <article
        v-for="fund in funds"
        :key="fund.id"
        class="border border-horizon-100 rounded-md p-4"
        data-testid="za-retirement-fund-row"
      >
        <header class="flex items-start justify-between">
          <div>
            <h4 class="font-semibold text-horizon-900">{{ fund.fund_type_label }} — {{ fund.provider }}</h4>
            <p v-if="fund.scheme_name" class="text-sm text-horizon-500 mt-1">{{ fund.scheme_name }}</p>
          </div>
          <div class="text-right">
            <p class="text-xs uppercase tracking-wide text-horizon-500">Total</p>
            <p class="text-lg font-bold text-horizon-900 tabular-nums">{{ formatZARMinor(fund.total_balance_minor) }}</p>
          </div>
        </header>

        <ZaTwoPotTracker :buckets="fund.buckets" />

        <div class="flex justify-end mt-4">
          <button
            type="button"
            class="px-3 py-1.5 text-sm font-semibold text-raspberry-500 hover:text-raspberry-600 transition-colors"
            @click="$emit('record-contribution', fund)"
          >
            Record contribution →
          </button>
        </div>
      </article>
    </div>
  </section>
</template>

<script>
import { mapState } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';
import ZaTwoPotTracker from './ZaTwoPotTracker.vue';

export default {
  name: 'ZaRetirementFundsList',
  components: { ZaTwoPotTracker },
  mixins: [zaCurrencyMixin],
  emits: ['add-fund', 'record-contribution'],
  computed: {
    ...mapState('zaRetirement', ['funds']),
  },
};
</script>
