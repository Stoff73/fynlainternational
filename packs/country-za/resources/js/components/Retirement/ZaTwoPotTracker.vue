<template>
  <div class="space-y-2 mt-4">
    <div v-for="bar in bars" :key="bar.key" class="flex items-center gap-3">
      <span class="text-xs text-horizon-500 w-40 shrink-0">{{ bar.label }}</span>
      <div class="flex-1 bg-horizon-100 rounded h-4 overflow-hidden">
        <div
          class="h-full transition-all duration-300"
          :class="bar.colorClass"
          :style="{ width: bar.widthPct + '%' }"
        ></div>
      </div>
      <span class="text-sm font-semibold text-horizon-900 w-32 text-right tabular-nums">
        {{ formatZARMinor(bar.valueMinor) }}
      </span>
    </div>
    <p v-if="!hasBalance" class="text-sm text-horizon-500 italic mt-2">
      No contributions recorded yet. Record one below to see the Two-Pot split.
    </p>
  </div>
</template>

<script>
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaTwoPotTracker',
  mixins: [zaCurrencyMixin],
  props: {
    buckets: { type: Object, default: null },
  },
  computed: {
    hasBalance() {
      return this.buckets && (
        this.buckets.vested_minor > 0
        || this.buckets.savings_minor > 0
        || this.buckets.retirement_minor > 0
        || this.buckets.provident_vested_pre2021_minor > 0
      );
    },
    total() {
      if (!this.buckets) return 1;
      return Math.max(1,
        this.buckets.vested_minor
        + this.buckets.savings_minor
        + this.buckets.retirement_minor
        + this.buckets.provident_vested_pre2021_minor,
      );
    },
    bars() {
      const b = this.buckets || { vested_minor: 0, savings_minor: 0, retirement_minor: 0, provident_vested_pre2021_minor: 0 };
      const out = [
        { key: 'vested', label: 'Vested', valueMinor: b.vested_minor, colorClass: 'bg-horizon-400', widthPct: (b.vested_minor / this.total) * 100 },
        { key: 'savings', label: 'Savings Pot', valueMinor: b.savings_minor, colorClass: 'bg-raspberry-500', widthPct: (b.savings_minor / this.total) * 100 },
        { key: 'retirement', label: 'Retirement Pot', valueMinor: b.retirement_minor, colorClass: 'bg-spring-500', widthPct: (b.retirement_minor / this.total) * 100 },
      ];
      if (b.provident_vested_pre2021_minor > 0) {
        out.push({
          key: 'provident',
          label: 'Provident pre-2021',
          valueMinor: b.provident_vested_pre2021_minor,
          colorClass: 'bg-violet-500',
          widthPct: (b.provident_vested_pre2021_minor / this.total) * 100,
        });
      }
      return out;
    },
  },
};
</script>
