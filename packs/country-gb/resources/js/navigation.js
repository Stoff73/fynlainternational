/**
 * GB pack — sidebar navigation manifest.
 *
 * Returns the modules the UK pack contributes to the sidebar. The current
 * UK sidebar (`SideMenu.vue`) renders its items from a hardcoded template,
 * so the GB manifest only carries the flat module-name list consumed by
 * the `sidebarModules` getter (route guards, feature gating). When R-13a
 * converts the UK sidebar to data-driven rendering, this file gains the
 * richer `rootItems` + `sections` shape without touching consumers.
 *
 * Pack ↔ core contract: each active pack supplies a default-exported
 * `navigation()` thunk; the core `jurisdiction` Vuex module collects them
 * via the `PACK_NAVIGATIONS` map and the sidebar reads from the resulting
 * getters. No `App\*` or core imports — this file is data only.
 */
export default function navigation() {
    return {
        code: 'gb',
        modules: [
            'protection',
            'savings',
            'investment',
            'retirement',
            'estate',
            'goals',
            'coordination',
        ],
    };
}
