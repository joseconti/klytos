# Legal Standards Reference

Detailed reference for accessibility laws and regulations applicable to web projects.
Use this during Phase 1 (scope definition) and Phase 4 (legal compliance summary).

---

## WCAG 2.2 (W3C Recommendation, October 2023)

### Overview
Web Content Accessibility Guidelines 2.2 is the current W3C Recommendation and the
technical foundation for nearly all accessibility legislation worldwide. It contains
86 success criteria across three conformance levels.

### Conformance Levels
- **Level A (27 criteria)**: Minimum accessibility. Removes the worst barriers.
- **Level AA (28 criteria)**: Standard requirement for legal compliance worldwide.
- **Level AAA (31 criteria)**: Aspirational. Not typically required by law but recommended.

### New in WCAG 2.2 (vs 2.1)
Nine new criteria address gaps for users with low vision, cognitive/learning disabilities,
and motor disabilities on touch devices:

| Criterion | Level | Addresses |
|---|---|---|
| 3.2.6 Consistent Help | A | Cognitive disabilities |
| 3.3.7 Redundant Entry | A | Cognitive disabilities |
| 2.4.11 Focus Not Obscured (Minimum) | AA | Low vision |
| 2.5.7 Dragging Movements | AA | Motor disabilities |
| 2.5.8 Target Size (Minimum) | AA | Motor disabilities, touch devices |
| 3.3.8 Accessible Authentication (Minimum) | AA | Cognitive disabilities |
| 2.4.12 Focus Not Obscured (Enhanced) | AAA | Low vision |
| 2.4.13 Focus Appearance | AAA | Low vision |
| 3.3.9 Accessible Authentication (Enhanced) | AAA | Cognitive disabilities |

SC 4.1.1 Parsing was removed as obsolete since modern browsers handle parsing errors.

### Global Adoption
WCAG is referenced by accessibility laws in the EU, US, Canada, UK, Australia, Japan,
Israel, and dozens of other countries. Level AA is the de facto global standard.

---

## European Accessibility Act (EAA) - Directive (EU) 2019/882

### Timeline
- **Adopted**: June 7, 2019
- **Transposition deadline**: June 28, 2022 (member states implemented into national law)
- **Enforcement began**: June 28, 2025 (currently active)

### Who Must Comply
Any business providing digital products or services to consumers within the EU,
regardless of where the business is headquartered. Covers:

- E-commerce platforms and online stores
- Banking and financial services
- Transport services (ticketing, check-in)
- Telecommunications services
- Audiovisual media services
- E-books and e-readers
- All digital services provided to EU consumers

**Exemptions**: Microenterprises (fewer than 10 employees AND under 2M EUR turnover)
have limited obligations. Content created before June 28, 2025 has transitional provisions.

### Technical Requirement
Compliance with **EN 301 549 v3.2.1**, which incorporates **WCAG 2.1 Level AA**.

### Penalties
Enforcement is handled by each EU member state. Typical penalty structures:

- **Maximum fines**: Up to 100,000 EUR or 4% of annual revenue (whichever is higher)
- **Market surveillance**: Regulators can require product withdrawal or recall
- **Injunctive relief**: Courts can order immediate remediation
- **Reputational damage**: Public disclosure of non-compliance

### Enforcement Activity (2025-2026)
- France's DGCCRF issued formal notices to major retailers before end of 2025
- Disability advocacy groups filing lawsuits across multiple member states
- Market surveillance authorities conducting proactive audits
- Cross-border enforcement cooperation increasing

### Practical Implications
- Websites selling to EU customers must meet WCAG 2.1 AA
- Customer support channels (chat, phone, email) must be accessible
- Product documentation must be available in accessible formats
- Ongoing monitoring required (not just one-time compliance)

---

## EN 301 549 v3.2.1 (ETSI, March 2021)

### Overview
The harmonized European standard for ICT accessibility. Required by the EAA and the
Web Accessibility Directive (EU) 2016/2102.

### WCAG Integration
Incorporates WCAG 2.1 Level AA in full text within its Chapter 9 (Web), Chapter 10
(Non-web documents), and Chapter 11 (Software).

### Requirements Beyond WCAG
EN 301 549 adds requirements that WCAG does not cover:

**Chapter 5 - Generic Requirements:**
- Closed functionality alternatives (devices without keyboards)
- Biometric recognition alternatives
- Preservation of accessibility in conversions

**Chapter 6 - ICT with Two-Way Voice Communication:**
- Real-time text (RTT) support
- Caller ID alternatives
- Responsive video for sign language

**Chapter 7 - ICT with Video Capabilities:**
- Caption processing for media players
- Audio description playback
- User control of caption display

**Chapter 8 - Hardware:**
- Physical dimensions and spacing of controls
- Tactile/auditory feedback
- Operable with limited reach and strength

