---
type: prd
workstream: SA pack — Estate module completion
version: v1
date: 2026-07-06
status: autonomous-generated (loop) — CSJ review invited
spec: Plans/za-estate-spec-v1.md
plan: Plans/za-estate-plan-v1.md
---

# PRD — SA Estate Module

## 1. Overview
Wire the already-built SA estate/donations tax engines to the frontend: a
controller + routes exposing estate-duty/CGT-on-death/exemptions, a donations
register for lifetime-donation tracking, and a ZA Estate UI. No new tax math.

## 2. Users
South African users (SA jurisdiction) needing estate-duty visibility and a
SARS donations-tax running total.

## 3. Functional requirements
- FR1: estate-duty summary from the user's SA position (engine-computed).
- FR2: exemptions/reliefs reference; CGT-on-death calculation.
- FR3: donations register CRUD, caller-scoped; cumulative feeds donations tax.
- FR4: `/api/za/estate/*` pack-registered, auth-guarded.

## 4. Non-functional
- Pack isolation, strict_types, int-minor money, no float-money — all enforced
  by the architecture suite. Mirror existing ZA controllers exactly.

## 5. Out of scope
Will-builder/LPA, SA personas, FAIS/POPIA surfaces (separate SA workstreams).

## 6. Success metrics
Spec §5 acceptance table green; architecture + auth-coverage stay green.

## 7. Open questions
- Q1 (slice 1 A2): does an SA net-worth aggregator exist to auto-derive gross
  estate, or does summary take explicit inputs for v1? Default: explicit inputs
  now, wire aggregation in slice 2.
- Q2: donations-tax cumulative window — SARS aggregates since 2018-03-01;
  confirm the register's earliest-date handling with CSJ if pre-2018 donations
  matter (default: register tracks all, aggregation filters >= 2018-03-01).
