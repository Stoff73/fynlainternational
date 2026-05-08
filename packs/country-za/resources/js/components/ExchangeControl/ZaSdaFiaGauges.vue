<template>
  <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="card p-6">
      <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">
        Single Discretionary Allowance (SDA)
      </div>
      <div class="text-3xl font-black text-horizon-700 mt-2">
        {{ formatZARMinor(remaining.sdaMinor) }} remaining
      </div>
      <div class="mt-4 h-2 bg-horizon-100 rounded-full overflow-hidden">
        <div
          :class="['h-full transition-all duration-500', sdaBarClass]"
          :style="{ width: sdaPct + '%' }"
        />
      </div>
      <div class="mt-2 text-xs text-horizon-400">
        {{ formatZARMinor(consumed.sdaMinor) }} used of
        {{ formatZARMinor(allowances.sda?.annual_limit || 0) }}
      </div>
      <p class="mt-3 text-xs text-horizon-500">{{ allowances.sda?.description }}</p>
    </div>

    <div class="card p-6">
      <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">
        Foreign Investment Allowance (FIA)
      </div>
      <div class="text-3xl font-black text-horizon-700 mt-2">
        {{ formatZARMinor(remaining.fiaMinor) }} remaining
      </div>
      <div class="mt-4 h-2 bg-horizon-100 rounded-full overflow-hidden">
        <div
          :class="['h-full transition-all duration-500', fiaBarClass]"
          :style="{ width: fiaPct + '%' }"
        />
      </div>
      <div class="mt-2 text-xs text-horizon-400">
        {{ formatZARMinor(consumed.fiaMinor) }} used of
        {{ formatZARMinor(allowances.fia?.annual_limit || 0) }}
      </div>
      <p class="mt-3 text-xs text-horizon-500">{{ allowances.fia?.description }}</p>
    </div>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaSdaFiaGauges',
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapGetters('zaExchangeControl', ['allowances', 'consumed', 'remaining']),
    sdaPct() {
      const cap = this.allowances.sda?.annual_limit || 0;
      if (!cap) return 0;
      return Math.min(100, (this.consumed.sdaMinor / cap) * 100);
    },
    fiaPct() {
      const cap = this.allowances.fia?.annual_limit || 0;
      if (!cap) return 0;
      return Math.min(100, (this.consumed.fiaMinor / cap) * 100);
    },
    sdaBarClass() {
      if (this.sdaPct >= 100) return 'bg-raspberry-500';
      if (this.sdaPct >= 75) return 'bg-violet-500';
      return 'bg-spring-500';
    },
    fiaBarClass() {
      if (this.fiaPct >= 100) return 'bg-raspberry-500';
      if (this.fiaPct >= 75) return 'bg-violet-500';
      return 'bg-spring-500';
    },
  },
};
</script>
