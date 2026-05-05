<template>
  <AppLayout>
    <div class="module-gradient py-2 sm:py-6">
      <ModuleStatusBar />
      <div class="max-w-7xl mx-auto space-y-6 px-4 py-6">
        <header>
          <h1 class="text-3xl font-black text-horizon-500">Exchange Control</h1>
          <p class="text-sm text-horizon-500 mt-1">
            Calendar year {{ calendarYear || currentYear }} — Single Discretionary Allowance (SDA) and Foreign Investment Allowance (FIA).
          </p>
        </header>

        <ZaCombinedThresholdBanner />
        <ZaSdaFiaGauges />
        <ZaApprovalCheckCard />
        <ZaTransferLedger @record-transfer="showTransferModal = true" />

        <ZaTransferModal
          v-if="showTransferModal"
          @save="handleSave"
          @close="showTransferModal = false"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';
import ZaSdaFiaGauges from '@/components/ZA/ExchangeControl/ZaSdaFiaGauges.vue';
import ZaCombinedThresholdBanner from '@/components/ZA/ExchangeControl/ZaCombinedThresholdBanner.vue';
import ZaTransferLedger from '@/components/ZA/ExchangeControl/ZaTransferLedger.vue';
import ZaTransferModal from '@/components/ZA/ExchangeControl/ZaTransferModal.vue';
import ZaApprovalCheckCard from '@/components/ZA/ExchangeControl/ZaApprovalCheckCard.vue';

export default {
  name: 'ZaExchangeControlDashboard',
  components: {
    AppLayout,
    ModuleStatusBar,
    ZaSdaFiaGauges,
    ZaCombinedThresholdBanner,
    ZaTransferLedger,
    ZaTransferModal,
    ZaApprovalCheckCard,
  },
  data() {
    return { showTransferModal: false };
  },
  computed: {
    ...mapGetters('zaExchangeControl', ['calendarYear']),
    currentYear() {
      return new Date().getFullYear();
    },
  },
  async mounted() {
    await Promise.all([this.fetchDashboard(), this.fetchTransfers()]);
  },
  methods: {
    ...mapActions('zaExchangeControl', ['fetchDashboard', 'fetchTransfers', 'storeTransfer']),
    async handleSave(data) {
      await this.storeTransfer(data);
      this.showTransferModal = false;
    },
  },
};
</script>
