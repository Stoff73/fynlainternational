import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import QuickActions from '@/components/Dashboard/QuickActions.vue';

describe('QuickActions', () => {
  let wrapper;
  let mockRouter;

  beforeEach(() => {
    mockRouter = {
      push: vi.fn(),
    };
  });

  it('renders correctly', () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    expect(wrapper.find('h3').text()).toContain('Quick Actions');
    expect(wrapper.exists()).toBe(true);
  });

  it('displays all 6 quick action buttons', () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    expect(wrapper.vm.actions.length).toBe(6);

    // Check all actions are present
    const actionTitles = wrapper.vm.actions.map(a => a.title);
    expect(actionTitles).toContain('Add Savings Goal');
    expect(actionTitles).toContain('Record Gift');
    expect(actionTitles).toContain('Update Pension Contribution');
    expect(actionTitles).toContain('Add Investment Holding');
    expect(actionTitles).toContain('Check IHT Liability');
    expect(actionTitles).toContain('Update Protection Coverage');
  });

  it('navigates to savings module when "Add Savings Goal" clicked', async () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    await wrapper.vm.handleAction('/savings');
    expect(mockRouter.push).toHaveBeenCalledWith('/savings');
  });

  it('navigates to estate module when "Record Gift" clicked', async () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    await wrapper.vm.handleAction('/estate');
    expect(mockRouter.push).toHaveBeenCalledWith('/estate');
  });

  it('navigates to retirement module when "Update Pension Contribution" clicked', async () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    await wrapper.vm.handleAction('/retirement');
    expect(mockRouter.push).toHaveBeenCalledWith('/retirement');
  });

  it('navigates to investment module when "Add Investment Holding" clicked', async () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    await wrapper.vm.handleAction('/investment');
    expect(mockRouter.push).toHaveBeenCalledWith('/investment');
  });

  it('navigates to estate module when "Check IHT Liability" clicked', async () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    await wrapper.vm.handleAction('/estate');
    expect(mockRouter.push).toHaveBeenCalledWith('/estate');
  });

  it('navigates to protection module when "Update Protection Coverage" clicked', async () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    await wrapper.vm.handleAction('/protection');
    expect(mockRouter.push).toHaveBeenCalledWith('/protection');
  });

  it('displays action icons correctly', () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    const actions = wrapper.vm.actions;

    // Check each action has an icon and color
    actions.forEach(action => {
      expect(action.icon).toBeDefined();
      expect(action.color).toBeDefined();
    });
  });

  it('uses different colors for each action', () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    const colors = wrapper.vm.actions.map(a => a.color);

    // Check we have variety in colors (at least 4 different colors)
    const uniqueColors = new Set(colors);
    expect(uniqueColors.size).toBeGreaterThanOrEqual(4);
  });

  it('displays correct number of action buttons in grid', () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    const buttons = wrapper.findAll('button');
    expect(buttons.length).toBe(6);
  });

  it('action buttons have hover effects', () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    const buttons = wrapper.findAll('button');

    buttons.forEach(button => {
      expect(button.classes()).toContain('quick-action-btn');
      expect(button.classes()).toContain('group');
    });
  });

  it('grid layout is responsive', () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    const grid = wrapper.find('.grid');
    expect(grid.exists()).toBe(true);

    // Check responsive classes
    const gridClasses = grid.classes().join(' ');
    expect(gridClasses).toContain('grid-cols');
  });

  it('each action has a link', () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    const actions = wrapper.vm.actions;

    actions.forEach(action => {
      expect(action.link).toBeDefined();
      expect(action.link).toMatch(/^\//); // Should start with /
    });
  });

  it('action titles are descriptive', () => {
    wrapper = mount(QuickActions, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    const actions = wrapper.vm.actions;

    actions.forEach(action => {
      expect(action.title.length).toBeGreaterThan(10); // Descriptive titles
    });
  });
});
