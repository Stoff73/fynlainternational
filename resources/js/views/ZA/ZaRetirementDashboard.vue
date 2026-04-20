<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <ZaRetirementSummary />

      <ZaRetirementTabs :active="activeTab" @change="switchTab" />

      <section v-if="activeTab === 'accumulation'" class="space-y-6">
        <ZaRetirementFundsList
          @add-fund="showFundForm = true"
          @record-contribution="openContribution"
        />
        <ZaSection11fReliefCalculator />
        <ZaSavingsPotWithdrawalCard />

        <ZaRetirementFundForm
          v-if="showFundForm"
          @save="handleSaveFund"
          @close="showFundForm = false"
        />
        <ZaContributionModal
          v-if="contributingFund"
          :fund="contributingFund"
          @save="handleSaveContribution"
          @close="contributingFund = null"
        />
      </section>

      <section v-else-if="activeTab === 'decumulation'" class="space-y-6">
        <ZaLivingAnnuitySlider />
        <ZaLifeAnnuityQuote />
        <ZaCompulsoryAnnuitisationCard />
      </section>

      <section v-else-if="activeTab === 'compliance'" class="space-y-6">
        <ZaReg28AllocationForm />
        <ZaReg28SnapshotHistory />
      </section>
    </div>
  </AppLayout>
</template>

<script>
import { mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import ZaRetirementSummary from '@/components/ZA/Retirement/ZaRetirementSummary.vue';
import ZaRetirementTabs from '@/components/ZA/Retirement/ZaRetirementTabs.vue';
import ZaRetirementFundsList from '@/components/ZA/Retirement/ZaRetirementFundsList.vue';
import ZaRetirementFundForm from '@/components/ZA/Retirement/ZaRetirementFundForm.vue';
import ZaContributionModal from '@/components/ZA/Retirement/ZaContributionModal.vue';
import ZaSection11fReliefCalculator from '@/components/ZA/Retirement/ZaSection11fReliefCalculator.vue';
import ZaSavingsPotWithdrawalCard from '@/components/ZA/Retirement/ZaSavingsPotWithdrawalCard.vue';
import ZaLivingAnnuitySlider from '@/components/ZA/Retirement/ZaLivingAnnuitySlider.vue';
import ZaLifeAnnuityQuote from '@/components/ZA/Retirement/ZaLifeAnnuityQuote.vue';
import ZaCompulsoryAnnuitisationCard from '@/components/ZA/Retirement/ZaCompulsoryAnnuitisationCard.vue';
import ZaReg28AllocationForm from '@/components/ZA/Retirement/ZaReg28AllocationForm.vue';
import ZaReg28SnapshotHistory from '@/components/ZA/Retirement/ZaReg28SnapshotHistory.vue';

const VALID_TABS = ['accumulation', 'decumulation', 'compliance'];

export default {
  name: 'ZaRetirementDashboard',
  components: {
    AppLayout,
    ZaRetirementSummary,
    ZaRetirementTabs,
    ZaRetirementFundsList,
    ZaRetirementFundForm,
    ZaContributionModal,
    ZaSection11fReliefCalculator,
    ZaSavingsPotWithdrawalCard,
    ZaLivingAnnuitySlider,
    ZaLifeAnnuityQuote,
    ZaCompulsoryAnnuitisationCard,
    ZaReg28AllocationForm,
    ZaReg28SnapshotHistory,
  },
  data() {
    return {
      showFundForm: false,
      contributingFund: null,
    };
  },
  computed: {
    activeTab() {
      const t = this.$route.query.tab;
      return VALID_TABS.includes(t) ? t : 'accumulation';
    },
  },
  async created() {
    try {
      await this.fetchDashboard({});
      await this.fetchFunds();
    } catch (e) {
      // Error state is surfaced via the Vuex module; components read state.error
    }
  },
  methods: {
    ...mapActions('zaRetirement', ['fetchDashboard', 'fetchFunds', 'storeFund', 'storeContribution']),
    switchTab(tab) {
      if (tab === this.activeTab) return;
      this.$router.replace({ path: this.$route.path, query: { ...this.$route.query, tab } });
    },
    openContribution(fund) {
      this.contributingFund = fund;
    },
    async handleSaveFund(payload) {
      await this.storeFund(payload);
      this.showFundForm = false;
      await this.fetchDashboard({});
    },
    async handleSaveContribution(payload) {
      await this.storeContribution(payload);
      this.contributingFund = null;
      await this.fetchDashboard({});
    },
  },
};
</script>
