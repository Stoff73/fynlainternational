<template>
  <div class="bg-eggshell-500 rounded-lg p-6 border border-savannah-100">
    <h3 class="font-bold text-horizon-500">{{ label }}</h3>
    <p class="mt-2 text-sm text-horizon-300">We need more data to compute this gap.</p>
    <ul class="mt-3 space-y-2">
      <li v-for="input in category.missing_inputs" :key="input">
        <router-link :to="linkFor(input)" class="text-raspberry-500 hover:underline text-sm font-bold">
          → {{ promptFor(input) }}
        </router-link>
      </li>
    </ul>
  </div>
</template>

<script>
export default {
  name: 'ZaMissingInputsEmptyState',
  props: { category: { type: Object, required: true } },
  computed: {
    label() {
      return { life: 'Life cover', idisability_income: 'Income protection',
               dread: 'Dread disease', funeral: 'Funeral cover' }[this.category.category];
    },
  },
  methods: {
    linkFor(input) {
      return { annual_income: '/income', dependants: '/household' }[input] || '/dashboard';
    },
    promptFor(input) {
      return { annual_income: 'Add your annual income', dependants: 'Add your household members' }[input] || 'Complete the prompted module';
    },
  },
};
</script>
