/**
 * ZA Protection store module. Placeholder until WS 1.5b frontend lands.
 * Shape mirrors zaSavings so parallel workstreams can extend without
 * breaking the registration contract in store/index.js.
 */
const state = () => ({
  loaded: false,
  error: null,
});

const getters = {
  isLoaded: (state) => state.loaded,
};

const actions = {
  reset({ commit }) {
    commit('RESET');
  },
};

const mutations = {
  RESET(state) {
    state.loaded = false;
    state.error = null;
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
