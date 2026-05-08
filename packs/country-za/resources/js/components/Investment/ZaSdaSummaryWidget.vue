<template>
  <section v-if="hasZa" class="card p-6">
    <header class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-bold text-horizon-700">Offshore allowances</h2>
      <router-link
        to="/za/exchange-control"
        class="text-sm text-raspberry-500 hover:text-raspberry-700 font-semibold"
      >
        Manage offshore transfers
      </router-link>
    </header>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
      <div>
        <div class="text-horizon-400 uppercase tracking-wide text-xs">Single Discretionary Allowance</div>
        <div class="text-horizon-700 font-bold mt-1">
          {{ formatZARMinor(consumed.sdaMinor) }} of {{ formatZARMinor(allowances.sda?.annual_limit || 0) }} used
        </div>
      </div>
      <div>
        <div class="text-horizon-400 uppercase tracking-wide text-xs">Foreign Investment Allowance</div>
        <div class="text-horizon-700 font-bold mt-1">
          {{ formatZARMinor(consumed.fiaMinor) }} of {{ formatZARMinor(allowances.fia?.annual_limit || 0) }} used
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaSdaSummaryWidget',
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapGetters('jurisdiction', ['hasJurisdiction']),
    ...mapGetters('zaExchangeControl', ['allowances', 'consumed']),
    hasZa() {
      return this.hasJurisdiction('za');
    },
  },
  async mounted() {
    if (this.hasZa && !this.allowances.sda) {
      await this.fetchDashboard();
    }
  },
  methods: {
    ...mapActions('zaExchangeControl', ['fetchDashboard']),
  },
};
</script>
