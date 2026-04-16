import { config } from '@vue/test-utils';

// Global test setup
config.global.mocks = {
  $route: {
    params: {},
    query: {},
  },
  $router: {
    push: () => {},
    replace: () => {},
  },
};

// Mock ApexCharts
global.ApexCharts = class {
  constructor() {}
  render() {}
  updateOptions() {}
  updateSeries() {}
  destroy() {}
};

// Mock apexchart Vue component
config.global.stubs = {
  apexchart: {
    template: '<div class="apexchart-mock"></div>',
    props: ['options', 'series', 'type', 'height', 'width'],
  },
};
