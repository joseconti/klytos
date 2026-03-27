# Klytos — Privacy by Design

## GDPR / RGPD / CCPA / LGPD Compliance

Klytos is designed from the ground up to comply with global privacy regulations:
- **GDPR** (EU General Data Protection Regulation)
- **RGPD** (Reglamento General de Protección de Datos — EU/ES)
- **CCPA** (California Consumer Privacy Act)
- **LGPD** (Lei Geral de Proteção de Dados — Brazil)

### No Cookie Banner Required

Klytos built-in analytics do NOT require a cookie consent banner because:

1. **Zero cookies** — Klytos never sets any cookies on visitor browsers.
2. **Zero fingerprinting** — No canvas, WebGL, font, or audio fingerprinting.
3. **Zero external requests** — No Google Analytics, no Facebook Pixel, no third-party trackers.
4. **IP anonymization** — Visitor IPs are hashed with SHA-256 + daily rotating salt.
   The raw IP is never stored. Hashes rotate daily, preventing cross-day tracking.
5. **No PII** — No names, emails, or any personally identifiable information is collected.
6. **Minimal data** — Only: page path, referrer domain (not full URL), device category (not exact screen size).

Per GDPR Article 5(1)(c) (data minimization) and Recital 30, anonymous statistical data
that cannot be attributed to an identified or identifiable natural person is not personal data.

### Data Retention

- Analytics data: 90 days (configurable), auto-pruned.
- Audit logs: 90 days (configurable), auto-pruned.
- No visitor data is ever sold, shared, or transmitted to third parties.

---

## AI-First Architecture

Klytos is not "a CMS with AI features". It is an **AI-native CMS** where:

- The primary interface is the **MCP (Model Context Protocol)** — AI agents control the CMS.
- The admin panel is a secondary, human-friendly interface for monitoring and configuration.
- Every feature is exposed as an MCP tool first, admin UI second.
- Content creation, editing, theming, and site building are designed to be performed by AI.
- The human role is: configure, review, approve, and publish.
