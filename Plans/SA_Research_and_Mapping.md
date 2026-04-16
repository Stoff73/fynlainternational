Fynla SA
South African adaptation of Fynla
Research & mapping document — v1.0
Prepared for: Chris Slater-Jones
Date: 15 April 2026
Tax year in scope: 2026/27 (1 March 2026 – 28 February 2027)
 
1. Executive summary
Fynla SA is a separate South African application forked from the UK Fynla codebase. It keeps Fynla’s architecture, design language and module structure, but replaces every UK-specific rule, rate and product with its South African equivalent. The brief is to cover SARS personal income tax, the two-pot retirement system, exchange control, tax-free savings accounts, offshore investment, estate duty and the FSCA-regulated product set.
The work naturally splits into six streams:
•	A new Tax Engine built around the 2026/27 SARS tables, rebates, Section 11F retirement deduction, Section 10C tax-free annuity rollover, CGT at 40% inclusion, dividends withholding tax, interest exemption and retirement lump sum tables.
•	A new Retirement domain covering Retirement Annuities, Pension Funds, Provident Funds, Preservation Funds, the Two-Pot System (savings / retirement / vested components), Regulation 28 asset class limits, and Living vs Life annuities with the 2.5%–17.5% drawdown band.
•	A new Exchange Control module tracking the R2m Single Discretionary Allowance (SDA) and R10m Foreign Investment Allowance (FIA) per calendar year, with tax clearance workflow for amounts over the SDA.
•	A reworked Investment module with a Tax-Free Savings Account (annual R46,000 from 1 March 2026, lifetime R500,000), discretionary unit trusts/ETFs, endowments (section 29A), and offshore portfolios.
•	A simplified Protection module focused on life, disability (lump-sum and income), dread disease, and funeral cover — replacing the UK whole-of-life/term/critical illness framing.
•	A reworked Estate Planning module using estate duty (20% / 25% with R3.5m abatement and spousal roll-over), donations tax, CGT on death, and executor / Master’s fees.
This document is the foundation for the fork. It is deliberately exhaustive so that when we lift code from the UK repo, we know exactly which constants, services, controllers, Vuex stores and Vue components need to change.
 
2. Contents
1. Executive summary
2. Contents
3. Programme scope & product positioning
4. Regulatory landscape (SARS, FSCA, SARB, Pension Funds Act, FICA, POPIA)
5. Tax engine — SA 2026/27 rules
6. Module mapping: Protection
7. Module mapping: Savings & Tax-Free Savings Account
8. Module mapping: Investment (incl. Exchange Control & offshore)
9. Module mapping: Retirement (RA / PF / PvF / Preservation / Two-Pot / Reg 28 / Annuities)
10. Module mapping: Estate Planning (Estate Duty, Donations Tax, CGT on death)
11. Module mapping: Goals & Life Events
12. Module mapping: Coordination (cross-domain aggregation)
13. New SA-only modules (Two-Pot Tracker, Exchange Control Ledger, Reg 28 Monitor)
14. Technical architecture — what changes, what stays
15. Code-level impact inventory (services, controllers, stores, components, seeders)
16. Data model & database migrations
17. Localisation: currency, date, language, persona set
18. Compliance, disclosure & FAIS considerations
19. Phased delivery plan
20. Risks, open questions & out of scope
21. Appendix A — SARS tax tables 2026/27
22. Appendix B — Retirement lump-sum tax tables
23. Appendix C — Exchange control allowances
24. Appendix D — Glossary UK ↔ SA
25. Appendix E — Sources
 
3. Programme scope & product positioning
3.1 Product name and brand
Working name: Fynla SA (also ‘Fynla ZA’). The brand keeps the Fynla mark, design system (raspberry primary / horizon navy / spring green / violet warning / savannah hover) and tone of voice. The ‘.org’ suffix is reserved for the UK instance; production is expected to live at a dedicated .co.za domain (e.g. fynla.co.za) with the UK instance remaining at fynla.org. The mobile Capacitor iOS app becomes a second bundle identifier — they are independent apps on the App Store, not a regional variant of a single app.
3.2 Repository strategy
A separate GitHub repository (fynla-sa) is created as a one-time copy from Fynla at a known commit. UK and SA evolve independently from that point forward. Shared design decisions can be cherry-picked across, but there is no shared codebase. This is the simplest approach and avoids having to thread a ‘country’ flag through 233 PHP services and 660 Vue components, at the cost of some duplicated generic work (auth flows, layout, UI primitives).
3.3 Launch modules
v1 scope (user-confirmed):
•	Protection — life, disability (lump sum + income), dread disease, funeral cover
•	Savings — emergency fund, fixed deposits, notice deposits, money market, Tax-Free Savings Account
•	Investment — discretionary unit trusts / ETFs, endowments, offshore (SDA / FIA) with CGT tracking
•	Retirement — Retirement Annuity, Pension Fund, Provident Fund, Preservation Fund, Living Annuity, Life Annuity, Two-Pot balances
Dependent-but-essential modules added to v1 because they are structurally required:
•	Tax Engine — without it none of the other modules produce meaningful numbers
•	Estate Planning — estate duty sits on top of every asset in the system; it cannot be deferred
•	Goals & Life Events — re-used largely as-is (retirement date, home purchase, education funding)
•	Coordination — the cross-domain aggregation agent that powers the dashboard
3.4 Target personas (to be seeded)
Persona	Profile	Focus modules
Young professional	Single, 28, R480k salary, first TFSA, SIPP equivalent (RA)	Savings, TFSA, RA, Protection
Young family	Couple, 35, children, bond, employer Pension Fund	Protection, Retirement, Goals (education)
Peak earners	Couple, 48, multiple properties, RA + discretionary + offshore	Investment, Retirement, Estate, Exchange Control
Pre-retiree	Age 58, preservation fund, living annuity planning	Retirement, Living Annuity, Estate
Retiree (decumulation)	Age 68, living annuity + life annuity split	Retirement, Estate
Expat / financial emigrant	Tax resident elsewhere, SA-sourced RA	Exchange Control, Retirement, Estate
 
