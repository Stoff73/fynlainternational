# ADR-008: Frontend Lazy-Loading per Active Pack

**Status:** Accepted
**Date:** 2026-04-15

## Context

Each country pack has substantial frontend code: Vue components, Vuex store modules, route definitions, and service layers. The current UK-only application has 663 Vue components. Adding South Africa could nearly double the frontend bundle size.

Loading all countries' UI code for every user wastes bandwidth, increases initial load time, and exposes irrelevant UI elements. A UK-only user should never download SA components, and vice versa.

## Decision

Frontend feature bundles are lazy-loaded per active pack. A UK-only user never downloads the SA bundle.

**Structure:** Each pack provides a frontend entry point that exports routes, store modules, and a navigation manifest:

```
packs/country-gb/resources/js/
  index.js          # Exports { routes, storeModules, navManifest }
  components/       # UK-specific Vue components
  store/            # UK-specific Vuex modules
  services/         # UK-specific API services
```

**Loading flow:**

1. User authenticates. The API returns their active jurisdiction codes (e.g., `["gb"]` or `["gb", "za"]`).
2. The core shell dynamically imports only the matching pack bundles: `import(`@fynla/country-${code}/resources/js`)`.
3. Routes from each loaded pack are registered with Vue Router via `router.addRoute()`.
4. Vuex modules from each loaded pack are registered via `store.registerModule()`.
5. Navigation items from each loaded pack's manifest are merged into the sidebar.

**Vite configuration:** Each pack's frontend is configured as a separate entry in `vite.config.js`, producing independent chunks that are only fetched when needed.

## Consequences

- **Positive:** Optimal bundle size per user. A UK-only user downloads only core + UK chunks.
- **Positive:** Country-specific code is code-split automatically by Vite.
- **Positive:** Adding a new country does not increase bundle size for existing users.
- **Negative:** Requires a dynamic module registration pattern in Vue Router and Vuex, adding complexity to the core shell.
- **Negative:** Core shell must handle async pack loading gracefully (loading states, error boundaries for failed chunk loads).
- **Negative:** Hot module replacement during development must work across pack boundaries, which may need Vite plugin configuration.
