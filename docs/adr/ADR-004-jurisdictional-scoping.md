# ADR-004: Jurisdictional Scoping

**Status:** Accepted
**Date:** 2026-04-15

## Context

Fynla must ensure that UK users never see South African UI, SA users never see UK UI, and dual-jurisdiction users get the appropriate combined experience. Jurisdiction is not merely a preference -- it determines which tax rules apply, which products are available, which regulatory disclaimers are shown, and which API endpoints are accessible.

Without explicit scoping, a misconfigured route or a stale cache could expose one country's calculations to another country's users, creating regulatory and accuracy risks.

## Decision

Jurisdictional scoping is mandatory at every layer:

**Database:** A `user_jurisdictions` pivot table tracks each user's active jurisdictions with columns `user_id`, `jurisdiction_id`, `is_primary`, and `activated_at`.

**API:** All country-specific endpoints are prefixed with the country code (`/api/gb/*`, `/api/za/*`). An `ActiveJurisdictionMiddleware` rejects requests targeting a jurisdiction the authenticated user does not have active.

**Frontend:** The core shell inspects the user's active jurisdictions on login and lazy-loads only the corresponding frontend bundles (see ADR-008). Route registration, navigation manifests, and Vuex modules are loaded dynamically based on active jurisdictions.

**Navigation:** The sidebar and module navigation are dynamically composed from each active pack's manifest. A UK-only user sees UK modules; a dual-jurisdiction user sees both, grouped by jurisdiction.

## Consequences

- **Positive:** Zero information leak between jurisdictions. A user cannot accidentally (or deliberately) access another country's endpoints or UI.
- **Positive:** Navigation is dynamically composed, so adding a new country automatically extends the UI for users who activate that jurisdiction.
- **Positive:** URL structure is explicit and auditable (`/api/gb/protection/policies` vs `/api/za/protection/policies`).
- **Negative:** Every country-specific route must include the country prefix. Forgetting the prefix means the route bypasses jurisdiction checks.
- **Negative:** Dual-jurisdiction users require more complex state management (two sets of modules, two tax contexts).
- **Negative:** The `user_jurisdictions` table must be populated correctly during onboarding; an empty table means no access.