4. Regulatory landscape
Fynla SA sits inside a regulatory stack that is materially different from the UK. The following bodies and statutes directly shape the product:
Body / statute	Scope	Impact on Fynla SA
SARS (South African Revenue Service)	Personal and corporate tax, CGT, dividends tax, estate duty, donations tax, retirement fund deductions	Source of every rate in the Tax Engine
National Treasury	Annual Budget (tabled in February), Taxation Laws Amendment Act	Drives annual refresh of tax tables and allowances
FSCA (Financial Sector Conduct Authority)	Market conduct for banks, insurers, retirement funds, CIS, advisers	Disclosure rules, FAIS compliance if Fynla ever offers advice vs guidance
SARB (South African Reserve Bank)	Exchange control (FinSurv), prudential regulation	SDA / FIA limits, tax clearance process
Pension Funds Act, 1956 (incl. Reg 28)	Retirement funds regulation	Regulation 28 asset-class limits, two-pot rules, preservation
Long-term Insurance Act / Insurance Act 18 of 2017	Life, disability, dread, funeral, endowments	Protection module product catalogue
Collective Investment Schemes Control Act	Unit trusts, ETFs	Investment module product catalogue
Income Tax Act 58 of 1962	Core tax statute (Sections 11F, 10C, 10(1)(i), Eighth Schedule)	Tax Engine formulas
FICA (Financial Intelligence Centre Act)	KYC, AML	On-boarding flow, document upload UI
POPIA (Protection of Personal Information Act)	Data protection	Privacy notices, data export, retention policy
FAIS Act	Financial advisers and intermediaries	Licensing question: Fynla SA is information / planning, not advice; disclaimers must be explicit
Conduct of Financial Institutions (COFI) Bill	Emerging — replaces parts of the conduct regime	Watch-item; no immediate code change but monitor
Key product-level implication: Fynla SA must display a prominent disclaimer that it provides information and planning tools, not FSCA-licensed financial advice. All calculations should state the underlying assumptions and rate year. This wording should live in a new GuidanceDisclaimer.vue component consumed by every module landing page.
 
5. Tax engine — SA 2026/27 rules
The UK Tax Engine (TaxConfigService, TaxDefaults constant, TaxConfigurationSeeder) is ripped out and rebuilt for SA. Every hardcoded UK value — 40% IHT, £325k NRB, £20k ISA, £60k pension AA — disappears.
5.1 SARS personal income tax — 2026/27 brackets
Taxable income (R)	Rate
1 – 245,100	18% of each R1
245,101 – 383,100	R44,118 + 26% of amount above R245,100
383,101 – 530,200	R79,998 + 31% of amount above R383,100
530,201 – 695,800	R125,599 + 36% of amount above R530,200
695,801 – 887,000	R185,215 + 39% of amount above R695,800
887,001 – 1,878,600	R259,783 + 41% of amount above R887,000
1,878,601 and above	R666,339 + 45% of amount above R1,878,600
5.2 Rebates and tax thresholds — 2026/27
Item	Under 65	65 – 75	75+
Tax threshold (tax-free income)	R99,000	R148,217	R165,689
Primary rebate (implied)	R17,820	R17,820	R17,820
Secondary rebate (65+)	—	Applies	Applies
Tertiary rebate (75+)	—	—	Applies
Notes: the published tax thresholds are derived from rebates. Fynla SA should store rebates as the source of truth and derive thresholds, mirroring the SARS tables. The 2026/27 bracket fiscal drag adjustment was ~3.4%.
5.3 Other key taxes
Tax	2026/27 value / rule
Interest exemption (under 65)	R23,800 p.a.
Interest exemption (65+)	R34,500 p.a.
Dividends withholding tax (DWT)	20% on local dividends to SA residents
Foreign dividends	Effective 20% (via Section 10B partial exemption — 25/45 of gross)
Interest WHT (non-residents)	15% (outside scope for SA-resident users)
Capital gains tax inclusion rate (individuals)	40% (effective top rate ~18%)
Annual CGT exclusion (individuals)	R40,000
CGT primary residence exclusion	R2,000,000 on disposal
CGT exclusion on death	R300,000
Medical tax credit — main + first dependant	R376 per month each
Medical tax credit — additional dependants	R254 per month each
Section 11F retirement deduction	27.5% of greater of remuneration or taxable income, capped R350,000 p.a.
Section 10C tax-free annuity rollover	Applies to non-deductible retirement contributions
UIF contribution (employer + employee)	2% total (1% + 1%), capped at R177.12/month each
SDL (employer only)	1% of payroll (skills development levy)
5.4 Retirement lump sum tables — recap
Retirement lump sum (on retirement, cumulative across post-2007 withdrawals):
Lump sum (R)	Tax
0 – 550,000	0%
550,001 – 770,000	18% of amount above R550,000
770,001 – 1,155,000	R39,600 + 27% of amount above R770,000
1,155,001 and above	R143,550 + 36% of amount above R1,155,000
Withdrawal lump sum (pre-retirement withdrawal, resignation, divorce, and savings-pot withdrawals above de minimis):
Withdrawal (R)	Tax
0 – 27,500	0%
27,501 – 726,000	18% of amount above R27,500
726,001 – 1,089,000	R125,730 + 27% of amount above R726,000
1,089,001 and above	R223,740 + 36% of amount above R1,089,000
Important: a Savings Pot withdrawal (under the Two-Pot system) is NOT taxed on the withdrawal table — it is added to the member’s taxable income in that tax year and taxed at their marginal rate. This is one of the most common misconceptions in the market and a mandatory disclaimer in the Two-Pot module.
5.5 Estate duty and donations tax
Item	Rule
Estate duty abatement (section 4A)	R3,500,000 — portable between spouses (effective R7m on second death)
Estate duty rate	20% on dutiable value up to R30m; 25% above R30m
Donations tax annual exemption	R100,000 per donor per tax year (natural persons)
Donations tax rate	20% on cumulative donations since 1 March 2018 up to R30m; 25% above R30m
Spousal exemption (estate duty and donations tax)	Unlimited between spouses
Executor’s fee (maximum tariff)	3.5% of gross estate + 6% of income earned during administration, plus VAT
Master’s fees	Sliding scale, capped at R7,000 in most estates
Note: some recent SARS guidance restates the natural-person donations tax annual exemption at R100,000. Fynla SA should treat this as a single constant (DonationsTax::ANNUAL_EXEMPTION) sourced from TaxConfigurationSeeder so it can be adjusted in the annual Budget refresh.
 
