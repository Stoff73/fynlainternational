<template>
  <div class="card p-6">
    <div class="flex items-end justify-between mb-4">
      <h2 class="text-xl font-bold text-horizon-700">Recent contributions</h2>
      <span class="text-sm text-horizon-400">{{ contributions.length }} this tax year</span>
    </div>

    <div v-if="loading" class="py-10 flex justify-center">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <div v-else-if="contributions.length === 0" class="py-10 text-center text-horizon-400">
      No contributions yet this tax year. Record your first to start tracking.
    </div>

    <table v-else class="w-full text-sm">
      <thead>
        <tr class="text-left text-horizon-400 uppercase tracking-wide text-xs border-b border-light-gray">
          <th class="py-2 font-semibold">Date</th>
          <th class="py-2 font-semibold">Type</th>
          <th class="py-2 font-semibold text-right">Amount</th>
          <th class="py-2 font-semibold">Notes</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="c in contributions"
          :key="c.id"
          class="border-b border-light-gray last:border-0"
        >
          <td class="py-3 text-horizon-700">{{ c.contribution_date }}</td>
          <td class="py-3 text-horizon-500 capitalize">{{ formatSourceType(c.source_type) }}</td>
          <td class="py-3 text-horizon-700 font-semibold text-right">
            {{ formatZARMinor(c.amount_minor) }}
          </td>
          <td class="py-3 text-horizon-500 truncate max-w-xs">{{ c.notes || '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'TfsaContributionTracker',
  mixins: [zaCurrencyMixin],
  props: {
    contributions: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
  },
  methods: {
    formatSourceType(v) {
      if (!v) return '';
      return String(v).replace('_', ' ');
    },
  },
};
</script>
