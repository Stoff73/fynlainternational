<template>
  <div>
    <select :value="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.value)"
            class="w-full border border-savannah-200 rounded-md p-2">
      <option v-for="t in policyTypes" :key="t.code" :value="t.code">{{ t.name }} — {{ t.description }}</option>
    </select>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';

export default {
  name: 'ZaPolicyTypeSelector',
  props: {
    modelValue: { type: String, required: true },
    disabled: { type: Boolean, default: false },
  },
  emits: ['update:modelValue'],
  computed: { ...mapState('zaProtection', ['policyTypes']) },
  async mounted() { if (!this.policyTypes.length) await this.fetchPolicyTypes(); },
  methods: { ...mapActions('zaProtection', ['fetchPolicyTypes']) },
};
</script>