6. Module mapping: Protection
The UK Protection module assumes term life, whole-of-life, critical illness, income protection and family income benefit written in trust. SA market practice differs on several structural points.
6.1 Product catalogue
UK product (remove)	SA replacement	Notes
Term life (level / decreasing)	Life cover (level / decreasing)	Largely equivalent, different branding
Whole-of-life	Whole-of-life	Much less common in SA retail; keep but deprioritise
Critical illness	Dread disease	Different severity-based payouts (A/B/C/D tiers per ASISA SCIDEP)
Income protection (PHI)	Income protection / disability income	Typically expressed as % of gross/net income; 2-year or to-age-65 policies
Family income benefit	Monthly annuity life cover	Available but niche
—	Lump-sum disability	New product — pays on permanent disability
—	Funeral cover	New product — small policies, often short-term underwritten; cover whole family
Life policies in trust	Life policies with nominated beneficiary / estate / testamentary trust	SA has no ‘trust wrapper’ equivalent to the UK; beneficiary nomination works differently for estate duty
6.2 Tax treatment of premiums and payouts
•	Life cover premiums: generally not deductible for individuals; payouts to nominated beneficiaries generally free of income tax.
•	Policies payable to the estate are dutiable; policies payable to a nominated beneficiary (spouse or other) with correct wording are not dutiable under section 3(3)(a)(ii) exclusions (subject to the policy meeting the exclusion criteria).
•	Income protection: premiums no longer deductible (change from pre-1 March 2015 rules); payouts are tax-free.
•	Dread disease: lump-sum payouts are tax-free.
•	Key-person / buy-and-sell cover: separate tax treatment — out of scope for v1.
6.3 Coverage calculators
UK heuristics (e.g. 10x salary for life cover, 60–70% of net pay for income protection) translate loosely, but the SA version should use:
•	Life cover adequacy: capitalise dependants’ lifestyle need + outstanding bond + education capital + estate costs (including estate duty and 6% executor income fee) – existing liquid assets – group life.
•	Income protection: 75% of gross pay (typical insurer cap) vs actual net income after tax / UIF; factor Section 11F pension contributions that continue under the policy.
•	Dread disease: typically 1–3x annual salary or a fixed rand amount; allow severity-based projection.
•	Funeral cover: R10k – R100k per life; include family / extended family members.
6.4 Data model changes
•	Extend protection_policies table: add product_type enum (life, whole_of_life, dread, idisability_lump, idisability_income, funeral, key_person)
•	Add severity_tier (nullable) for dread disease
•	Add waiting_period_months and benefit_term_months for income protection
•	Replace ‘written in trust’ boolean with beneficiary_type enum (estate, spouse, nominated_individual, testamentary_trust, inter_vivos_trust)
 
7. Module mapping: Savings & Tax-Free Savings Account
7.1 Replace the ISA with the TFSA
The entire ISA framework disappears. It is replaced by a single Tax-Free Savings Account abstraction with very different limits and rules.
Item	UK ISA (remove)	SA TFSA (add)
Annual limit	£20,000	R46,000 (from 1 March 2026)
Lifetime limit	None	R500,000
Sub-types	Cash, Stocks & Shares, Lifetime, Innovative Finance, Junior	Single TFSA (cash or investment), minor TFSAs allowed
Over-subscription penalty	Tax on excess income	40% flat penalty on contributions over the annual or lifetime cap
Transfer rules	Provider-to-provider transfer preserves allowance	Transfers now permitted (since 2018); contributions in a year still count to limit
Aggregation	Per tax year across wrappers	Across all providers and all account types in aggregate
Tax treatment	Tax-free growth + withdrawals	Tax-free interest, dividends, CGT + withdrawals
7.2 Non-TFSA savings products
•	Transactional / call account — interest taxable at marginal rate, subject to interest exemption
•	Notice deposit (7 / 32 / 90 days)
•	Fixed deposit (3m – 5y)
•	Money Market fund (CIS) — interest + capital, governed by CISCA
•	RSA Retail Savings Bonds (Fixed and Inflation-Linked) — direct from Treasury, R1,000 minimum
•	Endowment (section 29A) — 30% tax rate within the wrapper, 5-year restriction period, used by higher-rate taxpayers
7.3 Emergency fund heuristic
UK default is 3–6 months of essential expenditure. SA default remains 3–6 months but the adequacy calculator must pull from the ZAR Budget / Expenditure data, include UIF-replaceable income appropriately, and weight towards 6 months for single-income households given higher SA unemployment risk.
 