**Chapter 12 - Documentation and Support Services:**
- Accessibility features documented
- Support services accommodate user communication needs
- Documentation in accessible formats

**Chapter 13 - ICT Providing Relay or Emergency Service Access:**
- Emergency communication accessibility

### Impact on Audits
When auditing for EAA/EN 301 549 compliance, check beyond just the website:
- Customer support accessibility
- Embedded media player accessibility
- Documentation format accessibility
- Authentication method accessibility
- Mobile app accessibility (if applicable)

---

## ADA (Americans with Disabilities Act)

### Title II - State and Local Governments

**Final Rule**: Published April 24, 2024 (Federal Register)
**Effective**: June 24, 2024

**Technical Standard**: WCAG 2.1 Level AA

**Compliance Deadlines:**
- Large entities (50,000+ population): **April 24, 2026**
- Smaller entities (<50,000 population or special districts): **April 26, 2027**

**Scope**: All public-facing content related to official agency business, including:
- Websites and web applications
- Mobile applications
- Electronic documents (PDFs, Office files)
- Videos and multimedia
- Online forms and services

**Limited Exceptions:**
- Archived content (not updated since before compliance date)
- Third-party content not under the entity's control
- Passwords and similar security inputs
- Content that would fundamentally alter the service

### Title III - Public Accommodations (Private Sector)

**Current Status**: No specific federal rule requiring WCAG compliance. However,
courts consistently interpret ADA Title III to require accessible websites.

**De Facto Standard**: WCAG 2.1 Level AA (reinforced by Title II rule)

**Case Law Trends (2025-2026):**
- 3,117 website accessibility lawsuits filed in federal court in 2025 (27% increase)
- Website cases now 36% of all ADA Title III lawsuits
- Geographic hotspots: New York (1,021), Florida (961), Illinois (585)
- Pro se litigation increasing 40% year-over-year
- Shift to state courts in New York due to federal judge scrutiny

**Settlement Ranges:**
- Demand letters: 1,000 - 25,000 USD
- Out-of-court settlements: average 25,000 USD, up to 100,000 USD
- Court judgments: average 75,000 USD
- Class actions: can exceed 6,000,000 USD

**Practical Implications:**
- Any business with a website serving the public should meet WCAG 2.1 AA
- E-commerce sites are the most frequent targets of litigation
- Having an accessibility statement and remediation plan reduces legal exposure
- Third-party overlay widgets do NOT provide compliance (and may increase risk)

---

## Section 508 (Rehabilitation Act)

### Current Standard
The 2018 Refresh of Section 508 incorporates WCAG 2.0 Level AA by reference.
Note: This is WCAG 2.0, not 2.1 or 2.2.

### Who Must Comply
- All US federal agencies and departments
- Organizations receiving federal funding
- Businesses contracted with the federal government
- Vendors selling ICT products/services to federal entities

### Scope
- All public-facing official agency content
- Specific categories of non-public-facing content
- Electronic documents (Office, PDF, HTML, etc.)
- Software and applications
- Hardware and telecommunications equipment

### Practical Implications for Vendors
- Voluntary Product Accessibility Template (VPAT) often required in procurement
- Compliance is a contract requirement, not just a guideline
- Non-compliance can result in contract loss or non-award
- Must maintain compliance throughout product lifecycle

### Future Direction
No update to WCAG 2.1 or 2.2 has been finalized for Section 508 as of April 2026,
though alignment is under consideration. Auditing against WCAG 2.2 AA covers all
Section 508 requirements and provides forward compatibility.

---

## Other Notable Regulations

### Web Accessibility Directive (EU) 2016/2102
Applies to EU public sector websites and mobile apps. Requires EN 301 549 compliance.
Preceded the EAA and covers government sites specifically.

### Accessibility for Ontarians with Disabilities Act (AODA)
Canada (Ontario). Requires WCAG 2.0 Level AA for organizations with 50+ employees.

### UK Equality Act 2010 + Public Sector Bodies Accessibility Regulations 2018
UK public sector must meet WCAG 2.1 Level AA. Private sector covered under broader
Equality Act duty to make reasonable adjustments.

### Australian Disability Discrimination Act (DDA)
References WCAG 2.0. Several precedent-setting cases involving commercial websites.

---

## Recommendation for Multi-Jurisdiction Projects

When a project serves multiple jurisdictions, audit against **WCAG 2.2 Level AA**.
This satisfies:
- EAA/EN 301 549 (requires WCAG 2.1 AA, which is a subset of 2.2 AA)
- ADA Title II (requires WCAG 2.1 AA)
- ADA Title III (de facto WCAG 2.1 AA)
- Section 508 (requires WCAG 2.0 AA, which is a subset of 2.2 AA)
- UK, Canadian, and Australian requirements

For EAA compliance, additionally verify support services, documentation, and
non-web ICT requirements from EN 301 549 chapters 5-13.
