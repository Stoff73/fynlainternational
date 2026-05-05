<template>
  <div
    v-if="open"
    class="fixed z-50 inset-0 overflow-y-auto"
    aria-labelledby="confirm-modal-title"
    role="dialog"
    aria-modal="true"
  >
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div
        class="fixed inset-0 bg-savannah-1000 bg-opacity-75 transition-opacity"
        @click="$emit('cancel')"
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full sm:p-6">
        <div>
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full" :class="iconBgClass">
            <svg
              class="h-6 w-6"
              :class="iconColourClass"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"
              />
            </svg>
          </div>

          <div class="mt-3 text-center sm:mt-5">
            <h3
              id="confirm-modal-title"
              class="text-lg leading-6 font-bold text-horizon-500"
            >
              {{ title }}
            </h3>
            <div v-if="message" class="mt-2">
              <p class="text-sm text-neutral-500">{{ message }}</p>
            </div>
          </div>
        </div>

        <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
          <button
            type="button"
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 sm:col-start-2 sm:text-sm"
            :class="confirmButtonClass"
            @click="$emit('confirm')"
          >
            {{ confirmLabel }}
          </button>
          <button
            type="button"
            class="mt-3 w-full inline-flex justify-center rounded-md border border-horizon-200 shadow-sm px-4 py-2 bg-white text-base font-medium text-horizon-500 hover:bg-savannah-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-horizon-500 sm:mt-0 sm:col-start-1 sm:text-sm"
            @click="$emit('cancel')"
          >
            {{ cancelLabel }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ConfirmModal',

  props: {
    open: { type: Boolean, required: true },
    title: { type: String, required: true },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    variant: {
      type: String,
      default: 'danger',
      validator: (v) => ['danger', 'primary'].includes(v),
    },
  },

  emits: ['confirm', 'cancel'],

  computed: {
    iconBgClass() {
      return this.variant === 'danger' ? 'bg-raspberry-100' : 'bg-violet-100';
    },
    iconColourClass() {
      return this.variant === 'danger' ? 'text-raspberry-600' : 'text-violet-600';
    },
    confirmButtonClass() {
      return this.variant === 'danger'
        ? 'bg-raspberry-600 hover:bg-raspberry-700 focus:ring-raspberry-500'
        : 'bg-raspberry-500 hover:bg-raspberry-600 focus:ring-raspberry-500';
    },
  },
};
</script>