8. Module mapping: Investment (incl. Exchange Control & offshore)
8.1 Local investment products
Product	Regulator / statute	Tax wrapper	Notes
Unit trusts (CIS)	FSCA / CISCA	Discretionary	Distributions taxed at marginal rate; CGT on disposal
ETFs	FSCA / JSE	Discretionary or TFSA	Same tax rules as unit trusts
Direct equities (JSE)	FSCA / JSE	Discretionary	DWT on dividends, CGT on disposal
Retail savings bonds	Treasury	Discretionary	Interest exemption applies
Structured products / hedge funds	FSCA	Varied	Out of scope for v1
Endowment policies	Prudential / Insurance Act	Wrapper	Effective 30% tax within; 5-year rule
8.2 Offshore investment — the exchange control regime
This is the biggest net-new module relative to the UK code. UK Fynla has nothing equivalent.
Allowance	Per-person annual limit	Requirements
Single Discretionary Allowance (SDA)	R2,000,000 per calendar year (doubled from R1m in 2026)	No SARS tax clearance; online declaration with authorised dealer; any purpose (travel, offshore investment, gifts to non-residents, etc.)
Foreign Investment Allowance (FIA)	R10,000,000 per calendar year	SARS Approval for International Transfer (AIT) required; tax-compliance history checks; only for investment purposes
Above R12m combined	On application	SARB special approval through authorised dealer
A new ExchangeControlLedger table tracks SDA and FIA consumption per calendar year per user. Because the limit resets on 1 January (not 1 March or 6 April), the ledger must key off calendar year not SA tax year — this is an easy source of bugs when carried across from the UK tax-year logic.
•	ExchangeControlLedger columns: user_id, calendar_year, allowance_type (sda / fia), amount_zar, destination_country, purpose, authorised_dealer, ait_reference (nullable), transfer_date
•	Running totals surface in the Investment dashboard as ‘SDA used this year: R450,000 of R2,000,000’
•	When a user schedules a transfer above their remaining SDA, Fynla SA surfaces the FIA requirement and the AIT workflow
8.3 Capital gains tax on discretionary investments
•	Track base cost per lot (SA uses weighted-average or specific identification depending on product)
•	Apply 40% inclusion on realised gains for individuals
•	Apply annual R40,000 exclusion
•	Primary residence R2m exclusion (handled in Property, not Investment)
•	Death R300,000 exclusion (handled in Estate)
•	Currency gains on offshore assets: gain in foreign currency, translated at average / spot rates per Eighth Schedule rules
8.4 Removals and renames from UK Investment module
•	Remove: GIA (General Investment Account), VCT, EIS, SEIS, SSAS references, Bond ISA
•	Remove: UK CGT annual exemption (£3,000) constants
•	Remove: Dividend allowance (£500) and personal savings allowance (£500/£1,000/£0)
•	Rename: ‘GIA’ → ‘Discretionary portfolio’
•	Rename: ‘Platform’ → ‘LISP’ (Linked Investment Service Provider) for SA investment platforms
 
9. Module mapping: Retirement
The Retirement module is the most surgery-heavy of the migration. The UK pillar structure (State Pension, DC workplace, DB, SIPP, SSAS, drawdown, UFPLS, annuity, LSA / LSDBA) is entirely removed. It is replaced by the SA three-funds + two-pot model.
9.1 SA retirement fund types
Fund	Sponsor	Contribution deduction	Access at retirement
Pension Fund	Employer	Section 11F (capped)	Up to 1/3 cash + 2/3 compulsory annuity (see §9.3)
Provident Fund (post-1 March 2021 portion)	Employer	Section 11F (capped)	Same rules as Pension Fund — fully harmonised for contributions after 1 March 2021
Provident Fund (vested pre-1 March 2021 portion)	Employer	Historical	Still commutable 100% for members 55+ on 1 March 2021
Retirement Annuity (RA)	Individual	Section 11F (capped)	Same 1/3 / 2/3 rule; accessible from age 55
Pension / Provident Preservation Fund	Individual (after leaving employment)	N/A	One full/partial withdrawal allowed (pre-two-pot portion only); rest at retirement
GEPF (Government Employees Pension Fund)	State	Members don’t deduct; employer contributes	Defined benefit — out of scope for v1 calc engine but must be recorded
Umbrella funds	Multi-employer	Section 11F (capped)	Same as sponsor type
9.2 Two-Pot System (effective 1 September 2024)
All contributions to pension, provident and retirement annuity funds from 1 September 2024 split into:
•	1/3 → Savings Component — accessible once per tax year, minimum R2,000, taxed at member’s marginal rate
•	2/3 → Retirement Component — locked until retirement; must be used for compulsory annuity
Pre-1 September 2024 balances remain in the Vested Component and keep their old rules. A one-off seeding of 10% (capped at R30,000) of the vested balance was moved into the Savings Component at implementation. There is no further seeding.
Fynla SA data model needs three balance buckets per fund per member: vested_balance, savings_balance, retirement_balance. The UI distinguishes each in the retirement dashboard and warns when a Savings Pot withdrawal would push the member into a higher marginal bracket.
9.3 Compulsory annuitisation rules at retirement
•	1/3 may be taken as a lump sum (taxed per §5.4 retirement lump sum table)
•	2/3 must be used to buy a compulsory annuity (living or life)
•	De minimis: if the combined vested + retirement component total is under R165,000 the member may commute the full amount
•	The Retirement Component of the Two-Pot system cannot be commuted regardless of overall size once the two-pot regime applies — it must buy an annuity
•	Lifetime cumulative rule: all post-October 2007 retirement lump sums aggregate for the purposes of the retirement lump sum table
9.4 Living annuity mechanics
•	Drawdown band: 2.5% to 17.5% of the capital value, per policy anniversary
•	Drawdown election once per year on policy anniversary
•	No Regulation 28 restriction in a living annuity (the member can hold up to 100% offshore if the LISP allows)
•	On death, remaining capital passes to nominated beneficiaries (option for cash lump sum taxed on retirement lump sum table, or continuation as their living annuity)
•	Capital is not dutiable for estate duty (important planning point)
9.5 Life (guaranteed) annuity mechanics
•	Fixed, with-profit, escalating, joint-and-survivor, or level options
•	Income is taxable at marginal rate; Section 10C exemption applies to the component attributable to non-deductible contributions
•	On death, capital extinguishes (guarantee terms aside)
9.6 Regulation 28 asset-class limits (applies to pre-retirement funds only)
Asset class	Regulation 28 maximum
Offshore (all foreign assets including African)	45%
Equities (local + foreign combined)	75%
Property	25%
Private equity	15%
Commodities (incl. gold)	10%
Hedge funds	10%
Other / alternative assets	2.5%
Exposure to a single entity	25%
Fynla SA includes a Reg 28 Monitor: for each retirement fund holding, roll up the look-through asset allocation and flag breaches. For umbrella-fund DC members this is usually handled by the administrator, but RA and preservation fund members on LISPs often select their own funds and need the check.
9.7 Section 11F and Section 10C
•	Section 11F: contribution deduction = 27.5% of greater of remuneration or taxable income, capped at R350,000 per tax year
•	Excess contributions roll over to future years as further deductions, and any remaining balance at retirement converts into an exemption against annuity income under Section 10C
•	The rollover balance is a per-member stateful amount — the Tax Engine must track ‘unclaimed section 11F deduction carry-forward’ and apply it automatically
9.8 Government Old Age Grant (SASSA)
•	Means-tested, not a UK-style state pension
•	Age 60+ with asset and income tests
•	Modest grant — the product surfaces it as a potential top-up for low-income retirees; full mechanics are out of scope for v1 but the data field is added
9.9 Unclaimed benefits
•	FSCA maintains an Unclaimed Benefits search facility
•	Fynla SA should link out to this from the Retirement landing page — common scenario for members who changed jobs and lost track of preserved benefits
 
