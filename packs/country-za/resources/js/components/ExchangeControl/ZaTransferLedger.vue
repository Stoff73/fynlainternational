<template>
  <section class="card p-6">
    <header class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-bold text-horizon-700">Transfers in {{ calendarYear }}</h2>
      <button
        type="button"
        class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-4 py-2 rounded-lg text-sm"
        @click="$emit('record-transfer')"
      >
        Record transfer
      </button>
    </header>
    <div v-if="!transfers.length" class="py-6 text-center text-horizon-400">
      No transfers recorded for this calendar year yet.
    </div>
    <table v-else class="w-full text-sm">
      <thead class="text-xs uppercase tracking-wide text-horizon-400 border-b border-light-gray">
        <tr>
          <th class="text-left py-2">Date</th>
          <th class="text-left py-2">Allowance</th>
          <th class="text-left py-2">Destination</th>
          <th class="text-left py-2">Purpose</th>
          <th class="text-right py-2">Amount</th>
          <th class="text-left py-2">Approval reference</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="t in transfers" :key="t.id" class="border-b border-light-gray/50">
          <td class="py-3 text-horizon-700">{{ t.transfer_date }}</td>
          <td class="py-3">
            <span class="uppercase text-xs font-bold">{{ t.allowance_type }}</span>
          </td>
          <td class="py-3 text-horizon-700">{{ t.destination_country || '—' }}</td>
          <td class="py-3 text-horizon-500">{{ t.purpose || '—' }}</td>
          <td class="py-3 text-right font-bold text-horizon-700">
            {{ formatZARMinor(t.amount_minor) }}
          </td>
          <td class="py-3 text-horizon-500">{{ t.ait_reference || '—' }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaTransferLedger',
  mixins: [zaCurrencyMixin],
  emits: ['record-transfer'],
  computed: {
    ...mapGetters('zaExchangeControl', ['transfers', 'calendarYear']),
  },
};
</script>
