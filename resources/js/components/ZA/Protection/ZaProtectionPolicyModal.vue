<template>
  <teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center" @click.self="$emit('close')">
      <div class="absolute inset-0 bg-horizon-500/40" aria-hidden="true" />
      <div role="dialog" aria-modal="true" :aria-labelledby="titleId"
           class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden flex flex-col"
           @keydown.esc="$emit('close')">
        <header class="px-6 py-4 border-b border-savannah-100 flex justify-between items-center">
          <h2 :id="titleId" class="text-xl font-bold text-horizon-500">{{ title }}</h2>
          <button type="button" class="text-horizon-300 hover:text-horizon-500 text-2xl leading-none"
                  aria-label="Close dialog" @click="$emit('close')">×</button>
        </header>
        <div class="overflow-y-auto flex-1">
          <ZaProtectionPolicyForm
            :existing-policy="policy"
            @save="(payload) => $emit('save', payload)"
            @close="$emit('close')"
          />
        </div>
      </div>
    </div>
  </teleport>
</template>

<script>
import ZaProtectionPolicyForm from './ZaProtectionPolicyForm.vue';

export default {
  name: 'ZaProtectionPolicyModal',
  components: { ZaProtectionPolicyForm },
  props: {
    open: { type: Boolean, required: true },
    policy: { type: Object, default: null },
  },
  emits: ['update:open', 'save', 'close'],
  data() {
    return { titleId: `dialog-title-${Math.random().toString(36).slice(2, 9)}` };
  },
  computed: {
    title() { return this.policy ? 'Edit policy' : 'Add policy'; },
  },
};
</script>