10. Module mapping: Estate Planning
10.1 Replace IHT with Estate Duty
UK (remove)	SA (add)
IHT at 40% above £325k NRB + £175k RNRB	Estate duty at 20% up to R30m dutiable value; 25% above R30m
Residence Nil Rate Band (main home to descendants)	No equivalent — primary residence is fully dutiable, but CGT R2m exclusion applies
Taper relief on PETs (3–7 years)	No taper — cumulative donations since 1 March 2018 aggregate; annual R100k exemption
Gifts out of normal expenditure exemption	No direct equivalent
Spousal unlimited exemption	Unlimited spousal — with R3.5m abatement roll-over to survivor under s4A
Trusts: relevant property regime, 10-year charge	Inter vivos trusts: 45% flat income tax on retained income; conduit principle; s7 attribution; special trusts Type A (disabled) and Type B (testamentary for minors)
Lifetime gifts up to £3,000	R100,000 annual donations tax exemption per donor
10.2 CGT on death
•	Deemed disposal at market value at date of death
•	R300,000 annual exclusion (instead of the R40k live exclusion)
•	Inclusion at 40% for individuals
•	Primary residence R2m exclusion applies
•	Rollover relief to surviving spouse: bequests to a spouse are rolled over at base cost, no CGT on first death
10.3 Executor and administration costs
•	Executor’s fee: prescribed tariff 3.5% of gross asset value + 6% of post-death income, plus VAT where registered
•	Master’s fees: sliding scale, typically capped at R7,000
•	Advertising, conveyancing, valuation, bond cancellation — modelled as aggregate admin cost (~1–2% of gross estate)
•	Liquidity test: project whether sufficient cash / liquid investments exist to cover estate duty + admin costs + debts without being forced to sell illiquid assets
10.4 Will, testamentary trust and guardian data
•	Replace UK-centric ‘Deed of Variation’ language
•	Capture estate plan: will location, executor appointment, testamentary trust instruction, guardianship, key-person letter of wishes
•	Surface age-specific alerts — e.g. children in a testamentary trust with no independent trustee flag
 
11. Module mapping: Goals & Life Events
Largely reusable with minor edits.
•	Replace ‘buy a home’ bond/mortgage defaults with SA bond norms (10% deposit, 20-year term, prime+0 to prime+1 typical)
•	Replace ‘university tuition’ defaults with SA figures: private school R120–R300k p.a., tertiary fees R60–R90k p.a. public, R150k+ private
•	Add ‘emigration / financial emigration’ life event — triggers Exchange Control, retirement fund withdrawal rules post-three-year tax non-residency
•	Add ‘retrenchment’ life event — triggers R500,000 severance benefit tax-free threshold (same table as retirement lump sum)
•	Goal currency — default ZAR but allow per-goal override for offshore goals (USD, GBP, EUR)
 
12. Module mapping: Coordination
The CoordinatingAgent re-aggregates all modules. In SA the aggregation logic changes because:
•	Retirement balances are now tri-bucketed (vested / savings / retirement) — dashboards must net these correctly
•	Offshore vs onshore split is a first-class dashboard widget (shows SDA and FIA used)
•	Estate duty liability projection must include retirement annuities correctly — RAs are NOT dutiable (section 3(2)(bA) exclusion) but living annuity residues ARE included in the income of beneficiaries
•	TFSA appears in Savings and in Investment — de-duplicate in totals (same rule UK had for ISAs)
 
13. New SA-only modules
13.1 Two-Pot Tracker
A new service (TwoPotTracker) that, given a fund record and contribution ledger, produces vested_balance, savings_balance, retirement_balance and the projected 1 September 2024+ split for future contributions. Backed by:
•	retirement_fund_buckets table (bucket_type, fund_id, opening_balance, transactions)
•	contribution_split_service — applies the 1/3 – 2/3 split to every new contribution from 1 Sept 2024 onwards
•	Savings-pot withdrawal simulator: given amount X, projects marginal tax hit using current-year cumulative income
13.2 Exchange Control Ledger
•	Calendar-year ledger of SDA and FIA consumption
•	AIT workflow stubs — documents checklist (IT14SD, IT77C, tax compliance status)
•	Offshore destination record (country, authorised dealer, recipient account)
13.3 Reg 28 Monitor
•	Look-through asset allocation across retirement holdings
•	Per-fund compliance flag plus whole-portfolio compliance flag
•	Suggested rebalancing where a breach exists
13.4 Tax Compliance Status / SARS eFiling link
•	A field on the user profile for the SARS tax reference number
•	A status record for Tax Compliance Status PIN (used for FIA approval)
•	No API integration in v1 — just data capture and workflow reminders
 
