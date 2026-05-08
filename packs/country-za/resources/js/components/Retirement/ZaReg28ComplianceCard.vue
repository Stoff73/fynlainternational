<template>
  <div class="bg-eggshell-100 border border-horizon-100 rounded-md p-4 space-y-4" data-testid="za-reg28-result">
    <div class="flex items-center justify-between">
      <span
        class="inline-block px-3 py-1 text-sm font-semibold rounded-full"
        :class="result.compliant ? 'bg-spring-100 text-spring-800' : 'bg-raspberry-100 text-raspberry-800'"
      >
        {{ result.compliant ? 'Compliant with Regulation 28' : 'Breaches detected' }}
      </span>
      <span class="text-xs text-horizon-500">Tax year {{ result.tax_year }}</span>
    </div>

    <table class="w-full text-sm">
      <thead class="text-left text-xs uppercase text-horizon-500 border-b border-horizon-200">
        <tr>
          <th class="py-2 pr-4">Asset class</th>
          <th class="py-2 pr-4 text-right">Your %</th>
          <th class="py-2 pr-4 text-right">Regulation 28 limit</th>
          <th class="py-2 text-center">Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.key" class="border-b border-horizon-100 last:border-0">
          <td class="py-2 pr-4 text-horizon-900">{{ row.label }}</td>
          <td class="py-2 pr-4 text-right tabular-nums">{{ row.actual.toFixed(1) }}%</td>
          <td class="py-2 pr-4 text-right tabular-nums text-horizon-500">{{ row.limit.toFixed(1) }}%</td>
          <td class="py-2 text-center">
            <span :class="row.compliant ? 'text-spring-600 font-semibold' : 'text-raspberry-500 font-semibold'">
              {{ row.compliant ? '✓' : '✗' }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="!result.compliant" class="border-t border-horizon-200 pt-3">
      <p class="text-xs uppercase tracking-wide text-horizon-500 mb-2">Breaches</p>
      <ul class="text-sm text-raspberry-700 space-y-1">
        <li v-for="key in result.breaches" :key="key">
          {{ labelFor(key) }} allocation ({{ (result.per_class[key]?.actual_pct || 0).toFixed(1) }}%) exceeds Regulation 28 limit of {{ (result.per_class[key]?.limit_pct || 0).toFixed(1) }}%.
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
const LABELS = {
  offshore: 'Offshore',
  equity: 'Equity',
  property: 'Property',
  private_equity: 'Private equity',
  commodities: 'Commodities',
  hedge_funds: 'Hedge funds',
  other: 'Other',
  single_entity: 'Single-entity exposure',
};

export default {
  name: 'ZaReg28ComplianceCard',
  props: {
    result: { type: Object, required: true },
  },
  computed: {
    rows() {
      const keys = Object.keys(LABELS);
      return keys.map((k) => {
        const pc = this.result.per_class?.[k] || {};
        return {
          key: k,
          label: LABELS[k],
          actual: Number(pc.actual_pct ?? 0),
          limit: Number(pc.limit_pct ?? 0),
          compliant: pc.compliant !== false,
        };
      });
    },
  },
  methods: {
    labelFor(key) { return LABELS[key] || key; },
  },
};
</script>
