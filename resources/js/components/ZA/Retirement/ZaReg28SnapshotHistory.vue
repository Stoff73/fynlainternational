<template>
  <section class="bg-white rounded-lg shadow-sm border border-horizon-100 p-6">
    <header class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-lg font-bold text-horizon-900">Regulation 28 snapshot history</h3>
        <p class="text-sm text-horizon-500 mt-1">Previously saved compliance snapshots.</p>
      </div>
      <div>
        <label class="text-xs font-semibold text-horizon-700 mr-2">Tax year</label>
        <select v-model="selectedYear" class="border border-horizon-300 rounded-md px-2 py-1 text-sm" @change="loadSnapshots">
          <option value="">All</option>
          <option v-for="y in taxYears" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
    </header>

    <div v-if="snapshots.length === 0" class="py-8 text-center">
      <p class="text-horizon-500 text-sm">
        No Regulation 28 snapshots yet. Run a check above and click "Save as snapshot" to start a history.
      </p>
    </div>

    <table v-else class="w-full text-sm">
      <thead class="text-left text-xs uppercase text-horizon-500 border-b border-horizon-200">
        <tr>
          <th class="py-2 pr-4">Date</th>
          <th class="py-2 pr-4">Status</th>
          <th class="py-2">Breaches</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in snapshots" :key="s.id" class="border-b border-horizon-100 last:border-0" data-testid="za-reg28-snapshot-row">
          <td class="py-2 pr-4 text-horizon-900">{{ s.as_at_date_iso }}</td>
          <td class="py-2 pr-4">
            <span
              class="inline-block px-2 py-0.5 text-xs font-semibold rounded-full"
              :class="s.compliant ? 'bg-spring-100 text-spring-800' : 'bg-raspberry-100 text-raspberry-800'"
            >
              {{ s.compliant ? 'Compliant' : 'Breach' }}
            </span>
          </td>
          <td class="py-2 text-horizon-500">{{ s.compliant ? '—' : s.breaches.join(', ') }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script>
import { mapActions, mapState } from 'vuex';

export default {
  name: 'ZaReg28SnapshotHistory',
  data() {
    return {
      selectedYear: '',
      taxYears: ['2026/27', '2025/26', '2024/25', '2023/24'],
    };
  },
  computed: {
    ...mapState('zaRetirement', {
      snapshots: (s) => s.reg28Snapshots,
    }),
  },
  async mounted() {
    await this.loadSnapshots();
  },
  methods: {
    ...mapActions('zaRetirement', ['fetchReg28Snapshots']),
    async loadSnapshots() {
      await this.fetchReg28Snapshots(this.selectedYear ? { taxYear: this.selectedYear } : {});
    },
  },
};
</script>