14. Technical architecture — what changes, what stays
14.1 Stays as-is
•	Laravel 10 + Vue.js 3 + MySQL 8 stack
•	Overall folder layout: app/Agents, app/Services/{Module}, Http/Controllers/Api, Http/Requests, Http/Resources, Traits, Constants, Observers, Exceptions
•	Vuex-based state management, 31 namespaced modules pattern
•	Pest test framework, Auditable / HasJointOwnership / FormatsCurrency / StructuredLogging traits
•	Preview-mode infrastructure (preview users, PreviewWriteInterceptor middleware) — retained with SA personas
•	Design system: raspberry / horizon / spring / violet / savannah / eggshell — unchanged
•	Capacitor iOS mobile shell — rebranded Fynla SA with a new bundle id
14.2 Changes
•	New Agents: RetirementAgent rewritten, EstateAgent rewritten, ProtectionAgent adjusted, new ExchangeControlAgent
•	TaxConfigService completely rewritten — UK tax_configurations table schema replaced by SA schema (income_tax_brackets, rebates, medical_credits, section_11f, cgt, dwt, interest_exemption, estate_duty, donations_tax)
•	Seeders: TaxConfigurationSeeder (SA), SAProductReferenceSeeder, PreviewUserSeeder (SA personas), ActuarialLifeTablesSeeder (replace ONS life expectancy with Stats SA / ASSA life tables)
•	Frontend currency formatter defaults to en-ZA + ZAR
•	Date formatter defaults to DD MMM YYYY (SA convention) with explicit tax year annotations (e.g. ‘2026/27’)
•	Validation: ID number validator (13-digit SA ID with Luhn check + embedded DOB + citizenship digit) replaces UK NI number
•	Banking: replace sort-code + 8-digit account with 6-digit universal branch code + account number, and Payshap identifiers
14.3 Environment / deploy
•	Separate production host (e.g. SiteGround / Afrihost / Xneelo — user preference)
•	Separate .env, separate Stripe / Paystack / Payfast account for ZAR billing
•	Separate Sentry DSN, separate audit-log bucket
•	deploy/fynla-sa/build.sh mirrors deploy/fynla-org/build.sh
 
15. Code-level impact inventory
First-order inventory derived from the Fynla project metrics (660 Vue components, 233 PHP services, 94 controllers, 94 models, 32 Vuex stores, 9 agents).
Layer	Items	Delete	Rewrite	Keep
Agents	9	0	5 (Protection, Savings, Investment, Retirement, Estate)	4 (Coordinating, Goals, plus 2 new SA agents added)
Services (214 domain services)	214	~40 (UK-specific — IHT, ISA, SIPP, UFPLS, DB transfer, gift-in-normal-expenditure, NRB/RNRB)	~110 (tax, retirement, estate, investment, protection core)	~64 (currency, logging, audit, validation, formatting, goal tracking)
Controllers (94)	94	~5	~60	~29
Models (94)	94	~5	~50 (schema changes)	~39
Vuex stores (32)	32	~3	~20	~9
Vue components (660)	660	~50 (UK wizards, ISA / SIPP forms)	~300 (forms & widgets that display tax/pension values)	~310 (generic UI, layout, shared charts)
Traits (9)	9	0	1 (CalculatesOwnershipShare — check SA joint-ownership nuances)	8
Constants	3	2 (TaxDefaults, EstateDefaults — SA versions)	1 (ValidationLimits — SA values)	0
Observers (12)	12	0	~4 (risk recalc on tax-engine change)	~8
Seeders	—	UK TaxConfiguration, TaxProductReference, PreviewUser, ActuarialLifeTables	All above rebuilt for SA	—
Factories (46)	46	~4	~15	~27
Migrations	—	—	New SA-specific migrations: exchange_control_ledger, retirement_fund_buckets, tax_compliance_statuses, donations_register, estate_duty_projections	Existing schema core largely preserved
Approximate headline: 40–45% of the codebase is directly impacted; ~55% (generic UI, auth, layout, shared infrastructure) is reusable.
 
16. Data model & database migrations
16.1 New tables
•	retirement_fund_buckets — (id, fund_id, bucket_type, balance, as_at_date)
•	retirement_fund_contributions — (id, fund_id, contribution_date, amount, split_savings, split_retirement)
•	exchange_control_ledger — (id, user_id, calendar_year, allowance_type, amount_zar, destination_country, authorised_dealer, ait_reference, transfer_date, notes)
•	tax_compliance_statuses — (id, user_id, tcs_pin, issued_at, expires_at, purpose)
•	donations_register — (id, donor_id, donee_name, donee_relationship, tax_year, amount, exemption_applied)
•	reg28_snapshots — (id, user_id, as_at_date, offshore_pct, equity_pct, property_pct, private_equity_pct, single_entity_pct, compliant)
•	estate_duty_projections — (id, user_id, projection_date, dutiable_value, abatement_used, spousal_rollover_available, projected_duty)
•	tfsa_contributions — (id, user_id, tax_year, provider, amount)
16.2 Modified tables
•	tax_configurations — replace UK schema with SA bracket/rebate/credit/exemption records
•	protection_policies — add product_type, severity_tier, waiting_period_months, benefit_term_months, beneficiary_type enum
•	investment_accounts — add wrapper enum (discretionary, tfsa, endowment, offshore_discretionary) and base_currency
•	retirement_funds — add fund_type, sponsor, includes_pre_2021_provident_portion, has_guarantee_certificate
•	users — add sa_id_number (13 char, nullable for non-citizens), passport_number, tax_reference
16.3 Dropped tables / fields
•	isa_contributions, lifetime_allowance_records, sipp_defined_benefit_transfers, ufpls_events
•	rnrb_trackers, nrb_trackers, gift_records (UK seven-year taper)
 
17. Localisation: currency, date, language, persona set
•	Currency: ZAR; formatter uses ‘R 1 234 567.89’ with space thousands separator and period decimal (SA Government Gazette convention)
•	Language: English (en-ZA). Optional future support for Afrikaans (af-ZA), isiZulu (zu-ZA), isiXhosa (xh-ZA), Sesotho (st-ZA) — not in v1
•	Date format: DD MMM YYYY in copy (e.g. ‘15 April 2026’); DD/MM/YYYY in tabular contexts
•	Tax year notation: ‘2026/27’ rather than ‘2025–2026’
•	Spelling: British (optimise / analyse / programme) — same as UK Fynla
•	Financial year end: 28 February (company year-ends) or individual tax year 1 March – 28/29 February
•	Currency symbol: ‘R’ prefix (never ‘ZAR’ in user copy except in multi-currency disambiguation contexts)
 
