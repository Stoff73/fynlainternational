<template>
  <AppLayout>
    <div class="module-gradient py-2 sm:py-6">
      <ModuleStatusBar />
      <div class="max-w-7xl mx-auto space-y-8 px-4 py-6">
        <ZaInvestmentSummary @add-account="showAccountForm = true" />

        <ZaSdaSummaryWidget />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <ZaCgtCalculatorCard />
          <ZaCgtProjectionPanel />
        </div>

        <ZaInvestmentAccountsList />
        <ZaHoldingsList
          @record-purchase="showPurchaseModal = true"
          @record-disposal="openDisposal"
        />

        <ZaInvestmentForm
          v-if="showAccountForm"
          @save="handleSaveAccount"
          @close="showAccountForm = false"
        />
        <ZaPurchaseModal
          v-if="showPurchaseModal"
          @save="handleSavePurchase"
          @close="showPurchaseModal = false"
        />
        <ZaDisposalModal
          v-if="disposingHolding"
          :holding="disposingHolding"
          @save="handleSaveDisposal"
          @close="disposingHolding = null"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';
import ZaInvestmentSummary from '@za/components/Investment/ZaInvestmentSummary.vue';
import ZaSdaSummaryWidget from '@za/components/Investment/ZaSdaSummaryWidget.vue';
import ZaInvestmentAccountsList from '@za/components/Investment/ZaInvestmentAccountsList.vue';
import ZaInvestmentForm from '@za/components/Investment/ZaInvestmentForm.vue';
import ZaHoldingsList from '@za/components/Investment/ZaHoldingsList.vue';
import ZaPurchaseModal from '@za/components/Investment/ZaPurchaseModal.vue';
import ZaDisposalModal from '@za/components/Investment/ZaDisposalModal.vue';
import ZaCgtCalculatorCard from '@za/components/Investment/ZaCgtCalculatorCard.vue';
import ZaCgtProjectionPanel from '@za/components/Investment/ZaCgtProjectionPanel.vue';

export default {
  name: 'ZaInvestmentDashboard',
  components: {
    AppLayout,
    ModuleStatusBar,
    ZaInvestmentSummary,
    ZaSdaSummaryWidget,
    ZaInvestmentAccountsList,
    ZaInvestmentForm,
    ZaHoldingsList,
    ZaPurchaseModal,
    ZaDisposalModal,
    ZaCgtCalculatorCard,
    ZaCgtProjectionPanel,
  },
  data() {
    return {
      showAccountForm: false,
      showPurchaseModal: false,
      disposingHolding: null,
    };
  },
  async mounted() {
    await Promise.all([
      this.fetchDashboard(),
      this.fetchAccounts(),
      this.fetchHoldings(),
    ]);
  },
  methods: {
    ...mapActions('zaInvestment', [
      'fetchDashboard',
      'fetchAccounts',
      'fetchHoldings',
      'storeAccount',
      'storePurchase',
      'recordDisposal',
    ]),
    async handleSaveAccount(data) {
      await this.storeAccount(data);
      this.showAccountForm = false;
      await this.fetchDashboard();
    },
    async handleSavePurchase(data) {
      await this.storePurchase(data);
      this.showPurchaseModal = false;
    },
    openDisposal(holding) {
      this.disposingHolding = holding;
    },
    async handleSaveDisposal(data) {
      await this.recordDisposal(data);
      this.disposingHolding = null;
    },
  },
};
</script>
