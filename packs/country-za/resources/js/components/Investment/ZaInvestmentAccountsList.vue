<template>
  <section class="card p-6">
    <header class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-bold text-horizon-700">Your accounts</h2>
      <span class="text-sm text-horizon-400">
        {{ accounts.length }} account{{ accounts.length === 1 ? '' : 's' }}
      </span>
    </header>
    <div v-if="isLoading" class="py-8 text-center text-horizon-400">Loading…</div>
    <div v-else-if="!accounts.length" class="py-8 text-center text-horizon-400">
      No South African investment accounts yet. Click "Add account" above to record one.
    </div>
    <ul v-else class="divide-y divide-light-gray">
      <li
        v-for="account in accounts"
        :key="account.id"
        class="py-3 flex items-center justify-between"
      >
        <div>
          <div class="font-semibold text-horizon-700">
            {{ account.account_name || account.provider }}
          </div>
          <div class="text-xs text-horizon-400 mt-0.5">
            <span
              :class="badgeClass(account.account_type)"
              class="inline-block px-2 py-0.5 rounded font-bold uppercase tracking-wide mr-2"
            >
              {{ wrapperLabel(account.account_type) }}
            </span>
            {{ account.provider }}<span v-if="account.platform"> · {{ account.platform }}</span>
          </div>
        </div>
        <div class="text-right">
          <div class="font-bold text-horizon-700">{{ formatZAR(account.current_value) }}</div>
        </div>
      </li>
    </ul>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaInvestmentAccountsList',
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapGetters('zaInvestment', ['accounts', 'isLoading']),
  },
  methods: {
    wrapperLabel(code) {
      return { tfsa: 'TFSA', discretionary: 'Discretionary', endowment: 'Endowment' }[code] || code;
    },
    badgeClass(code) {
      return (
        {
          tfsa: 'bg-spring-100 text-spring-700',
          discretionary: 'bg-horizon-100 text-horizon-700',
          endowment: 'bg-violet-100 text-violet-700',
        }[code] || 'bg-light-gray text-horizon-700'
      );
    },
  },
};
</script>