18. Compliance, disclosure & FAIS considerations
•	FAIS positioning: Fynla SA is a financial planning and information tool, not an FSCA-licensed FSP rendering advice. Every calculation output must carry a guidance disclaimer.
•	If Fynla SA ever moves to recommend specific products, it must operate under a Category I or II FSP licence, with a licensed Key Individual and appointed Representatives. This is a strategic decision, not a v1 scope item.
•	POPIA compliance: explicit opt-in for marketing, Information Officer registration with the Information Regulator, data subject access request workflow, breach notification within 72 hours.
•	FICA: if Fynla SA holds any client money or transacts, KYC levels apply. v1 does not transact — but the on-boarding flow must collect the information that would later be needed (ID, proof of address within 3 months, source of funds) so that a later transactional pivot is not blocked.
•	TCF (Treating Customers Fairly) principles: suitable outcomes, clear communication, no unreasonable barriers. Product copy and calculator outputs are reviewed through this lens.
•	Consumer Protection Act: cooling-off periods, plain-language rule — relevant to any product purchase flow.
•	Regulation of Advice and Intermediary Services (in COFI) — watch-item for 2026 / 2027 as the statute progresses.
 
19. Phased delivery plan
Phase	Duration	Deliverables
0. Fork & scaffold	1 week	Create fynla-sa repo, rebrand, replace deploy scripts, blank out UK seeders, stub SA TaxConfigurationSeeder
1. Tax Engine	2 weeks	SA brackets, rebates, credits, Section 11F/10C, CGT, estate duty, donations tax constants and service, full Pest coverage of the tax service
2. Savings + TFSA	1 week	TFSA wrapper, savings products, emergency fund calculator
3. Investment + Exchange Control	2 weeks	Discretionary / endowment / offshore, SDA+FIA ledger, AIT workflow stubs
4. Retirement (core)	2 weeks	RA / PF / PvF / Preservation models, Two-Pot tracker, Reg 28 monitor
5. Retirement (decumulation)	1 week	Living annuity & life annuity calculators, compulsory annuitisation, de minimis
6. Protection	1 week	Life, dread, disability lump/income, funeral — coverage calculators and gap analysis
7. Estate Planning	1.5 weeks	Estate duty, donations tax, CGT on death, executor and Master’s fee, testamentary trust data
8. Goals / Life Events	0.5 weeks	Refit SA defaults for bond / tertiary / retirement age
9. Coordination + dashboard	1 week	Aggregate across modules, onshore/offshore split widget, Two-Pot balance widget
10. Personas + preview	1 week	Seeded SA personas, preview guardrails
11. Mobile iOS rebuild	1 week	Capacitor bundle, Face ID, new app store listing
12. Beta, compliance review, launch	2 weeks	FAIS disclaimers, POPIA notice, closed beta
Total indicative effort: ~16 developer-weeks for a solo full-stack engineer with Fynla familiarity; ~10 weeks with a 2-person team running Tax Engine and Retirement in parallel.
 
20. Risks, open questions & out of scope
20.1 Risks
•	Two-Pot System is still bedding in (since Sept 2024); administrative edge cases (divorce orders, housing loans, emigrated members) may require model updates as SARS / FSCA issue guidance
•	Annual Budget (February) adjusts rates — Fynla SA must ship a ‘February refresh’ process; the UK has the same cadence but a March Budget
•	FAIS creep: if user testing shows users treating outputs as advice, the product must either add friction (‘this is not advice’ interstitials, suitability questionnaires) or pursue a licence
•	Exchange control rules continue to relax (SDA doubled 2026; further liberalisation possible) — keep limits in seeder not code
•	Regulation 28 amendments (last material one Feb 2022) — the 45% offshore number is the current line; monitor for further change
20.2 Open questions for Chris
•	Do we license a SA life-table set (e.g. ASSA SA85-90) or use Stats SA life expectancy? The UK uses ONS — we need an equivalent signed-off data source
•	Payment processor: Paystack, Stripe SA, or Payfast for ZAR subscriptions?
•	Domain: fynla.co.za (target), fynla.africa, or a new SA-specific brand?
•	Do we support couples who are UK and SA tax residents simultaneously (dual-domicile)? This opens DTA modelling — out of scope for v1
•	Should the existing UK ‘chris@fynla.org / Password1!’ admin account be replicated as an admin in fynla-sa, or fresh credentials?
20.3 Out of scope for v1
•	Trust accounting (inter vivos trust tax at 45%, attribution rules) beyond flagging existence
•	Section 12J VCCs (closed to new investment anyway, sunsetted 2021)
•	GEPF defined-benefit calculations
•	Medical aid / gap cover modelling (distinct product set — future module)
•	Crypto asset tax tracking
•	Multi-currency accounting (offshore assets recorded in foreign ccy but projected in ZAR at spot — full dual-ccy accounting is v2)
 
21. Appendix A — SARS tax tables 2026/27
Individuals and Special Trusts (tax year 1 March 2026 – 28 February 2027):
Taxable income (R)	Rate of tax
1 – 245,100	18% of taxable income
245,101 – 383,100	R44,118 + 26% of the amount above 245,100
383,101 – 530,200	R79,998 + 31% of the amount above 383,100
530,201 – 695,800	R125,599 + 36% of the amount above 530,200
695,801 – 887,000	R185,215 + 39% of the amount above 695,800
887,001 – 1,878,600	R259,783 + 41% of the amount above 887,000
1,878,601 and above	R666,339 + 45% of the amount above 1,878,600
Rebates and tax thresholds (2026/27): primary rebate R17,820 (threshold R99,000); secondary +R9,570 (65–74, threshold R148,217); tertiary +R3,145 (75+, threshold R165,689).
 
22. Appendix B — Retirement lump-sum tax tables
Retirement (on retirement, death, or severance benefit) — cumulative:
Lump sum (R)	Rate
0 – 550,000	0%
550,001 – 770,000	18% above 550,000
770,001 – 1,155,000	R39,600 + 27% above 770,000
1,155,001 and above	R143,550 + 36% above 1,155,000
Withdrawal (pre-retirement withdrawal, divorce, emigration access) — cumulative:
Withdrawal (R)	Rate
0 – 27,500	0%
27,501 – 726,000	18% above 27,500
726,001 – 1,089,000	R125,730 + 27% above 726,000
1,089,001 and above	R223,740 + 36% above 1,089,000
 
