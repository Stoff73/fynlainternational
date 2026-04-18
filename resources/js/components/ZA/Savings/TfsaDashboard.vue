<template>
  <section class="space-y-6">
    <header class="flex items-end justify-between">
      <div>
        <h1 class="text-3xl font-black text-horizon-700">Tax-Free Savings</h1>
        <p class="text-sm text-horizon-500 mt-1">
          Tax year {{ taxYear || '2026/27' }} — annual cap R46&nbsp;000, lifetime cap R500&nbsp;000.
        </p>
      </div>
      <button
        type="button"
        class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg transition-colors"
        @click="showContributionModal = true"
      >
        Record contribution
      </button>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="card p-6">
        <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">Annual allowance</div>
        <div class="text-3xl font-black text-horizon-700 mt-2">
          {{ formatZARMinor(tfsa.annualRemainingMinor) }} remaining
        </div>
        <div class="mt-4 h-2 bg-horizon-100 rounded-full overflow-hidden">
          <div
            class="h-full bg-spring-500 transition-all duration-500"
            :style="{ width: annualProgressPercent + '%' }"
          />
        </div>
        <div class="mt-2 text-xs text-horizon-400">
          {{ formatZARMinor(tfsa.annualUsedMinor) }} used of {{ formatZARMinor(tfsa.annualCapMinor) }}
        </div>
      </div>

      <div class="card p-6">
        <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">Lifetime allowance</div>
        <div class="text-3xl font-black text-horizon-700 mt-2">
          {{ formatZARMinor(tfsa.lifetimeRemainingMinor) }} remaining
        </div>
        <div class="mt-4 h-2 bg-horizon-100 rounded-full overflow-hidden">
          <div
            class="h-full bg-spring-500 transition-all duration-500"
            :style="{ width: lifetimeProgressPercent + '%' }"
          />
        </div>
        <div class="mt-2 text-xs text-horizon-400">
          {{ formatZARMinor(tfsa.lifetimeUsedMinor) }} used of {{ formatZARMinor(tfsa.lifetimeCapMinor) }}
        </div>
      </div>
    </div>

    <TfsaContributionTracker :contributions="contributions" :loading="isLoading" />

    <TfsaContributionModal
      v-if="showContributionModal"
      :tax-year="taxYear"
      :annual-remaining-minor="tfsa.annualRemainingMinor"
      :lifetime-remaining-minor="tfsa.lifetimeRemainingMinor"
      @save="handleContribution"
      @close="showContributionModal = false"
    />
  </section>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import TfsaContributionTracker from './TfsaContributionTracker.vue';
import TfsaContributionModal from './TfsaContributionModal.vue';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'TfsaDashboard',
  components: { TfsaContributionTracker, TfsaContributionModal },
  mixins: [zaCurrencyMixin],
  data() {
    return { showContributionModal: false };
  },
  computed: {
    ...mapGetters('zaSavings', ['tfsa', 'contributions', 'taxYear', 'isLoading']),
    annualProgressPercent() {
      const cap = this.tfsa.annualCapMinor;
      if (!cap) return 0;
      return Math.min(100, (this.tfsa.annualUsedMinor / cap) * 100);
    },
    lifetimeProgressPercent() {
      const cap = this.tfsa.lifetimeCapMinor;
      if (!cap) return 0;
      return Math.min(100, (this.tfsa.lifetimeUsedMinor / cap) * 100);
    },
  },
  async mounted() {
    await this.fetchDashboard();
  },
  methods: {
    ...mapActions('zaSavings', ['fetchDashboard', 'storeContribution']),
    async handleContribution(payload) {
      await this.storeContribution(payload);
      this.showContributionModal = false;
    },
  },
};
</script>
