<template>
  <div class="bg-white rounded-lg p-6 border border-savannah-100">
    <header class="flex justify-between items-baseline mb-4">
      <h3 class="font-bold text-horizon-500">{{ label }}</h3>
      <span v-if="category.shortfall_minor > 0" class="text-sm font-bold text-raspberry-500">Shortfall</span>
      <span v-else class="text-sm font-bold text-spring-500">On track</span>
    </header>
    <div class="h-3 bg-savannah-100 rounded-full overflow-hidden">
      <div class="h-full bg-raspberry-500 transition-all" :style="{ width: pct + '%' }" />
    </div>
    <dl class="mt-4 grid grid-cols-3 gap-2 text-sm">
      <div><dt class="text-horizon-300">Recommended</dt><dd class="font-bold">{{ formatZAR(category.recommended_cover_major) }}</dd></div>
      <div><dt class="text-horizon-300">Existing</dt><dd class="font-bold">{{ formatZAR(category.existing_cover_major) }}</dd></div>
      <div><dt class="text-horizon-300">Shortfall</dt><dd class="font-bold text-raspberry-500">{{ formatZAR(category.shortfall_major) }}</dd></div>
    </dl>
    <ZaCoverageRationalePanel :rationale="category.rationale" />
  </div>
</template>

<script>
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';
import ZaCoverageRationalePanel from './ZaCoverageRationalePanel.vue';

export default {
  name: 'ZaCoverageGaugeCard',
  components: { ZaCoverageRationalePanel },
  mixins: [zaCurrencyMixin],
  props: { category: { type: Object, required: true } },
  computed: {
    label() {
      return { life: 'Life cover', idisability_income: 'Income protection',
               dread: 'Dread disease', funeral: 'Funeral cover' }[this.category.category];
    },
    pct() {
      if (!this.category.recommended_cover_minor) return 0;
      const v = (this.category.existing_cover_minor / this.category.recommended_cover_minor) * 100;
      return Math.min(100, Math.max(0, Math.round(v)));
    },
  },
};
</script>