23. Appendix C — Exchange control allowances
Allowance	Limit (per calendar year)	Requirements
Single Discretionary Allowance (SDA)	R2,000,000	Any legal purpose; no SARS approval; declared to authorised dealer
Foreign Investment Allowance (FIA)	R10,000,000	SARS Approval for International Transfer (AIT); tax-compliant status required
Travel allowance (under SDA)	Part of SDA	Same calendar-year pool
Foreign inheritance / earnings	Unlimited (SARS AIT)	Not counted against SDA/FIA once approved
Above R12m combined	Case-by-case	SARB Special Approval via authorised dealer
All amounts reset on 1 January. A year-of-transfer declaration is required annually.
 
24. Appendix D — Glossary UK ↔ SA
UK term	SA equivalent	Notes
Inheritance Tax (IHT)	Estate Duty	Different rates, abatement instead of NRB
Nil Rate Band (NRB)	Estate duty abatement (s4A)	R3.5m, portable between spouses
Residence Nil Rate Band (RNRB)	No equivalent	Primary residence is fully dutiable (but see CGT R2m)
ISA	TFSA	R46k annual / R500k lifetime vs £20k annual
SIPP	Retirement Annuity (RA)	Individual retirement vehicle
Workplace Pension (DC)	Pension / Provident Fund	Employer-sponsored
DB pension / Final Salary	Defined Benefit / GEPF	Far less common in SA private sector
Lifetime Allowance / LSA / LSDBA	Section 11F cap + retirement lump-sum tables	No lifetime pot cap in SA
State Pension	SASSA Old Age Grant	Means-tested, not contributory
Drawdown pension	Living Annuity	2.5%–17.5% band; no Reg 28 inside annuity
Annuity (UK)	Life Annuity / Guaranteed Annuity	Taxable income; s10C for non-deductible portion
Dividend Allowance £500	No direct equivalent	SA uses DWT at 20%
Personal Savings Allowance	Interest exemption	R23,800 (<65) / R34,500 (65+)
PAYE / NI	PAYE / UIF + SDL	Different rates and caps
NI number	SA ID number (13 digits, Luhn-checked)	Embeds DOB and citizenship
Gift out of normal expenditure	—	SA has donations tax with R100k annual exemption
Deed of Variation	Redistribution agreement	Common-law; estate planning tool
Trust (bare / discretionary / interest-in-possession)	Inter vivos trust / testamentary trust / special trust (Type A/B)	45% flat trust rate; conduit principle
Child Trust Fund / Junior ISA	Minor TFSA	TFSA can be opened for a minor; counts towards their lifetime R500k
 
25. Appendix E — Sources
The following sources informed the rates, thresholds and rules cited above. All values are for the 2026/27 tax year unless stated, and should be re-verified against the latest SARS / National Treasury / SARB publications before code freeze.
•	SARS — Rates of Tax for Individuals — https://www.sars.gov.za/tax-rates/income-tax/rates-of-tax-for-individuals/
•	SARS — Budget 2026 FAQs — https://www.sars.gov.za/about/sars-tax-and-customs-system/budget/budget-2026-frequently-asked-questions/
•	SARS — Retirement Lump Sum Benefits — https://www.sars.gov.za/tax-rates/income-tax/retirement-lump-sum-benefits/
•	SARS — Interest and Dividends — https://www.sars.gov.za/tax-rates/income-tax/interest-and-dividends/
•	SARS — CGT — https://www.sars.gov.za/tax-rates/income-tax/capital-gains-tax-cgt/
•	SARS — Estate Duty — https://www.sars.gov.za/types-of-tax/estate-duty/
•	SARS — Donations Tax — https://www.sars.gov.za/types-of-tax/donations-tax/
•	SARS — Section 11F(2)(a) retirement deductions — https://www.sars.gov.za/latest-news/retirement-fund-contribution-deductions-section-11f2a/
•	SARB — South African Reserve Bank — https://www.resbank.co.za/en/home
•	FSCA — Financial Sector Conduct Authority — https://www.fsca.co.za/
•	FSCA — Unclaimed Benefits search — https://www.fsca.co.za/Unclaimed-Benefits/
•	National Treasury — Budget documents — https://www.treasury.gov.za/
•	Gov.za — SASSA Old Age Grant — https://www.gov.za/services/services-residents/social-benefits/old-age-pension
•	Pension Funds Online — South Africa profile — https://www.pensionfundsonline.co.uk/content/country-profiles/south-africa
•	Accounter — SARS Tax Tables 2026/2027 — https://accounter.co.za/news/sars-tax-tables-2026-2027
•	Moneyweb — Godongwana lifts annual TFSA limit — https://www.moneyweb.co.za/news/south-africas/godongwana-lifts-annual-tfsa-limit/
•	Moonstone — CGT / TFSA / retirement / donations changes — https://www.moonstone.co.za/changes-to-cgt-tax-free-savings-retirement-contributions-and-donations/
•	Investec — Two-Pot System explained — https://www.investec.com/en_za/focus/money/two-pot-retirement-system-explained.html
•	Old Mutual — Two-Pot Retirement System — https://www.oldmutual.co.za/two-pot-retirement-system/
•	10X — Living Annuity FAQs — https://www.10x.co.za/living-annuity-faq
•	Glacier — Regulation 28 amendments — https://www.glacierinsights.co.za/blog/articles/regulation-28-amendments
•	FinGlobal — Single Discretionary Allowance — https://www.finglobal.com/2026/02/23/single-discretionary-allowance-south-africa/
•	MoneyMarketing — SDA doubled to R2m — https://www.moneymarketing.co.za/south-africans-send-money-offshore/
•	PKF — SA Tax Guide 2025/2026 — https://www.pkf.co.za/media/akvieqeq/pkf-tax-guide-2025-2026.pdf
•	KPMG — SA Budget Guide 2026 — https://assets.kpmg.com/content/dam/kpmgsites/za/pdf/2026/02/SA%20Budget%20Guide%202026.pdf

All figures in this document reflect publicly-available guidance as at April 2026 and must be re-validated against official SARS / FSCA / SARB publications before release. Where a figure is used in code, it should be stored in the TaxConfigurationSeeder with an effective_from date, never hardcoded.
