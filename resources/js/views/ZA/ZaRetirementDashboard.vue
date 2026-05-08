<template>
  <AppLayout>
    <div class="module-gradient py-2 sm:py-6">
      <ModuleStatusBar />
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
    </div>
  </AppLayout>
</template>

<script>
import { mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';
import ZaRetirementSummary from '@za/components/Retirement/ZaRetirementSummary.vue';
import ZaRetirementTabs from '@za/components/Retirement/ZaRetirementTabs.vue';
import ZaRetirementFundsList from '@za/components/Retirement/ZaRetirementFundsList.vue';
import ZaRetirementFundForm from '@za/components/Retirement/ZaRetirementFundForm.vue';
import ZaContributionModal from '@za/components/Retirement/ZaContributionModal.vue';
import ZaSection11fReliefCalculator from '@za/components/Retirement/ZaSection11fReliefCalculator.vue';
import ZaSavingsPotWithdrawalCard from '@za/components/Retirement/ZaSavingsPotWithdrawalCard.vue';
import ZaLivingAnnuitySlider from '@za/components/Retirement/ZaLivingAnnuitySlider.vue';
import ZaLifeAnnuityQuote from '@za/components/Retirement/ZaLifeAnnuityQuote.vue';
import ZaCompulsoryAnnuitisationCard from '@za/components/Retirement/ZaCompulsoryAnnuitisationCard.vue';
import ZaReg28AllocationForm from '@za/components/Retirement/ZaReg28AllocationForm.vue';
import ZaReg28SnapshotHistory from '@za/components/Retirement/ZaReg28SnapshotHistory.vue';

const VALID_TABS = ['accumulation', 'decumulation', 'compliance'];

export default {
  name: 'ZaRetirementDashboard',
  components: {
    AppLayout,
    ModuleStatusBar,
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
      await Promise.all([this.fetchDashboard({}), this.fetchFunds()]);
    },
  },
};
</script>
