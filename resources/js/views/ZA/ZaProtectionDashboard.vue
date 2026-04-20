<template>
  <div class="za-protection-dashboard">
    <header class="mb-6">
      <h1 class="text-3xl font-black text-horizon-500">Protection</h1>
      <p class="text-horizon-300 mt-2">Policies, coverage gap, and beneficiaries.</p>
    </header>
    <div class="tabs border-b border-savannah-100 mb-6">
      <button
        v-for="tab in tabs" :key="tab.key"
        type="button"
        :class="['px-4 py-2 border-b-2 transition',
                 activeTab === tab.key ? 'border-raspberry-500 text-horizon-500 font-bold' : 'border-transparent text-horizon-300 hover:text-horizon-500']"
        @click="setTab(tab.key)"
      >{{ tab.label }}</button>
    </div>
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin" />
    </div>
    <component v-else :is="currentComponent" />
  </div>
</template>

<script>
import { mapActions, mapGetters, mapState } from 'vuex';
import ZaPoliciesTable from '@/components/ZA/Protection/ZaPoliciesTable.vue';
import ZaCoverageGapDashboard from '@/components/ZA/Protection/ZaCoverageGapDashboard.vue';
import ZaBeneficiariesTab from '@/components/ZA/Protection/ZaBeneficiariesTab.vue';

export default {
  name: 'ZaProtectionDashboard',
  components: { ZaPoliciesTable, ZaCoverageGapDashboard, ZaBeneficiariesTab },
  data() {
    return {
      activeTab: this.$route.query.tab || 'policies',
      tabs: [
        { key: 'policies', label: 'Policies', component: 'ZaPoliciesTable' },
        { key: 'coverage-gap', label: 'Coverage gap', component: 'ZaCoverageGapDashboard' },
        { key: 'beneficiaries', label: 'Beneficiaries', component: 'ZaBeneficiariesTab' },
      ],
    };
  },
  computed: {
    ...mapState('zaProtection', ['loading']),
    ...mapGetters('zaProtection', ['isLoaded']),
    currentComponent() {
      const t = this.tabs.find((x) => x.key === this.activeTab);
      return t ? t.component : 'ZaPoliciesTable';
    },
  },
  async mounted() {
    await Promise.all([
      this.fetchDashboard(),
      this.fetchPolicies(),
      this.fetchCoverageGap(),
      this.fetchPolicyTypes(),
    ]);
  },
  methods: {
    ...mapActions('zaProtection', ['fetchDashboard', 'fetchPolicies', 'fetchCoverageGap', 'fetchPolicyTypes']),
    setTab(key) {
      this.activeTab = key;
      this.$router.replace({ query: { ...this.$route.query, tab: key } }).catch(() => {});
    },
  },
};
</script>
