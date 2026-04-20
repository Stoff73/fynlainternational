<template>
  <details class="mt-4 text-sm">
    <summary class="cursor-pointer text-horizon-300 hover:text-horizon-500">Why this number?</summary>
    <p class="mt-2 text-horizon-300">{{ rationale }}</p>
  </details>
</template>

<script>
export default {
  name: 'ZaCoverageRationalePanel',
  props: { rationale: { type: String, required: true } },
};
</script>
