<template>
  <section class="card p-6">
    <header class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-bold text-horizon-700">Holdings</h2>
      <button
        type="button"
        class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-4 py-2 rounded-lg text-sm"
        @click="$emit('record-purchase')"
      >
        Record purchase
      </button>
    </header>
    <div v-if="!holdings.length" class="py-8 text-center text-horizon-400">
      No holdings recorded yet. Add an account, then record purchases against it.
    </div>
    <table v-else class="w-full text-sm">
      <thead class="text-xs uppercase tracking-wide text-horizon-400 border-b border-light-gray">
        <tr>
          <th class="text-left py-2">Security</th>
          <th class="text-right py-2">Quantity</th>
          <th class="text-right py-2">Cost basis</th>
          <th class="text-right py-2">Open lots</th>
          <th class="text-right py-2"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="h in holdings" :key="h.id" class="border-b border-light-gray/50">
          <td class="py-3 font-semibold text-horizon-700">
            {{ h.security_name }}
            <span v-if="h.ticker" class="text-horizon-400 ml-2">{{ h.ticker }}</span>
          </td>
          <td class="py-3 text-right text-horizon-700">{{ h.open_quantity ?? h.quantity }}</td>
          <td class="py-3 text-right text-horizon-700">{{ formatZAR(h.cost_basis) }}</td>
          <td class="py-3 text-right text-horizon-700">{{ h.open_lot_count ?? 0 }}</td>
          <td class="py-3 text-right">
            <button
              class="text-raspberry-500 hover:text-raspberry-700 font-semibold"
              :disabled="(h.open_quantity ?? h.quantity) <= 0"
              :class="{ 'opacity-40 cursor-not-allowed': (h.open_quantity ?? h.quantity) <= 0 }"
              @click="$emit('record-disposal', h)"
            >
              Dispose
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaHoldingsList',
  mixins: [zaCurrencyMixin],
  emits: ['record-purchase', 'record-disposal'],
  computed: {
    ...mapGetters('zaInvestment', ['holdings']),
  },
};
</script>
