# ADR-009: Pack-Owned Legal Copy

**Status:** Accepted
**Date:** 2026-04-15

## Context

Financial regulations differ fundamentally by jurisdiction:

- **UK** is regulated by the Financial Conduct Authority (FCA). Disclaimers must reference FCA authorisation, the Financial Services Compensation Scheme (FSCS), and the Financial Ombudsman Service (FOS).
- **South Africa** is regulated by the Financial Sector Conduct Authority (FSCA) under the Financial Advisory and Intermediary Services (FAIS) Act. Disclaimers must reference FSCA licence numbers, FAIS compliance, and the FAIS Ombud.

Having regulatory text in core risks showing the wrong jurisdiction's disclaimers -- a compliance violation in both countries. Hardcoding "FCA" anywhere in core means core is UK-specific.

## Decision

Disclaimers, legal copy, and compliance text are pack-owned strings. Core holds no FCA, FSCA, or FAIS wording.

**Implementation:**

1. All regulatory text lives in pack-owned translation files under each pack's `resources/lang/` directory.
2. Core templates provide named placeholder slots (e.g., `@yield('regulatory-disclaimer')`, `{{ $pack->disclaimer('general') }}`). Packs fill these slots with jurisdiction-appropriate text.
3. Each pack registers a `LegalCopyServiceProvider` that binds its disclaimer strings into the container.
4. An architecture test greps core code (excluding packs/) for jurisdiction-specific regulatory terms (`FCA`, `FSCA`, `FAIS`, `FSCS`, `FOS`, `FAIS Ombud`) and fails if any are found.

**Examples:**

| Slot                  | country-gb                                          | country-za                                        |
|-----------------------|-----------------------------------------------------|---------------------------------------------------|
| `regulatory-body`     | Financial Conduct Authority (FCA)                   | Financial Sector Conduct Authority (FSCA)         |
| `compensation-scheme` | Financial Services Compensation Scheme (FSCS)       | (no equivalent -- omitted)                        |
| `ombudsman`           | Financial Ombudsman Service (FOS)                   | FAIS Ombud                                        |
| `general-disclaimer`  | "Fynla is not authorised by the FCA..."             | "Fynla is not a licensed financial services provider under FAIS..." |

## Consequences

- **Positive:** Regulatory accuracy by construction. Each pack owns its own compliance wording.
- **Positive:** Core cannot accidentally display the wrong jurisdiction's legal text.
- **Positive:** Adding a new country cannot break existing regulatory compliance -- the new pack provides its own text.
- **Negative:** Core templates must anticipate which disclaimer slots are needed, requiring coordination with pack developers.
- **Negative:** Some jurisdictions may not have equivalents for all slots (e.g., SA has no FSCS equivalent), so core must handle missing slots gracefully.
