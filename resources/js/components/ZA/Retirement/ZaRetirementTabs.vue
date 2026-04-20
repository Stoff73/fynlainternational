<template>
  <div class="border-b border-horizon-100 mb-6">
    <nav class="flex gap-6" aria-label="Retirement sections">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="py-3 px-1 text-sm font-semibold border-b-2 transition-colors"
        :class="t.key === active
          ? 'border-raspberry-500 text-raspberry-500'
          : 'border-transparent text-horizon-500 hover:text-horizon-700'"
        :aria-selected="t.key === active"
        @click="$emit('change', t.key)"
      >
        {{ t.label }}
      </button>
    </nav>
  </div>
</template>

<script>
export default {
  name: 'ZaRetirementTabs',
  props: {
    active: { type: String, required: true },
  },
  emits: ['change'],
  data() {
    return {
      tabs: [
        { key: 'accumulation', label: 'Accumulation' },
        { key: 'decumulation', label: 'Decumulation' },
        { key: 'compliance', label: 'Compliance' },
      ],
    };
  },
};
</script>
