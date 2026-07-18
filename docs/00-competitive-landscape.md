# 00 — Competitive Landscape (Klytos CMS)

> Keel Phase 1, step 0 (competitive scan). Produced 2026-07-18.
> Scan status: **partial** — see §7. Every claim below carries a source URL. Where a fact
> could not be resolved to a source it is marked **[unverified]** rather than guessed.

**Product under analysis.** Klytos CMS — self-hosted, GPL-3.0-or-later, PHP 8.1+ AI-first CMS.
Thesis: the site is built and managed **entirely by AI through MCP**; the MCP server (OAuth 2.0,
core tools) is the primary interface and the human admin panel is secondary. Output is a
**static site**. Source: <https://github.com/joseconti/klytos>, <https://klytos.io>.

**Facts verified directly against this repository** (not marketing copy), 2026-07-18:

| Claim | Verified value | How |
|---|---|---|
| Core MCP tools | **172 `$registry->register()` calls** across **34 tool modules** in `installer/core/mcp/tools/` | grep |
| Hooks / filters | **411 unique** hook names via `klytos_do_action` / `klytos_apply_filters` | grep |
| Locales | **20** (`ca da de el en es eu fi fr gl it ja nb nl pl pt ru sv tr zh`) in `installer/core/lang/` | ls |
| Version | `installer/VERSION` = **0.31.1-beta.1**; latest public release **0.30.1 (2026-04-06)**, 152 releases | file + repo |
| Integrity, consent, x402 | `core/integrity-checker.php`, `core/consent-manager.php`, `core/x402*`; plugins `klytos-x402-coinbase`, `klytos-x402-stripe` | ls |
| GitHub traction | **10 stars** | repo page |

⚠️ The public README still advertises "160+ tools" and "75+ hooks and filters" — both are now
stale versus the code (172 / 411). Fixing the README is a trivial, high-leverage action.

---

## 1. Direct competitors

Products whose primary or first-class control surface is an AI agent / MCP.

### 1.1 The single most dangerous competitor: EmDash (Cloudflare)

| Field | Value |
|---|---|
| What | Full-stack TypeScript CMS on **Astro 6**, pitched by Cloudflare as "the spiritual successor to WordPress" |
| License / price | **MIT**, free, open source |
| MCP | **First-class — built into every instance**, not a plugin. Covers content, schema, media, taxonomies, menus, revision history. Ships **agent skills** for plugin/theme dev and a CLI agents can drive |
| Self-hostable | **Yes** — "runs best on Cloudflare, but it's not locked to it"; SQLite/D1/Turso/Postgres, R2/S3/local FS. **Caveat:** sandboxed plugins depend on Cloudflare **Dynamic Workers, paid accounts only** |
| Static output | **Hybrid** — server-rendered with Astro Live Collections ("no rebuilds"), not a pure static generator |
| x402 | **Yes** — ships `@emdash-cms/x402@0.29.0` |
| Traction | **11,300 GitHub stars**, ~1,100 commits, 1k forks, 427 releases, v0.29.0 (2026-07-10). Launched 2026-04-01 |

Sources: <https://github.com/emdash-cms/emdash> · <https://www.infoq.com/news/2026/04/cloudflare-emdash-wordpress/> · <https://siliconangle.com/2026/04/02/cloudflare-debuts-emdash-challenge-aging-wordpress-ai-native-cms/> · <https://joost.blog/emdash-cms/>

**Why this matters to Klytos.** EmDash occupies almost exactly Klytos's stated position —
open source, self-hostable, MCP-native by design, agent-first, static-leaning, **and x402** —
with Cloudflare's engineering, distribution and PR behind it, and **1,130× the GitHub stars**.
Klytos's feature-level differentiation against EmDash is close to zero on paper. What remains
is runtime (PHP vs Node/Workers), hosting economics, and governance independence (§6).

The criticism of EmDash is also the opening. Awesome Motive's Syed Balkhi: *"CF has a vendor
lock in… Without [support from hosting companies], I don't see this project gaining fast
traction."* Matt Mullenweg's counter-argument is, verbatim, the case for Klytos's runtime
choice: *"You can run WordPress on a Raspberry Pi, on your phone, on your desktop, on a random
web host in Indonesia charging 99 cents a month."*
Sources: <https://www.therepository.email/cloudflare-launches-emdash-wordpress-community-rejects-spiritual-successor-claim> · <https://ma.tt/2026/04/emdash-feedback/>

Note the coordinated stack play: **Cloudflare acquired the Astro Technology Company in
January 2026**, then shipped a CMS on Astro 6 in April. This is not a side project.

### 1.2 Headless CMSs with official MCP servers

**Structural finding: of twelve official vendor MCP implementations surveyed, ZERO produce a
static site.** Every commercial CMS with first-class MCP is a runtime/API product. That gap is
real and verified.

| Product | License / price | MCP status | Self-host | Static out | Tools |
|---|---|---|---|---|---|
| **Strapi** | MIT core free; Cloud $35/$90/$450 per mo | **First-class, in core** — native `/mcp` endpoint on Strapi's own HTTP server, stateless, token-scoped. GA v5.49.0 (from v5.47.0, 2026-05-28) | ✅ | ❌ | Generated per content type (≤8/collection, ≤6/single). **Content CRUD only — cannot author schema** |
| **Directus** | ⚠️ **No longer OSS.** MSCL (Fair-Core derivative) from v12, May 2026: free self-host only under **$5M revenue / 50 employees**; converts to GPLv3 after 4 yrs. Cloud from $99/mo | **First-class, in core** (Settings → AI). MCP client gets a *Directus role*; every action hits the native audit trail. Out of beta v11.13, 2025-11-07 | ✅ (threshold) | ❌ | **14 unified tools** covering schema, collections, fields, relations, flows — deepest system coverage of the commercial set |
| **Sanity** | SaaS. Free / Growth $15 per seat/mo / Enterprise. 100 AI credits/mo, $0.05 each | **First-class but hosted-only** at `mcp.sanity.io`; local `@sanity/mcp-server` **deprecated**. Earliest official CMS MCP found (2025-04-10) | ❌ | ❌ | **38** incl. `deploy_schema`, `deploy_studio`, `semantic_search`, `generate_image` |
| **Payload** | MIT, free. Payload Cloud discontinued for new projects post-**Figma acquisition** — self-host is the only path | Official **plugin** `@payloadcms/plugin-mcp` (first-party, not core). Present by v3.64.0 (2025-11-13) | ✅ | ❌ | **8 core** + 6 opt-in auth. **Schema tools are read-only** |
| **Contentful** | SaaS; server code MIT | Official, remote (`mcp.contentful.com`, EU endpoint, OAuth) + local OSS | ❌ (server yes) | ❌ | Docs claim "70+", repo enumerates **40+** ⚠️ discrepancy — treat 70+ as marketing |
| **Storyblok** | SaaS. ⚠️ plan gating undisclosed | Official, **hosted-only**; local repo **archived 2026-03-30**. Launched 2026-03-23 | ❌ | ❌ | Discovery-first: `search`, `describe`, three `execute` tools **split by risk (read / mutate / destroy)** — best governance design found. ⚠️ "155+" claim unverified |
| **Contentstack** | Enterprise SaaS | Official npm `@contentstack/mcp`, stdio. ⚠️ dev docs still say **experimental**, agent-os docs read GA | ❌ | ❌ | **~227** — largest count found, thin per-endpoint wrappers |
| **Hygraph** | SaaS; MCP on **all plans**, Early Access (2026-01) | Official, hosted, permission-aware | ❌ | ❌ | ⚠️ unpublished. **Delete and unpublish deliberately excluded** |
| **Kontent.ai** | SaaS; server MIT | Official — but **9 GitHub stars**, essentially unnoticed | server ✅ | ❌ | **50+**, real schema coverage |
| **Prismic** | SaaS; MCP free on all plans | Official but **deliberately narrowed**: developer-facing server deprecated for the CLI; remaining editor MCP **writes drafts into a release only — cannot delete or publish** (2026-06-08) | ❌ | ❌ | ⚠️ unpublished |
| **Webflow** | SaaS; server MIT | Official + vendor-verified, `mcp.webflow.com`, OAuth. Deep: components/props/variants/slots, page branches, **custom code r/w, design-system definition**. v1.0.0 2025-09-17 | ❌ | ~hosted | ⚠️ unenumerated |
| **Ghost** | MIT, self-hostable | ❌ **No official MCP.** Community only (`MFYDev/ghost-mcp`). A forum request thread exists with no TryGhost commitment | ✅ | ❌ | — |

Sources: <https://docs.strapi.io/cms/features/strapi-mcp-server> · <https://strapi.io/blog/the-strapi-mcp-server-is-now-ga> · <https://directus.com/docs/guides/ai/mcp> · <https://directus.io/blog/directus-v12-license-change> · <https://www.sanity.io/docs/ai/mcp-server> · <https://github.com/sanity-io/sanity-mcp-server> · <https://payloadcms.com/docs/plugins/mcp> · <https://github.com/contentful/contentful-mcp-server> · <https://www.storyblok.com/mp/mcp-server> · <https://www.contentstack.com/docs/agent-os/contentstack-mcp-server> · <https://hygraph.com/blog/mcp-server> · <https://prismic.io/updates/prismic-mcp> · <https://github.com/webflow/mcp-server> · <https://forum.ghost.org/t/integrating-the-model-context-protocol-mcp-as-a-native-ghost-server/63239>

**Adoption timeline** (MCP is now table stakes for headless CMS): Sanity Apr 2025 → Webflow
Sep 2025 → Directus Nov 2025 → Payload Nov 2025 → Hygraph Jan 2026 → Storyblok Mar 2026 →
EmDash Apr 2026 → Strapi May 2026.

Ecosystem context: the official MCP Registry counted **9,652 servers** (2026-05-24); MCP SDK
downloads reached **97M/month** in March 2026, up ~970× in 18 months.
Source: <https://www.digitalapplied.com/blog/mcp-adoption-statistics-2026-model-context-protocol>

### 1.3 WordPress — the Abilities API and MCP Adapter

This is the most consequential entry in the whole scan and the answer is more nuanced than
"WordPress has MCP now."

- **Abilities API IS in core.** Shipped in **WordPress 6.9 (Dec 2025)**: PHP registration,
  REST at `/wp-json/wp-abilities/v1/`, plus a small set of core abilities.
  <https://make.wordpress.org/core/2025/11/10/abilities-api-in-wordpress-6-9/>
- **WordPress 7.0 "Armstrong" (2026-05-20)** added client-side `@wordpress/abilities`, the
  Abilities Explorer, an AI Client, an AI Services Registry, a Connectors hub, and Command
  Palette everywhere. <https://www.infoq.com/news/2026/07/wordpress-7-ai/>
- **MCP is deliberately NOT in core.** `WordPress/mcp-adapter` (1.4k stars, GPL-2.0+, v0.5.0
  2026-04-15) is the canonical project, shipped as a **plugin/Composer package**, part of the
  "AI Building Blocks" initiative. The developer blog states the adapter/bridge pattern "is
  intended as the long term approach" — i.e. WordPress keeps MCP outside core so it can pivot
  if the protocol landscape shifts.
  <https://developer.wordpress.org/news/2026/02/from-abilities-to-ai-agents-introducing-the-wordpress-mcp-adapter/>
- **The default server exposes only 3 tools**: `discover-abilities`, `get-ability-info`,
  `execute-ability` — a discovery-indirection layer, not a rich typed surface. (Confirmed in
  the wild: the WordPress MCP connectors visible in this very session expose exactly that
  three-tool shape.)
- **`Automattic/wordpress-mcp` is dead** — archived read-only 2026-01-19, README points to
  `WordPress/mcp-adapter`. <https://github.com/Automattic/wordpress-mcp>
- **The 7.1 roadmap (2026-06-19) contains no mention of MCP at all**, and a merge proposal for
  three read-only core abilities was **deferred out of 7.1** back to the feature plugin.
  <https://make.wordpress.org/core/2026/07/02/merge-proposal-expanding-wordpress-core-abilities/>
- Ecosystem is moving anyway: **WooCommerce 10.9** ships seven canonical domain abilities
  through the adapter; **GoDaddy launched "Airo for WordPress" (May 2026)**, AI that builds and
  continuously improves WordPress sites.
  <https://aboutus.godaddy.net/newsroom/news-releases/press-release-details/2026/GoDaddys-Airo-for-WordPress-Delivers-AI-that-Builds-Grows-and-Continuously-Improves-Websites/default.aspx>

**Net:** WordPress has a first-class *abilities* primitive in core, a deliberately-external MCP
bridge exposing 3 generic tools, no static output in core, and 42.4% of the web. Klytos's MCP
surface is currently **deeper and more direct** (172 typed tools vs 3 generic ones). That
advantage is a matter of time and contributor count, not architecture.

### 1.4 AI site builders

| Product | Price | License | Self-host | Static out | MCP for external agents | Lock-in |
|---|---|---|---|---|---|---|
| **Lovable** | Free 5 credits/day; Pro ~$25, Business ~$50/mo | Proprietary | Frontend yes via GitHub sync; **backend no** | Node build | ✅ **Official, `mcp.lovable.dev`, ~50 tools**, OAuth + `lov_` API key | Frontend low, **Cloud backend high** |
| **v0 (Vercel)** | Free $5; Premium $20; Team $30/user | Proprietary | ZIP / GitHub, Next.js | Node build | MCP **client**; ⚠️ no verified first-party "drive v0 externally" server | **Lowest** — plain Next.js source |
| **Bolt.new** | Free; Pro $25; Teams $30/member | Proprietary; **bolt.diy** fork MIT ⚠️ WebContainers API needs commercial licence for for-profit prod | ZIP / GitHub; bolt.diy via Docker | Node | ⚠️ none found | Low frontend, Bolt Cloud sticky |
| **Replit Agent** | Core $20/mo, Pro $95/mo | Proprietary | Code portable, infra sticky | ❌ | MCP **client** (with security scanner); external-drive server ⚠️ unverified | Moderate |
| **Framer AI** | Free → Scale ~$100/mo | Proprietary | ❌ **No native code export** | Hosted runtime | ✅ Connects to Claude Code / Cursor / Codex over MCP; `@framer/agent`. *"Because the AI runs in your own tool, it doesn't draw on your Framer credits"* | **High** |
| **Wix (Harmony / Astro / Aria)** | Harmony free on all plans; Light $17 → Elite $159/mo | Proprietary | ❌ | ❌ | ✅ **Official `mcp.wix.com`** (~9 tools) — and **every Wix site is itself an MCP server**. @Wix in ChatGPT since Mar 2026 | **Worst-in-class** — "no traditional source code… a proprietary client-side JavaScript engine at runtime" |
| **Squarespace / Blueprint AI** | Basic $16 → Advanced $99/mo | Proprietary | ❌ | ❌ | ❌ none official; 3rd-party REST wrappers only | **High** — 7.1 has **no XML export at all** |
| **Durable** | Free; Starter $15 → Mogul $95/mo | Proprietary | ❌ | ❌ | ❌ | **Total** — no source access |
| **Hostinger Horizons** | Explorer $6.99 → Hustler $79.99/mo | Proprietary | Export ZIP **paywalled to Hobbyist+**; Node/Vite | Static only after a Vite build | ❌ | ⚠️ **One-way**: "Once you export a Horizons project, there is no way to import the edited version back" |
| **GoDaddy Airo** | ~$9.99–$20.99/mo; Airo Plus $59.88 yr1 → $95.88 renewal | Proprietary | Only via the WordPress path | ❌ | ❌ | High (low via WP) |
| **Base44** (Wix, acq. ~$80M) | Free; Builder ~$40 | Proprietary | ZIP/GitHub paywalled; **backend not exportable** | ❌ | ❌ | High. **$100M ARR 9 months post-acquisition** |
| **10Web** | $10–$24/mo; API tier $140/mo | Proprietary wrapper | ✅ **Genuinely portable — it IS WordPress** | ❌ | Via the WP adapter, not 10Web-specific ⚠️ | **Lowest of the hosted builders** |
| **Webstudio** | Free + paid cloud | **AGPL-3.0**; ⚠️ `sdk-components-animation` proprietary EULA | Sites ✅; **builder "not recommended" in prod** | ✅ static export (loses dynamic pages, redirects, webhook forms, image opt, sitemap/robots) | ❌ **none found — notable gap** | Lowest |
| **Silex** | Free | AGPL-3.0 | ✅ Docker/Node/CapRover/YunoHost | ✅ **Static HTML/CSS/JS** | ✅ **Yes** — local-first AI via Ollama / Claude Code / Goose over MCP | Lowest. 2.9k stars, **OW2 Best Project Award 2026** |
| **VoxelSite** | One-time, CodeCanyon | AGPL-3.0 | ✅ **any PHP 8.2+ shared host** | ✅ **HTML/CSS/PHP/vanilla JS, no build step** | ✅ Agent API + MCP endpoint + llms.txt | ⚠️ **4 GitHub stars — zero traction** |
| **Dyad / Onlook / bolt.diy / Libra / OpenUI** | Free | Apache-2.0 / MIT | ✅ local | Node apps | BYO-key, no MCP server | Lowest |

Traction, for scale calibration: **Lovable $500M ARR (Jun 2026)**, in talks at **$13.2B**;
**Replit ~$525M annualized (Apr 2026)**, $9B; **Wix ARR $1.903B** (SEC-filed);
**Framer $50M ARR**, $2B; **Base44 $100M ARR, 2M+ users**.

Sources: <https://lovable.dev/mcp> · <https://docs.lovable.dev/integrations/lovable-mcp-server> · <https://techcrunch.com/2026/07/08/lovable-reportedly-in-talks-to-double-its-valuation-to-13-2b/> · <https://v0.app/pricing> · <https://bolt.new/pricing> · <https://github.com/stackblitz-labs/bolt.diy> · <https://docs.replit.com/references/mcp/overview> · <https://www.framer.com/blog/building-framer-agents/> · <https://github.com/wix/wix-mcp> · <https://www.wix.com/press-room/home/post/wix-reports-first-quarter-2026-results> · <https://www.nocodeexport.com/en/blog/export-wix-website-guide> · <https://support.squarespace.com/hc/en-us/articles/206566687-Exporting-your-site> · <https://www.websiteplanet.com/website-builders/durable/> · <https://www.hostinger.com/support/10771345-hostinger-horizons-how-to-export-code/> · <https://docs.webstudio.is/university/self-hosting> · <https://github.com/silexlabs/Silex> · <https://github.com/NowSquare/VoxelSite> · <https://10web.io/pricing-platform/>

### 1.5 Other self-declared "AI-first / MCP-native" CMSs

Most of this category is SEO content farming — `llmcms.org`, `elmapicms.com`, `decoupled.io`,
`mobian.studio`, `skywork.ai` publish near-identical "Top 7 CMS for AI agents 2026" listicles;
treat their tool counts as unreliable. Real projects found:

| Project | Verdict |
|---|---|
| **LightCMS** (Jon Radoff) | ✅ Real, tiny. Go + MongoDB, MIT, **23 stars**, v4.2.0 (2026-03-24). **106 MCP tools + 3 prompts. Generates static HTML** with ISR + `/cm` admin. Has **content forking as agent sandboxes**, versioning with diff/merge, approval workflows. **The closest architectural match to Klytos found — and it has essentially no traction.** <https://github.com/jonradoff/lightcms> |
| **Seite** | ✅ Real, very small. AI-native SSG in Rust, MIT, **13 stars**, v0.16.0 (2026-06-25). 6 MCP tools. Emits canonical URLs, OG, JSON-LD + markdown copies for LLMs. <https://github.com/seite-sh/seite> |
| **FormCMS** | ASP.NET Core + React, agents connect via MCP to design schemas, seed data, deploy. <https://github.com/formcms/formcms> |
| **MDCMS** (Blazity) | ⚠️ Partly verified — heavy on positioning, light on specifics. <https://www.mdcms.ai/> |
| **GitCMS** | Commercial, one-time per site. **Has an MCP app** turning ChatGPT/Claude into content agents (branch drafts, SEO checks, submit for review) over any SSG. <https://gitcms.dev/mcp/> |
| **agentic-cms** | ❌ Effectively vapour — **0 stars**, only a Supabase adapter working |
| **NomaCMS / ElmapiCMS** | ❌ Could not verify independently; only cited by the listicle network |

---

## 2. Adjacent competitors

Judged on the four axes Klytos competes on: **self-hosting · PHP shared-hosting friendliness ·
static output · AI control**.

### 2.1 SSG + CMS combos

| Product | Self-host | Shared PHP host | Static | AI / MCP | Status |
|---|---|---|---|---|---|
| **Decap CMS** | ✅ MIT, git-based | JS bundle sits anywhere; SSG side needs Node | ✅ | ❌ **none** | **Not abandoned but slow** — v3.14.1 (2026-06-15), 19.2k stars, 557 open issues. ⚠️ Documented security-response friction (a moderate XSS reported Sep 2025 reportedly still unpatched; a Jan 2026 proxy vuln fixed in a week but unreleased until late Feb) — third-party review, not Decap advisories |
| **Sveltia CMS** | ✅ | same | ✅ | ❌ **explicitly none today**; AI chat/images are post-1.0 roadmap | De-facto Decap successor, ~300KB, strong i18n. **Still beta; v1.0 GA targeted late 2026** |
| **TinaCMS** | ✅ core (BYO DB/auth/git) | ❌ Node/React | ✅ | ⚠️ no MCP found (unverified-negative) | TinaCloud: free ≤2 users, Team $29/mo, Business $599/mo |
| **Keystatic** | ✅ | ❌ Node | ✅ | ❌ | README **still self-describes as experimental** |
| **Pages CMS** | ✅ | ❌ | ✅ | ❌ | Modest activity |
| **CloudCannon** | ❌ SaaS $45–$250/mo | ❌ | ✅ | No proprietary MCP — "we store plain files in Git so Cursor/Claude Code work fine" | — |
| **Astro** | ✅ MIT | ❌ Node | ✅ | via EmDash | **#1 pure SSG**, 9.08% SSG share. **Astro Technology Company acquired by Cloudflare Jan 2026** |
| **Hugo** | ✅ | Single Go binary, **no Node** | ✅ | Community only (`hugo-mcp`) | Fastest; sub-1ms/page |
| **Eleventy** | ✅ | ❌ Node | ✅ | ❌ | Healthy |
| **Jekyll** | ✅ | ❌ Ruby | ✅ | ❌ | GitHub Pages default |
| **Gatsby** | ✅ | ❌ | ✅ | ❌ | **Effectively dead for new projects** — Netlify acquired 2023, core team gone, plugin ecosystem unmaintained, Gatsby Cloud EOL'd. v5.16.0 (Jan 2026) not formally EOL |
| **Publii** | ✅ desktop Electron | Output uploads to **any cheap shared host** | ✅ | ❌ **none** | Free/OSS, v0.47.7 (2026-07-04). No server runtime at all |

Sources: <https://github.com/decaporg/decap-cms> · <https://sveltiacms.app/en/docs/roadmap> · <https://tina.io/pricing> · <https://github.com/thinkmill/keystatic> · <https://cloudcannon.com/pricing/> · <https://getpublii.com/>

### 2.2 PHP flat-file / commercial CMSs — the nearest neighbours

| Product | License / price | Static output | MCP |
|---|---|---|---|
| **Statamic** | Laravel-based. **Solo free forever**; **Pro $349/site one-time** (from 2026-05-01) + optional $99/yr updates | ✅ **Official first-party `statamic/ssg`**, v4.1.0 (2026-03-09) | ⚠️ **PHP-native, 100+ tools, OAuth 2.1, scoped tokens, 21 permissions, audit logging, CP dashboard** — but `cboxdk/statamic-mcp` is a **community addon** (31 stars), not Statamic core. ⚠️ Sources conflict on "official"; evidence favours community, built on first-party `laravel/mcp` |
| **Grav** | Free/OSS | ❌ **Not native.** Third-party plugins only; vendor-adjacent article literally titled "Grav CMS is not a Static Site Generator" | ⚠️ **First-party MCP, but `grav-mcp` is a standalone Node.js npm package**, running on the *AI client's* machine and translating MCP → Grav REST API. **70 tools**, 11 domains, MIT. Grav 2.0 stable ~Jun 2026 |
| **Kirby** | Commercial — **Basic €99/site**, Enterprise €349/site, one-time | Third-party only; the well-known `d4l/kirby-static-site-generator` is **archived (Jul 2024)** | ❌ none found |
| **Craft CMS** | Commercial | ⚠️ SSG plugin | ⚠️ **Community, not Pixel & Tonic.** `stimmtdigital/craft-mcp`: **50 tools + 9 prompts + 12 resources**, 1,915 active installs, disabled in prod by default. Plus `markhuot/craft-ai` |
| **ProcessWire** | Free | ⚠️ **StaticWire** module | ⚠️ Community `elabx/processwire-mcp` (PHP 8.1+), notable security hardening. Plus `processwire-boost` (28 tools) |
| **October CMS** | $29–$39/yr | ❌ | "AI-aware tooling" listed as upcoming; no MCP plugin found |
| **Concrete CMS** | Free | ❌ | ⚠️ Community `MacareuxDigital/concretecms-mcp-server` — **TypeScript, not PHP-native** |
| **SilverStripe** | Free | ❌ | ❌ **Weakest.** Only a *code-validation* MCP; commentary is blunt: "no way for an AI assistant to read, write or operate on a SilverStripe site as a first-class consumer of its data model" |
| **Typemill** | Free/OSS flat-file | ❌ | ⚠️ **Has AI, no MCP** — Claude/ChatGPT via "Kixote", v2.23.0 "Bring Your Own AI" (2026-05-16) |
| **Bludit / Pico / Automad / WonderCMS** | Free | ❌ | ❌ | 

Sources: <https://github.com/cboxdk/statamic-mcp> · <https://github.com/statamic/ssg> · <https://statamic.com/pricing> · <https://getgrav.org/blog/grav-2-mcp-server> · <https://github.com/getgrav/grav-mcp> · <https://getkirby.com/buy> · <https://github.com/stimmtdigital/craft-mcp>

### 2.3 The classic self-hosted PHP CMS field

Market share (W3Techs, 2026): **WordPress 42.4% of all websites / 59.8% of the CMS market**
(peaked 43.6% mid-2025; CMS share down from 65.2% in 2022) · **Joomla ~2.4%** (from 9.3% in
2014, ~80% decline) · **Drupal ~1.2%** (from 5.5%). Structural trend: open-source PHP CMSs are
losing ground to SaaS builders. <https://w3techs.com/technologies/overview/content_management>

| CMS | Self-host | Shared PHP host | Static output | AI / MCP |
|---|---|---|---|---|
| **WordPress** | ✅ | ✅ **canonical** | ⚠️ **Plugin-only and thinning** — **WP2Static effectively unmaintained**; **Simply Static** is the live option (v3.7.5); Elementor sunset Strattic hosting Jan 2025. Realistic effort cited: 30–90 min export + 1–2 h cleanup | ✅ **Strongest of the classics** — Abilities API in core 6.9, official `mcp-adapter` plugin, OAuth 2.1/JWT/App Passwords |
| **Drupal** | ✅ | ❌ poor fit (Composer, memory, cron) | ⚠️ **Tome**, officially flagged **"Minimally maintained"** | ✅ **Official and serious** — `mcp_server` module built on the **official MCP PHP SDK** (PHP Foundation + Symfony). Core AI module reportedly on **11,000+ live sites**; Dries published a 2026 AI roadmap; European Commission ran a Drupal AI hackathon Jan 2026. ⚠️ But contrib is fragmented: `mcp` (315 sites), `mcp_tools` (**222 tools, 118 sites, beta14, explicitly NOT covered by Drupal's security advisory policy**) |
| **Joomla** | ✅ | ✅ | ❌ | 🔜 **PHP-native, core-integrated, PoC stage** — Jan 2026 discovery sprint produced a working backend UI + MCP web service endpoint. Deliberate: **in core, not standalone; PHP to avoid dependencies; Streamable HTTP** |
| **TYPO3** | ✅ | ⚠️ | ❌ | ⚠️ Community only. `hauptsacheNet/typo3-mcp-server` routes **all agent changes through TYPO3 workspaces** so nothing hits production until published — **the best safety model found anywhere in this scan** |
| **ProcessWire** | ✅ | ✅ | ⚠️ StaticWire | ⚠️ Community |

Sources: <https://www.drupal.org/project/mcp_server> · <https://dri.es/files/drupal-ai-roadmap-2026.pdf> · <https://www.drupal.org/project/tome> · <https://magazine.joomla.org/all-issues/february-2026/joomla-cms-mcp-server-opening-the-door-to-ai-powered-administration> · <https://news.typo3.com/article/ai-integration-in-typo3-via-mcp-the-end-of-backend-fumbling> · <https://wordpress.org/plugins/simply-static/>

### 2.4 The key competitive question, answered

> **Is there any PHP CMS with a native, first-party MCP server AND native static output?**

**No — and Klytos is the only candidate that has both in core.** The intersection is genuinely
unoccupied by any single, native, first-party product:

| Candidate | PHP | Native MCP | Static output | Verdict |
|---|---|---|---|---|
| **Klytos** | ✅ | ✅ **core, PHP-native, 172 tools, OAuth 2.0** | ✅ **core** | **Only product where both are core** |
| **Statamic 6** | ✅ Laravel | ⚠️ PHP-native but **community addon** | ✅ official first-party SSG | **Closest competitor** — but core + 2 addons, needs Composer/Laravel, Pro $349/site |
| **Grav 2.0** | ✅ | ⚠️ first-party but **Node.js sidecar** | ❌ | Closest on MCP, fails on static |
| **ProcessWire** | ✅ | ⚠️ community | ⚠️ module | Both exist, neither official |
| **Craft** | ✅ | ⚠️ community | ⚠️ plugin | Commercial, both are plugins |
| **WordPress** | ✅ | ✅ official (external plugin) | ⚠️ plugin-only, WP2Static dead | Best MCP reach, weakest static |
| **Drupal** | ✅ | ✅ official | ⚠️ "minimally maintained" | Not shared-hosting friendly |
| **Joomla** | ✅ | 🔜 PoC | ❌ | Watch this one |
| **Concrete** | ✅ | ❌ TypeScript sidecar | ❌ | — |

**But note the caveat that decides §6:** the **official PHP SDK for MCP (`mcp/sdk`) is now
maintained by Symfony + The PHP Foundation in collaboration with Anthropic**. Drupal already
builds on it; Joomla chose PHP for the same reason. Expect PHP CMS MCP servers to proliferate
over the next 12 months. **The window on "PHP CMS with native MCP" as a differentiator is
closing.**

---

## 3. Unified feature list (table stakes)

Features a product in this category is now expected to have. "Has it?" uses the verified facts
above; **unknown** where this scan could not determine it.

### 3.1 CMS fundamentals

| # | Feature | Klytos has it? | Note |
|---|---|---|---|
| 1 | Pages / posts CRUD, hierarchy, slugs | ✅ | `page-tools.php` |
| 2 | Custom post types + taxonomies + terms | ✅ | `post-type-tools.php`, taxonomy tools |
| 3 | Custom fields with typed validation | ✅ | 27 field types |
| 4 | Custom post statuses / editorial workflow | ✅ | `post-status-tools.php` |
| 5 | Media library + metadata + usage tracking | ✅ | asset tools, categories, unused-asset cleanup |
| 6 | Image editing / derivatives | ✅ | `klytos_edit_image` |
| 7 | Menus & navigation | ✅ | |
| 8 | Templates / template parts / reusable blocks | ✅ | |
| 9 | Themes, design tokens, colours, fonts, layout | ✅ | 118 `--klytos-*` tokens |
| 10 | Block editor (Gutenberg-compatible markup) | ✅ | plus TinyMCE |
| 11 | Versioning, diff, restore | ✅ | `version-tools.php` |
| 12 | Trash / restore / permanent delete | ✅ | |
| 13 | Page locking / concurrent-edit safety | ✅ | `klytos_lock_page` |
| 14 | Comments + moderation | ✅ | |
| 15 | Forms + entries + anti-spam | ✅ | `klytos-forms` plugin |
| 16 | Users, roles, permissions | ✅ | |
| 17 | 2FA | ✅ | TOTP, magic link, passkeys |
| 18 | Scheduled / recurring background actions | ✅ | ActionScheduler + cron |
| 19 | Webhooks | ✅ | |
| 20 | Site health / diagnostics | ✅ | |
| 21 | Import / migration from other CMSs | ✅ | `klytos-importer` (WP XML, sitemap, crawl) |
| 22 | Export / no lock-in | ✅ | `klytos_export_site` + static output |
| 23 | Backups | **unknown** | `installer/backups/` exists; scope/restore path not verified |
| 24 | Multi-site / multi-tenant | **unknown** — likely ❌ | Not observed |
| 25 | Real-time collaborative editing | ❌ | WordPress cut this from 7.0 RC too |
| 26 | A/B testing, personalisation | ❌ | Enterprise-CMS table stakes, not SMB |

### 3.2 Static / performance

| # | Feature | Klytos? |
|---|---|---|
| 27 | Static HTML/CSS generation, no frontend DB queries | ✅ **core differentiator** |
| 28 | Incremental / per-page rebuild | ✅ | `klytos_build_page` vs `klytos_build_site` |
| 29 | Build status / observability | ✅ | `klytos_get_build_status` |
| 30 | Preview before publish | ✅ | page, block, template previews |
| 31 | Runs on cheap PHP shared hosting, no Node/Docker | ✅ **core differentiator** |
| 32 | CDN / deploy-target integrations (Netlify, Pages, S3) | **unknown** — likely ❌ | Gap vs Jamstack expectations |

### 3.3 SEO / AI discoverability

| # | Feature | Klytos? |
|---|---|---|
| 33 | sitemap.xml, robots.txt, canonical, hreflang | ✅ |
| 34 | Meta tags, Open Graph, JSON-LD structured data | ✅ |
| 35 | `llms.txt` | ✅ | Contested value: Google's 2026-05-15 guidance says it is **not needed** for AI Overviews/AI Mode; Anthropic and OpenAI do recommend it. Yoast auto-generates it for WordPress |
| 36 | Analytics, cookieless | ✅ |

### 3.4 Compliance (EU-weighted)

| # | Feature | Klytos? |
|---|---|---|
| 37 | Cookie consent banner + prior blocking of non-essential scripts | ✅ **built-in — rare** | GDPR requires non-essential scripts not load before consent; one-click rejection is becoming mandatory across member states |
| 38 | Consent audit trail | ✅ |
| 39 | GDPR data export / erasure (DSAR) | ✅ | privacy tools |
| 40 | WCAG 2.1 AA / European Accessibility Act posture | ⚠️ partial | Skill exists (`klytos-accessibility`); PROGRESS.md records accessibility as **[TBD]** |
| 41 | i18n / multilingual content + hierarchical localised URLs | ✅ | 20 UI locales |

### 3.5 Extensibility & security

| # | Feature | Klytos? |
|---|---|---|
| 42 | Plugin system with hooks/filters | ✅ | 411 hooks |
| 43 | Plugin licensing exception for non-GPL plugins | ✅ | declared |
| 44 | Plugin marketplace / discovery | ❌ | Major ecosystem gap |
| 45 | Encryption at rest, bcrypt, RSA identity keys | ✅ |
| 46 | CSRF, CSP nonces, KSES-style output filtering | ✅ |
| 47 | Rate limiting on the MCP endpoint | ✅ | `mcp/rate-limiter.php` |
| 48 | **File-integrity verification with signed manifests** | ✅ **RSA-signed — genuinely ahead** | WordPress uses **MD5 checksums from wordpress.org**. 2026 saw real supply-chain attacks through the *official update channel*: the **ShapedPlugin** family compromised ~2026-06-18, and 30+ bought-and-backdoored plugins |
| 49 | Self-updater | ✅ |
| 50 | Sandboxed / capability-scoped plugins | ❌ | EmDash's headline feature (Dynamic Workers) |

### 3.6 AI / MCP surface

| # | Feature | Klytos? |
|---|---|---|
| 51 | MCP server as a first-class interface | ✅ **172 tools, deeper than any PHP CMS** |
| 52 | OAuth 2.0/2.1 on the MCP endpoint | ✅ | Directus, Statamic-addon and Storyblok also have this; **self-hosted WordPress still has no OAuth solution** |
| 53 | Schema/config authoring by agent (not just content CRUD) | ✅ | Strapi, Payload, Hygraph and Prismic **cannot** do this |
| 54 | Agent-facing guides / skills shipped with the product | ✅ | `guide-tools.php`, `.claude/skills/` |
| 55 | Tool annotations (read-only / destructive hints) | **unknown** | Skill mentions annotations; coverage not verified |
| 56 | Risk-tiered tool split (read / mutate / destroy) | ❌ | Storyblok's pattern |
| 57 | Per-agent identity + role + full audit trail of agent actions | **unknown / likely partial** | **Directus's pattern and the single most valuable thing to copy** |
| 58 | Staging/workspace so agent edits don't hit production | ❌ | **TYPO3's pattern; LightCMS's content-forking pattern** |
| 59 | Approval workflow for agent writes | ⚠️ partial | Post statuses + page templates approval exist; not a generalised agent-approval gate |
| 60 | MCP resources & prompts (not just tools) | **unknown** | |
| 61 | Elicitation / sampling / Multi-Round-Trip (SEP-2322) | **unknown** | 2026-07-28 spec adds Tasks, MCP Apps, Multi-Round-Trip |
| 62 | Multi-provider AI (BYO key: Anthropic/OpenAI/Gemini/local) | ✅ | `ai-tools.php`, `klytos_ai_list_providers` |
| 63 | AI image generation | ✅ |
| 64 | AI translation | ✅ |

### 3.7 Monetisation / bot economics

| # | Feature | Klytos? |
|---|---|---|
| 65 | x402 micropayment gating for AI bots | ✅ | **EmDash also ships this.** See §6 for the honest read on demand |
| 66 | Pay-per-crawl / RSL / crawler licensing metadata | **unknown** | RSL (Really Simple Licensing) is the emerging machine-readable licence standard alongside llms.txt |

---

## 4. External demand signals

Each item is a specific, citable demand. HN URLs are `news.ycombinator.com/item?id=<ID>`.
Quotes are verbatim unless marked. Venues covered: **Hacker News** (direct comment retrieval),
**GitHub issues/discussions** across 7 CMS repos, **Reddit** (via a Redlib mirror). Venues
**not** covered: Lobsters, Indie Hackers, Product Hunt, WordPress.org forums, WPTavern — treat
those as unexamined, not as negative evidence.

**Ranked summary** (detail below). Note that three of Klytos's marketed pillars land in the
bottom half of this table:

| Theme | Strength of demand | Klytos exposure |
|---|---|---|
| WP lock-in / non-technical client editing | **Very strong** (D-1) | ✅ core thesis |
| i18n in headless & git-based CMS | **Very strong** (D-16) | ✅ **underexploited asset** |
| AI/MCP control of a CMS | **Strong** (D-4, D-9, D-11) | ✅ core thesis |
| Safe AI write access | **Strong but latent** (D-17) | ⚠️ **biggest open opportunity** |
| AI builder lock-in | **Moderate** (D-6) | ✅ |
| Lightweight / shared hosting, no Node/Docker | **Weak** (D-5) | ⚠️ **marketed as a pillar, weakly evidenced** |
| GDPR / consent tooling | **None found** (D-13) | ⚠️ **marketed pillar, zero user pull** |
| x402 / AI-crawler payment | **None found, actively deteriorating** (D-14) | ⚠️ **marketed pillar, cut it** |

### D-1 — "A pretty CMS on the backend that your clients can still edit," on static output. A 12-year-old, still-unsolved demand.

> "Webhook… deploys static sites while allowing **a pretty CMS on the backend that your clients
> can still edit**." — *snide*, HN 8270949 (2014)

> "**No content editor should have to use anything much beyond a Word-like interface**" —
> *frereubu*, HN 18194002 (2018), on non-technical users struggling with Markdown; proposes a
> WordPress-like CMS that publishes static files.

> Static site generators are only viable "**if it were only techies updating it**" —
> *tragic*, HN 7809074 (2014)

> "the nontechnical people I was building them for **struggled to manage them**… brittle
> WordPress themes and poking around dashboards and juggling plugins" — *mmmateo*, HN 36813319
> (2023), launching Primo (a visual CMS + SSG) for exactly this reason.

> "Most of the clients we have, like using Wordpress, **because they are non-technical and can
> still have an extremely powerful website**" — *jordan801*, HN 20480885 (2019), arguing headless
> CMS is unnecessarily complex for agency client work.

Advice pattern still current in 2024: HTML → Markdown → static generator → **"WordPress when
client editing is needed"** (*abetusk*, HN 39817932).

**The 2026 evidence is far stronger still.** The Cloudflare EmDash thread (2026-04-01, **703
points, 504 comments**, HN 47602832) is the single densest concentration of this demand found
anywhere:

> "I'm locked into WordPress, **which I hate**, because it's the only platform that will allow a
> non-developer to maintain it **if I get hit by a bus**." — *sp1nningaway*, HN 47604676

> "the reason I'm still on WordPress isn't loyalty. It's that **my clients can maintain their own
> sites without me**… That's not a feature of WordPress. **That IS the product**… It was the guy
> who runs a bakery being able to edit his own website on a Sunday morning." — *JoostBoer*, HN 47611712

> "**An alternative for these customers does not exist.**" — *busterarm*, HN 47603981

> "there is **no real alternative to Wordpress for a free and open-source CMS** that is
> straightforward to install and usable to build and edit pages by non-tech-experts."
> — *ufmace*, HN 43841016 (2025)

And a near-specification for Klytos, unprompted:

> "Someone, anyone, AI coded or not, please work on a **COMPLETE successor to Wordpress**…
> batteries included… designed to be deployed into modern clouds… **but also self-hostable on a
> single server, or colocated by small (cheap!) providers**… **one-click migration from
> Wordpress**… A lot of people, myself included **pay for Wordpress hosting while also hating it
> and being ready to leap at an alternative — even if it cost more**." — *penglish1*, HN 47606093

> "The problem with WordPress… is that it's **way too cumbersome and bloated**… It's filled with
> meaningless admin notices, the sidebar is 5 miles long and about 98% of what the user sees is
> meaningless to them. **Creating a very lightweight, minimal UI for the client to edit exactly
> what they need**… really is the best solution in most cases." — *SunshineTheCat*, HN 47603761

The AI-generated-site variant of the same problem, from r/vibecoding (2025-12-05, [1peqkak](https://www.reddit.com/r/vibecoding/comments/1peqkak/)) — which received **zero comments**, i.e. unmet *and* unanswered:

> "when I deliver these sites to clients… the code is hard coded. **Clients cannot easily edit
> content on their own.**" / "I am looking for a way to **keep my AI generated code based
> workflow, while still delivering websites that clients can edit** through an intuitive CMS
> interface."

**This is precisely Klytos's slot.** The complaint recurs across twelve years — 2014, 2018,
2019, 2023, 2024, 2025, 2026 — with rising intensity. It is the strongest signal in this scan.

⚠️ **Two counter-signals that deserve equal weight**, both from experienced practitioners in the
same high-engagement threads:

> "Go ahead and give your content people access to a static site builder and see how quickly the
> process falls apart. **Static site generators are perfect for engineers but terrible for the
> marketing people** that are the actual 'customers' of your public-facing website."
> — *linkjuice4all*, HN 47608551

> "Those criticisms just aren't relevant to these clients… **PHP and errors doesn't mean anything
> to them**, that's a problem for their contractor to fix." — r/webdev [1lzb8yq](https://www.reddit.com/r/webdev/comments/1lzb8yq/) (2025)

The second is the sharper warning: **the developer-side pain Klytos solves is not pain the
client feels or pays for.** The buyer is therefore the contractor, never the site owner (§6.3).
And *Primo* (HN 36813319) is one of several prior entrants that tried this exact slot — it is
well-known and repeatedly attempted, which cuts both ways (§6.4).

### D-2 — The static-first direction is what CMS users say they want, against the industry's serverless drift.

On "The CMS is dead, long live the CMS" (HN 47638075), one of the most upvoted responses argued
CMSs should go **the opposite way from more server-side code — toward static, simple,
easy-to-cache files**.

> "These sites **cost 0 eur to run**, and they always score better in all benchmarks compared
> to sites built on separate CMS" — *tappio*

> "I expect we'll see a further wave of CMS interfaces which provide a nicer editing experience
> on top of flat files stored in Git" — *simonw*

Counter-signal, and a real product risk: > "With Git, my read-eval-print loop so to say was
**a minute which is just too long**" — *huijzer*. Build latency is a UX tax on static CMSs.

### D-3 — WordPress maintenance burden is actively pushing people out — and AI is what makes leaving feasible.

> "**20+ plugins creating a security nightmare. With AI assistance, we migrated to Hugo in
> three days**" — *torm*, HN 47638075

> "Every headless cms I've used has been much less of a headache compared to Wordpress" —
> *nothinkjustai*, same thread

Costed: a basic WordPress site runs **$600–$1,500/yr in maintenance** before design or content;
core updates 3–4×/yr, plugin updates weekly, "each one can break something."
<https://logoswebdesigns.com/blog/wordpress-alternative-static-sites-small-business/>

Counter-signal, quoted honestly: > "nothing beats its versatility, its rights management and
options. **There is always a plugin for that**" — *_the_inflator*, same thread. Ecosystem, not
architecture, is what retains WordPress users — and it is exactly what Klytos lacks (§3.1 #44).

### D-4 — People are already using AI as their webmaster, and say guard rails are the hard part.

> "I use AI as a **full blown web master**… **having good guard rails and investing in those is
> critical here**" — *jillesvangurp*, HN 47638075

The cleanest "someone is already living the use case" quote found:

> "**I use Claude Cowork to talk to my (remote) CMS over MCP to continually improve all content
> in my website.** If I find a new nugget of interesting information, I tell it to improve my
> content with it." — *spiderfarmer*, HN 47385232 (2026-03-15)

Framed explicitly as *replacing* the admin panel, not supplementing it:

> "are we planning to do something about payload MCP? **With the MCP, we can CRUD the collections
> via AI rather go to the payload portal to manage data.**"
> — [payloadcms/payload#13451](https://github.com/payloadcms/payload/discussions/13451), 7 upvotes

> "we'd need features like **installing plugins & themes and further control of the appearance**…
> I would definitely consider forking this plugin to build the above features"
> — [Automattic/wordpress-mcp#61](https://github.com/Automattic/wordpress-mcp/discussions/61)

And the agency-side version, which is close to Klytos's exact pitch:

> "I've become really tired of the bloat with Elementor. **The speed of building with Claude Code
> is making Elementor feel way less efficient**, especially for clients who don't really need or
> want the drag and drop / full edit control on their site."
> — r/Wordpress [1ul3szd](https://www.reddit.com/r/Wordpress/comments/1ul3szd/) (2026-07-02), 30 pts

⚠️ Counter-signal: > "imagine building a whole MCP server to automate copy-paste from ChatGPT
window into Wordpress editor and clicking Submit button" — *slt2021*, HN 44274184.

This cluster validates the AI-first thesis *and* states that the moat is governance, not tool
count (see D-17).

### D-5 — PHP shared hosting remains the deployment floor that nothing else reaches.

> "Deploying PHP software is as easy as subscribing to a shared web host… **extracting a ZIP
> file into the main directory**" — *selfhoster11*, HN 29262338

> "**Even $5/month shared hosting plan has it. Simply put — PHP is everywhere!**" — *pkrotich*,
> HN 34336205 ("Ask HN: Why does PHP dominate the CMS space?")

> "Finally one project with php and mysql that I can throw on a cheap shared hosting. **No docker
> of node_modules fuckup**" — *ulrischa*, HN 47650650 (2026-04-05)

> "You can start with simple shared hosting, copy your files into the server and you are done.
> **No docker, nothing.**" — *cardanome*, HN 47801040 (2026-04-17)

Corroborated by the EmDash controversy: Mullenweg's defence of WordPress is explicitly about
running "on a random web host in Indonesia charging 99 cents a month."
<https://ma.tt/2026/04/emdash-feedback/>

⚠️ **HONEST CORRECTION — this is the WEAKEST of the nine themes, and Klytos markets it as a
pillar.** The dedicated sweep found **no Reddit thread where someone wanted a CMS specifically to
avoid Node/Docker**, and r/selfhosted is strongly **Docker-positive**. GitHub searches for
"shared hosting" across the Payload and Decap repos returned **zero**. The plausible explanation
is selection bias — this cohort self-selects out before adopting, so it never generates issue
traffic — but that explanation is unfalsifiable from this data. **Treat the sentiment as real
and the demand as unquantified.** This materially qualifies §6.2.

### D-6 — Lock-in is the loudest, best-documented complaint about AI site builders.

> "Most AI website builders **trap your site in their proprietary systems**, and if you want to
> leave, you lose your design, functionality, and often your SEO."
> <https://www.turbopress.pro/blog/when-ai-website-builders-fail>

> "…if you ever decide to migrate to a more flexible platform, you may find the only practical
> option is to **rebuild the site from scratch**."
> <https://www.techradar.com/pro/website-building/5-downsides-of-using-an-ai-website-builder-how-to-overcome-them>

Wix, concretely: "you cannot view the source code that renders your pages, **because there is
no traditional source code**." <https://www.nocodeexport.com/en/blog/export-wix-website-guide>
Squarespace 7.1 **has no XML export at all**. Hostinger Horizons export is **one-way**. One
documented case of a builder shutting down **with 48 hours' notice**, taking the site with it.

### D-7 — AI-built sites are failing in production, and security is the specific failure.

- Red Access found **380,000+ publicly accessible web assets** across leading vibe-coding
  platforms, **2,000+ holding sensitive corporate/personal data**, deployed without basic
  access controls, **often granting admin access by default** (May 2026).
  <https://thehackernews.com/2026/05/what-2000-exposed-vibe-coded-apps.html>
- **At least 35 CVEs disclosed in March 2026 were the direct result of AI-generated code.**
  <https://www.infosecurity-magazine.com/news-features/how-safeguard-vibe-coding-security/>
- **Moltbook** (founder wrote zero code) had its production DB found open within days:
  ~1.5M auth tokens and 35,000 emails exposed.
- Projects with high "vibe scores" were **3.2× more likely to be abandoned within 18 months**.
- Red Hat Developer, Feb 2026: > "Generating code and building sustainable software are not the
  same thing. **The gap between a working demo and a production system remains vast.**"
  <https://developers.redhat.com/articles/2026/02/17/uncomfortable-truth-about-vibe-coding>

**Read this as demand for exactly what Klytos is:** a bounded, hardened, opinionated system the
agent *operates*, rather than an agent free-writing an application from scratch.

### D-8 — Supply-chain compromise through the official update channel is now the top CMS security fear.

2026 saw the **ShapedPlugin** family compromised via the vendor's update infrastructure
(~2026-06-18) and **30+ bought-and-backdoored plugins**. "The June 2026 incidents are different
because the malicious code arrived through **the official update or CDN channel, the exact path
that update advice tells you to trust**."
<https://wppoland.com/en/wordpress-supply-chain-attacks-2026/> ·
<https://threat-modeling.com/shapedplugin-wordpress-update-flow-supply-chain-attack-june-2026/>

Directly validates Klytos's RSA-signed integrity manifests (§3.5 #48) — a feature that is
usually a hard sell and now has a news hook.

### D-9 — Developers want introspection and clean structure for agents, not more chat boxes.

> "AI coding assistants write **generic** WordPress code because **they can't see your
> project's structure**" — *thanos_el*, HN 46876717 (Feb 2026), launching WordPress Boost MCP

> "LLMs consuming web content… get full browser HTML — **90% nav/scripts/ads. Content is 10%**"
> — *jcviau*, HN 47127446 (Feb 2026), building clean-JSON adapters for WP/Drupal/Joomla

### D-10 — MCP itself has vocal skeptics, including on the security model.

> "**MCP solves the wrong problem.** The mechanics of calling tools… isn't all that hard" —
> *jillesvangurp*, HN 46552740 (Jan 2026), arguing sandboxing and version control matter more.

> "most small MCP deployments probably have **zero visibility**… people ship a server and never
> look" — *suzuridev*, HN 48946483 (Jul 2026)

And a warning aimed squarely at this space: *cess11* (HN 48222062, May 2026) warns of
**repeating the WordPress plugin exploitation pattern with the MCP/extensions ecosystem**.

### D-11 — MCP *is* being used successfully for migration — an underexploited wedge.

> "I used one to **migrate a site from WordPress to Sanity**… much quicker and more flexible
> than whipping up a singular migration tool" — *james2doyle*, HN 46318384 (Dec 2025)

Klytos already ships `klytos-importer` (WP XML, sitemap, crawl). This quote says the *agent-
driven migration* framing is the one that lands.

### D-12 — EU digital sovereignty is a live, funded procurement tailwind.

The European Commission published its **Technological Sovereignty Package on 2026-06-03**,
containing a new **EU Open Source Strategy** that "places open source at the centre of the EU's
technological sovereignty by promoting **European open alternatives to non-EU proprietary
solutions**." The EU spends an estimated **€264bn/year predominantly on proprietary IT**,
and awarded a sovereign cloud tender of up to **€180M over six years** in April 2026.
<https://digital-strategy.ec.europa.eu/en/policies/open-source-strategy> ·
<https://interoperable-europe.ec.europa.eu/interoperable-europe/news/tech-sovereignty-package-eu-open-source-strategy> ·
<https://www.techpolicy.press/how-the-eus-tech-sovereignty-package-finally-puts-open-source-to-the-test/>

Corroborating signal in the CMS world specifically: the European Commission ran a **Drupal AI
hackathon in Brussels, Jan 2026** (~80 participants).

### D-13 — GDPR consent is actively enforced and is a real, recurring build cost.

Fines up to **€20M or 4% of global turnover**; regulators coordinate cross-border audits;
**GDPR does not exempt SMEs**. Technically: **non-essential scripts must not load before
consent** (prior blocking), **one-click rejection is becoming mandatory** across member states,
and equal prominence of Accept/Reject is required by several regulators. Separately the
**European Accessibility Act** puts WCAG 2.1 AA in scope for e-commerce and digital services
with 2025–2026 deadlines.
<https://www.cookieyes.com/blog/eu-cookie-compliance/> ·
<https://changetower.com/european-compliance-requirements-2026>

Essentially none of the SSG/AI-builder competitors in §1–2 ship consent tooling in core.

⚠️ **HONEST CORRECTION — regulatory pressure is real; USER PULL IS ZERO.** A dedicated sweep
found **no demand signal whatsoever**: GitHub search for GDPR/cookie-consent across six CMS
repos (2024-01 onward) returned **zero results**; HN searches for consent-tooling demand
returned **zero hits**. What HN does contain is abundant meta-debate about whether banners are
even required — e.g. *chuckadams*, HN 45979818: > "Cookie consent banners and such come from the
**ePrivacy Directive, not the GDPR**. The banners themselves were never mandated."

**Nobody is asking a CMS to provide this.** The market evidently treats consent as a
plugin/third-party-CMP concern, out of CMS core scope. That does not make Klytos's consent
tooling worthless — it is a genuine differentiator for the EU agency buyer (§6.3) and a
**billable deliverable**, which is different from a demanded feature — but it will not pull a
single user in on its own, and §5/§6 are corrected accordingly.

### D-14 — ⚠️ NEGATIVE EVIDENCE: developer sentiment on paying AI crawlers is predominantly hostile.

The *infrastructure* is unambiguously real: Cloudflare + AWS shipped x402 at the edge; the
**x402 Foundation launched under the Linux Foundation April 2026** with AWS, Cloudflare,
Anthropic and Circle; **169M payments / 590k buyers / 100k sellers** in year one; Cloudflare
customers send **1bn+ HTTP 402 responses to AI crawlers daily**.
<https://blog.cloudflare.com/x402/> · <https://www.infoq.com/news/2026/07/cloudflare-aws-x402-micropayment/> · <https://blog.cloudflare.com/introducing-pay-per-crawl/>

But sentiment among the developers who would self-host Klytos is **~9 critical to 2 supportive**
across HN comments sampled:

> "**AI extortion**" — *cpncrunch*, HN 46164196 · > "They create a new problem and sell the
> solution" — *ceejayoz*, HN 48327646 · > "It will not be pay-per-crawl… It will be an
> attention game" — *aeon_ai*, HN 46709962 · > "This is nonsense" — *mrcwinn*, HN 46709602 ·
> "Cloudflare may have a financial incentive if they are taking a cut" — *khurs*, HN 48759245

Supportive: > "This plus a /crawl endpoint is genius" — *carloslfu*, HN 47335261.

**And the picture is worse than sentiment — the model is actively deteriorating:**

- **Zero bottom-up demand in the CMS space.** A GitHub sweep for `x402` and `AI crawler` across
  **all seven** CMS repos (Strapi, Payload, Directus, TinaCMS, Decap, Automattic/wordpress-mcp,
  WordPress/wordpress-develop) returned an **empty result array in every single one.**
- **The market ignored it even when shipped.** In the 504-comment EmDash thread, x402 drew
  **exactly three comments**, one being *CodeWriter23* (HN 47606046): > "The lede everyone is
  burying: 'Every EmDash site has x402 support built in'" — the feature landed and nobody cared.
- **Cloudflare ABANDONED pay-per-crawl in July 2026**, replacing it with pay-per-answer on the
  grounds that crawls are a poor proxy for value. Only **two commercial partners** at launch.
  <https://ppc.land/cloudflare-stops-charging-ai-per-crawl-and-starts-paying-per-answer/>
- **Volume collapsed ~77%** from the Nov 2025 peak ($5.15M) to $1.19M by May 2026. Artemis
  (Feb 2026): *"The x402 'agent payments' boom is still mostly a mirage"* — roughly **half of
  observed transactions are self-dealing or wash trading**. CoinDesk: *"the merchants that x402
  is designed to serve are still rare."*
  <https://www.coindesk.com/markets/2026/03/11/coinbase-backed-ai-payments-protocol-wants-to-fix-micropayment-but-demand-is-just-not-there-yet>
- Practitioners, verbatim: *simonw*, HN 46972340: > "I have no idea if it actually works as
  advertised though. **I don't think I've heard from anyone trying it.**" · *cphoover*, HN
  48752233: > "**why would the people running these bots pay when they're already getting what
  they need for free?**" · *yieldcrv*, HN 48803799: > "This renders **x402 for consumer
  applications dead in the water**."
- **RSL is the partial exception** — 1,500+ publishers endorsing (AP, Guardian, Vox, Stack
  Overflow) — but *_kidlike*, HN 45336772: > "In its current state, RSL is not a full solution…
  **The spec does not cover payments.**"

**Interpretation — revised and hardened.** The infrastructure has real corporate backing but
**no validated user pull in the CMS space, collapsing volume, ~50% wash trading, and its
flagship implementer walked away within a year.** For a small self-hosted site, per-crawl
revenue rounds to zero. This is not a "demote the pitch" situation; see §5 F-3 and §6.5 — **cut
it from the marketing entirely.**

### D-15 — ⚠️ NEGATIVE EVIDENCE: nobody is asking for "an AI-first CMS."

Across HN searches for MCP + CMS, the entire result set was low-engagement Show HNs:
"Hostbento.com – MCP server to host websites designed in AI assistants" (2 points, 0 comments,
2026-01-07); "EmDash vs. WordPress 2026" (5 points, 1 comment). Even **Cloudflare's** entry into
this exact category generated near-zero organic HN discussion. Demand is expressed as
*"I want my existing site to be editable by AI"* and *"I want less maintenance"* — **not** as
*"I want a new AI-native CMS."* This is the central marketing problem, not a data gap.

### D-16 — ⭐ i18n / multilingual is the highest-volume unmet defect cluster in the entire category — and Klytos already has it.

This was the biggest surprise of the scan and is **badly underexploited by Klytos's marketing**.

- **TinaCMS has no i18n at all**, and is still at the "should we investigate?" stage:
  > "**Conduct a spike to investigate the potential for implementing internationalization (i18n)
  > support in TinaCMS.**" — [tinacms#4753](https://github.com/tinacms/tinacms/issues/4753),
  > opened 2024-08-29, **still open**, 9 reactions.
- **Decap's core i18n issue has been open since 2020** with 19 reactions; a user even supplied a
  working patch and could not get it merged:
  > "I cloned this repo locally and did a quick test… this edit… **seems to provide the solution
  > I am looking for, but I am not savvy enough to create all the tests required to form a clean
  > PR**" — [decap-cms#4416](https://github.com/decaporg/decap-cms/issues/4416)
- **Payload:** > "clicking 'Publish in Global' also publishes every other locale. This isn't
  expected behaviour." / "These two in combination make **localization feel very broken**."
  — [payload#14672](https://github.com/payloadcms/payload/issues/14672) (2025-11-19, open)
- **Strapi:** a *regression* — > "One locale shouldn't override another locale's content… i saw
  there were similar issue reported such as #20409 and been fixed in v5.30. **but i can still
  reproduce it.**" — [strapi#25650](https://github.com/strapi/strapi/issues/25650) (2026-03-05)
- **Strapi AI translation is already failing in the field:** > "a translated value exceeding a
  field's maxLength **fails the whole localization job with a generic 'AI translation failed'**"
  — [strapi#26579](https://github.com/strapi/strapi/issues/26579) (2026-06-08)

Volume: **Strapi alone had ~40 distinct i18n issues filed in 2025–2026.** Decap's open i18n
cluster carries 40+ combined reactions across issues open 4–6 years.

**Klytos ships 20 locales, hierarchical localised URLs, AI translation, and translation-source
management as core MCP tools.** Against a field where the leading git-based CMS can't merge a
6-year-old patch and the leading Node CMS hasn't started, this is a **genuine, demanded,
defensible advantage that is currently mentioned nowhere in the positioning.**

### D-17 — ⭐ Safe AI write access: severe, well-documented pain that users describe in incident stories, not governance vocabulary.

This theme has the **highest strategic value** in the scan: the pain is acute and repeatedly
documented, but almost nobody searches for it in governance language, so it looks absent to
keyword research and is under-served accordingly.

**The incidents:**

> "Asked Claude Code to run a database migration last week. **It deleted my production database
> instead**, then immediately said 'sorry' and started panicking trying to restore it… Anyway,
> **AI coding assistants no longer get prod credentials on my projects.**"
> — *victorbuilds*, HN 46104985 (2025-12-01)

> "The pattern across all of these: **the agent was NOT malfunctioning.** It was completing its
> task in order to reach its goal, and any rules you give it are malleable. **The fuckup was
> that the task boundary wasn't enforced outside the agent's reasoning loop.**"
> — *kstenerud*, HN 47500015 (2026-03-24), cataloguing Replit, Cursor, Claude Code and Snowflake incidents

> "No engineer with proper common sense will grant an agentic AI, API access to the database."
> — *h4kunamata*, HN 48727426

**Applied to CMSs specifically — two unprompted comments in the same r/Wordpress thread:**

> "my first approaches were to use Claudeus mcp which works fine but **feels like a recipe for
> disaster on a customers production site**."

> "Letting it SSH-edit production directly is just **Elementor bloat replaced with faster mystery
> meat**." — r/Wordpress [1ul3szd](https://www.reddit.com/r/Wordpress/comments/1ul3szd/) (2026-07-02)

**Someone already shipped the approval layer and it resonated** (r/divi
[1raq45p](https://www.reddit.com/r/divi/comments/1raq45p/), 2026-02-21, 28 pts):

> "so **most developers just avoid using AI on Divi sites entirely. not because AI can't handle
> it. because the risk isn't worth the convenience.**" / "**AI never touches live pages / works
> on a copy, you review in WordPress admin, then publish or trash.**"

**And the incumbents have documented, open gaps in exactly this area:**

> "All queries are logged under one token — **no per-agent audit trail**. If the token is leaked
> in agent logs, all collections are exposed. **No way to revoke 'just the agent's access'**
> without breaking the human's integration."
> — [directus#27664](https://github.com/directus/directus/issues/27664) (2026-06-02)

> "claude.ai uses these hints to group tools (e.g. **separating read-only tools from tools that
> modify data**), and to drive **how/when a tool requires confirmation**. Without them, every
> Payload-generated tool is treated identically, which makes the tool list **harder to reason
> about and approve**." — [payload#16744](https://github.com/payloadcms/payload/discussions/16744)

> "We successfully bolted **OAuth 2.1** onto the plugin in our own projects, but it required
> **duplicating roughly a thousand lines per app**"
> — [payload#16745](https://github.com/payloadcms/payload/discussions/16745), 7 upvotes

> Strapi audit logging is missing precisely where agent-relevant actions live: "the follow parts
> of the admin interface **do not generate any audit log trails: API Tokens**… Users-Permissions"
> — [strapi#23493](https://github.com/strapi/strapi/issues/23493), **open over a year**

**This is the clearest product opportunity in the document.** Klytos already has OAuth 2.0 in
core (which Payload users are hand-rolling at ~1,000 lines per app) and a build/publish
separation that makes the "AI works on a copy, you review, then publish" pattern natural. See
§5 A-1/A-2/A-3.

---

## 5. AI / MCP added-value opportunities specific to Klytos

Labelled per Keel Phase 1: **added value** (with the reason it actually helps) or
**forced filler** (AI for AI's sake — dropped, not softened).

### 5.1 Added value — high conviction

**A-1. Per-agent identity, role and a complete agent audit trail.** *Copy Directus.* Every MCP
client gets a first-class Klytos user with a role; every tool call lands in an audit log
attributed to that identity, with before/after state. This is the #1 unlock for the only buyer
who will ever pay (agencies, §6): it converts "I let an AI touch a client's site" from
negligence into a defensible, reviewable process. Directly answers D-4 ("guard rails… critical")
and D-10 ("zero visibility… people ship a server and never look"). Guardrail literature
converges on the same minimum: scoped permissions, consequence-based approvals, run-level audit
logs, and a kill switch.

**A-2. An agent staging workspace — edits never hit production unreviewed.** *Copy TYPO3's
workspaces and LightCMS's content-forking.* Klytos has a structural advantage nobody else has
here: **it already separates content state from a built artefact**. An agent branch that builds
to a preview URL and merges on human approval is a natural extension of the existing build
engine, not a new subsystem. This is the single most differentiating thing Klytos could ship,
and it is the direct answer to D-7 (AI-built sites failing in production).

**A-3. Risk-tiered tools and annotations.** *Copy Storyblok's read / mutate / destroy split;
copy Prismic and Hygraph in removing `delete`/`publish` from the default agent surface.* 172
undifferentiated tools is a large blast radius and a real prompt-injection target — note the
project's own D-003 threat model (tool poisoning, confused deputy). Low effort, high trust
return.

**A-4. Agent-driven migration as the wedge.** D-11 shows migration is where MCP already
demonstrably wins, and D-3 shows WordPress users want out but find leaving expensive. "Point
Claude at your WordPress site and get a static Klytos site" is a *concrete, demonstrable,
tweetable* value proposition — unlike "AI-first CMS," which D-15 shows nobody searches for.
`klytos-importer` already exists; this is packaging and demo work, not new architecture.

**A-5. ⭐ Multilingual as the demanded differentiator — promote it to the front.** D-16 is the
highest-volume unmet defect cluster in the category: Tina has **no i18n**, Decap's core issue has
been open **since 2020**, Strapi and Payload both have live localisation regressions, and
Strapi's AI translation already fails on field-length errors. Klytos ships 20 locales,
hierarchical localised URLs, AI translation and translation-source management **as core MCP
tools**. "Agent-driven multilingual site management that actually works" is a claim backed by
demonstrable competitor failure — the rarest kind of differentiator, and it is currently absent
from Klytos's positioning entirely. **Highest ratio of existing capability to marketing effort
in this document.**

**A-6. Compliance as an agent capability — real, but reclassified.** Klytos is the only product
in this scan with consent tooling, GDPR DSAR and integrity checking in core, and exposing these
as MCP tools an agent can *audit and remediate* is genuinely novel. ⚠️ **But D-13 found zero user
pull**: no GitHub or HN demand across six repos. Treat this as a **sales asset for the EU agency
buyer and a billable deliverable** (§6.3), **not** as an acquisition driver. Build it because it
differentiates a paid engagement; do not expect it to attract users.

**A-7. Ship MCP resources and prompts, not only tools.** Guides already exist (`guide-tools`);
exposing them as MCP **prompts** and site state as **resources** reduces the context an agent
must be spoon-fed and improves first-try correctness. Cheap. Directly answers D-9 ("they can't
see your project's structure").

### 5.2 Added value — conditional

**A-8. Build-latency mitigation.** D-2's counter-signal (a one-minute loop "is just too long")
is the standing UX risk of any static CMS. Incremental build already exists; making agent-facing
tools return build timing and default to per-page rebuild is worth doing before adding features.

**A-9. Semantic search over site content, exposed via MCP.** Genuinely useful for internal
linking, duplicate detection and content audits at scale. Sanity ships `semantic_search`. Only
worth it if it does not add a heavy dependency — a vector store would violate the shared-hosting
constraint that is Klytos's actual moat.

### 5.3 Forced filler — drop

**F-1. "AI writes your content" features.** Every builder in §1.4 has this; it is a
commodity that the model provider supplies for free. It is not a reason to choose a CMS.

**F-2. An in-admin AI chat panel.** The whole thesis is that the agent lives in the user's
own client (Claude, Cursor, ChatGPT). Building a second-rate chat UI inside the admin panel
contradicts the positioning and competes with the model vendors' own surfaces.

**F-3. x402 as a monetisation pitch — cut it.** The revised evidence in D-14 is decisive:
**zero demand across seven CMS repos, ~77% volume collapse, ~50% wash trading, and Cloudflare
itself abandoned pay-per-crawl in July 2026.** Even EmDash shipping it drew three comments out of
504. Keep the code (it is built, cheap to maintain, and harmless), but **remove it from the
positioning entirely** — it currently signals misread priorities to exactly the technical
audience Klytos needs. If any part is retained in marketing, retain **bot access control**
(blocking/gating crawlers, which people do want) and never the income story.

---

## 6. Honest assessment

No flattery. The verdict is at the end.

### 6.1 Is "AI-first CMS" defensible in 2026? — **No. Not as stated.**

The positioning as written on klytos.io — *"The first CMS designed to be controlled entirely by
artificial intelligence"* — is **factually no longer true and was already contestable when
written**. Sanity shipped an official MCP server in **April 2025**. Twelve vendors shipped one
before or around Klytos's public releases. And the specific claim "first" is the kind of thing
that costs credibility with exactly the technical audience Klytos needs.

More seriously, **"AI controls the CMS" has been commoditised from both directions in 2026**:

**(a) WordPress.** The Abilities API is **in core** (6.9), WordPress 7.0 shipped an AI client
and services registry, and the MCP adapter is official. Klytos's MCP surface is genuinely
deeper *today* (172 typed tools vs 3 generic discovery tools), but that gap is a function of
contributor count and time, not architecture — and WordPress has 42.4% of the web, every host,
every agency, and WooCommerce already shipping abilities. The honest read: **WordPress will not
beat Klytos on MCP depth soon, and will not need to.** Distribution decides this, not tools.

**(b) AI site builders.** Lovable is at **$500M ARR** and shipped a ~50-tool official MCP
server. Wix made **every site an MCP server** and put itself inside ChatGPT. For the
non-technical end of the market, this fight is already over and Klytos was never in it.

**(c) The one that actually hurts: EmDash.** Cloudflare shipped, in April 2026, an MIT-licensed,
self-hostable, MCP-native, agent-skills-shipping, **x402-supporting** CMS with static-leaning
output — and it has **11.3k stars to Klytos's 10**. On a feature-by-feature basis EmDash
matches or exceeds Klytos's entire differentiation list except the runtime. Klytos is not
first, and is not the best-resourced. Pretending otherwise in the docs will make every technical
reader discount everything else the project says.

### 6.2 What IS defensible

One thing, and it is narrow but real:

> **A PHP-native CMS where BOTH the MCP server AND static generation are in core, running on
> €3/month shared hosting with no Node, no Docker, no Composer and no vendor account.**

The scan confirms this intersection is **unoccupied** (§2.4). Statamic is closest but is core +
two separately-maintained addons, needs Laravel/Composer, and Pro costs $349/site. Grav's MCP is
a **Node sidecar** and Grav isn't an SSG. WordPress's static story is plugin-only with WP2Static
dead. Drupal's Tome is "minimally maintained." EmDash needs Node or Workers, and its headline
plugin-sandbox security only works on **paid Cloudflare accounts** — the exact vendor lock-in
Balkhi and Mullenweg attacked it for.

⚠️ **But one load-bearing plank of that sentence is weakly evidenced.** D-5 found **no thread
where anyone wanted a CMS specifically to avoid Node/Docker**, zero GitHub hits for "shared
hosting" in Payload or Decap, and an r/selfhosted culture that is strongly Docker-*positive*.
The selection-bias explanation (this cohort never files issues) is plausible and unfalsifiable.
So the defensible position should be stated as **"runs anywhere, including €3/month PHP hosting"**
— a *removal of a constraint* — rather than as a benefit users are actively demanding. It is a
qualifier that widens the funnel, not a headline that fills it.

**The genuinely demanded, under-marketed assets — in evidence order:**

1. **⭐ Multilingual (D-16).** The highest-volume unmet defect cluster found anywhere in the
   category. Tina has none; Decap's is 6 years open; Strapi and Payload both have live
   localisation regressions. Klytos has 20 locales, localised hierarchical URLs and AI
   translation **in core, as MCP tools**. This is the strongest demand/capability match in the
   entire document and it appears **nowhere** in Klytos's positioning.
2. **⭐ Safe agent write access (D-17).** Severe, repeatedly documented pain — production
   databases deleted, "recipe for disaster on a customers production site" — with open,
   unaddressed gaps at Directus (no per-agent audit trail), Payload (no tool annotations;
   OAuth hand-rolled at ~1,000 lines/app) and Strapi (audit logging absent on token actions,
   open >1 year). Klytos already has OAuth 2.0 in core and a build/publish split that makes
   "agent works on a copy, human approves, then publish" natural.
3. **RSA-signed integrity manifests** (§3.5 #48) against WordPress's MD5-from-wordpress.org, in
   a year with real update-channel compromises (D-8).
4. **EU-origin, GPL, self-hosted, zero-vendor** at the exact moment the European Commission
   funds "European open alternatives to non-EU proprietary solutions" (D-12). EmDash is a
   Cloudflare product; every headless CMS in §1.2 is US SaaS. **Klytos is structurally eligible
   for a procurement category none of them can enter.**
5. **GDPR consent + DSAR** — ⚠️ **downgraded.** D-13 found **zero user pull** across six repos
   and HN. Real differentiator *within a paid engagement*, not an acquisition driver.

### 6.3 Who is the realistic buyer?

**Not** end users. D-15 is unambiguous: nobody searches for "AI-first CMS," and even Cloudflare
couldn't generate organic discussion for one. Small business owners are served — badly but
cheaply and with marketing budgets Klytos cannot match — by Wix, Durable and GoDaddy. D-1's
counter-signal is decisive here: *"PHP and errors doesn't mean anything to them, that's a problem
for their contractor to fix."* **The pain Klytos removes is pain the client never feels.**

**The realistic buyer is the small agency or solo freelancer in the EU who builds and maintains
5–50 brochure/content sites for SMB clients.** For that person the value chain is concrete:

- They already pay $600–$1,500/yr/site in WordPress maintenance (D-3) and it is pure margin loss.
  Verbatim: *"WooCommerce: Plugin update roulette. Theme breaks checkout at 2am on a Saturday…
  I'm debugging someone else's spaghetti code for free"* (r/webdev [1tgihui](https://www.reddit.com/r/webdev/comments/1tgihui/), 102 pts).
- Static output removes the plugin-update treadmill and most of the attack surface (D-2, D-7).
- MCP means *they* drive the build with an agent they already pay for — a real speed gain
  (D-4: *"the speed of building with Claude Code is making Elementor feel way less efficient"*).
- **Clients still need a human admin panel; Klytos has one.** This is the whole reason headless
  CMSs lose this buyer — D-13's vendor-side admission: *"Headless is a developer optimization,
  not a client feature… the client ends up with essentially abandonware"* (*solardev*, HN 45137033).
- **Multilingual client sites are a paid speciality and the competition is broken at it** (D-16).
- GDPR consent + accessibility posture are **billable deliverables** in the EU, shipped in core —
  valuable to sell with, not to acquire with (D-13).
- Runs on cheap hosting, so client sites cost near-nothing and hand over cleanly — a constraint
  removed rather than a demanded feature (D-5).

The strongest single validation of this buyer, unprompted: *"I'm locked into WordPress, which I
hate, because it's the only platform that will allow a non-developer to maintain it if I get hit
by a bus"* and *"A lot of people, myself included, pay for Wordpress hosting while also hating it
and being ready to leap at an alternative — even if it cost more"* (D-1).

That is a coherent buyer with a real budget. It is also a **small, hard-to-reach, low-willingness-
to-pay market that GPL software cannot easily monetise** — which is the honest catch.

### 6.4 The single biggest strategic risk

**It is not WordPress, and it is not EmDash. It is that Klytos has 10 stars and one maintainer.**

Everything in §6.2 is true and none of it matters without distribution and a bus factor above 1.
The scan is blunt on this: **LightCMS (23 stars) is architecturally the closest thing to Klytos
in existence and has no traction. Seite has 13 stars. VoxelSite — PHP, shared-hosting,
MCP-native, static, AGPL, i.e. Klytos's positioning almost exactly — has 4 stars.** The
graveyard of this category is full of *technically correct* products. Being right about the
architecture is demonstrably not sufficient, and the pattern is consistent enough to be
predictive.

Compounding it: a CMS is a **trust purchase with a decade-long time horizon**. An agency will
not put a client's site on a single-maintainer CMS at v0.31.1-beta with no plugin marketplace
(§3.1 #44), no third-party ecosystem, and no evidence of a contributor base. The 2026
open-source sustainability discourse says adoption becomes durable when projects have "visible
contribution models, long-term maintenance capacity and organisations willing to support the
ecosystem" — Klytos currently demonstrates none of the three publicly.

Secondary risk, worth naming: **the PHP MCP window is closing.** The official `mcp/sdk` is now
maintained by Symfony + The PHP Foundation with Anthropic. Joomla has a core-integrated PHP MCP
PoC. Drupal already ships on the SDK. Within ~12 months "PHP CMS with native MCP" will be
unremarkable, and the differentiator narrows to static-output-in-core alone.

### 6.5 What to cut, what to double down on

**Cut / stop:**
- The **"first CMS controlled entirely by AI"** claim. It is false, checkable, and expensive.
- **x402 from the positioning entirely** (D-14, F-3) — not demoted, removed. Zero demand across
  seven CMS repos, ~77% volume collapse, ~50% wash trading, and Cloudflare abandoned
  pay-per-crawl in July 2026. Keep the code; delete the pitch.
- **GDPR consent as an acquisition message** (D-13) — zero user pull found. Move it from the
  homepage to the sales deck, where it genuinely earns money.
- Any **in-admin AI chat panel** ambition (F-2) and **AI content generation** as a selling
  point (F-1).
- Breadth-first tool growth. **172 tools is already more than any PHP competitor.** Tool #173
  buys nothing; agent *governance* buys everything.

**Double down:**
- **Agent governance** (A-1, A-2, A-3) — per-agent identity, audit trail, staging workspace,
  risk-tiered tools. **This is now the #1 recommendation, not the #2.** D-17 shows the pain is
  severe and the incumbents' gaps are open and documented: Directus has *"no per-agent audit
  trail"*, Payload users hand-roll OAuth at *"roughly a thousand lines per app"*, Strapi's audit
  logging gap has been open over a year. Klytos already has OAuth 2.0 and a build/publish split.
  **This is a lead the project can actually take and hold.**
- **⭐ Multilingual (A-5, D-16)** — promote from unmentioned to front-page. Highest ratio of
  existing capability to marketing effort in this document, backed by demonstrable competitor
  failure (Tina has none; Decap's issue is 6 years open).
- **Migration as the wedge** (A-4). Ship a 3-minute "WordPress → static Klytos, driven by
  Claude" demo. That is a *shareable artefact*; "AI-first CMS" is not. D-1's *penglish1* quote
  explicitly names **"one-click migration from Wordpress"** as the missing piece.
- **The client-editing claim** — lead with D-1, the strongest and most repeated demand in the
  scan. "Your client can still edit it on a Sunday morning; your agent does everything else."
- **The true technical claim**: PHP + core MCP + core static, running anywhere. Say it against a
  named alternative (Statamic's $349/site, Grav's Node sidecar, EmDash's paid Workers,
  WP2Static's death). Specificity is the whole marketing asset — but frame cheap hosting as a
  removed constraint, not a demanded feature (D-5).
- **EU sovereignty** (D-12) — a genuine moat against every US SaaS and against Cloudflare,
  aligned with funded EU procurement.
- **Fix the trust signals before the features**: correct the README's stale counts, get off
  `-beta`, publish a security policy and disclosure process, document the update-signing chain,
  and — most important — **recruit a second maintainer**. Nothing else in this document changes
  outcomes if the bus factor stays at 1.

### 6.6 Verdict

**PROCEED — with a mandatory repositioning.**

The product is real, unusually complete for its age, and occupies a genuinely unoccupied
technical intersection (§2.4). Building it was not a mistake. But **"AI-first CMS" is a dead
positioning in 2026** — commoditised by WordPress core, out-marketed by $500M-ARR builders, and
out-resourced by Cloudflare in the exact niche. Continuing to lead with it wastes the project's
one real advantage.

Reposition to **"the CMS your AI agent can safely operate — and your client can still edit —
that outputs static HTML and runs anywhere"**. The load-bearing word is **safely** (D-17), the
proof point is **your client can still edit it** (D-1, the strongest demand in the scan), and
the under-used weapon is **multilingual** (D-16).

Four conditions on the proceed, in priority order:
1. **Fix positioning and trust signals** (§6.5) — weeks, not months. Cut x402 and the "first
   CMS" claim; promote client-editing and multilingual.
2. **Ship agent governance** (A-1, A-2, A-3) as the next substantive feature block, ahead of any
   new tool breadth. This is the one place where the whole category is demonstrably thin and the
   incumbents' gaps are documented and open.
3. **Ship the WordPress-migration demo** (A-4) as the single shareable artefact.
4. **Treat contributor acquisition as a product requirement**, not a nice-to-have. If the bus
   factor is still 1 in twelve months, the honest recommendation flips regardless of how good
   the code is.

If the goal is a widely-adopted CMS, the realistic assessment is that it will not happen —
distribution, not architecture, decides this category, and that race is lost. If the goal is an
excellent, sustainable tool serving a defined EU agency/freelancer niche, with the author as the
first and most demanding user, that is achievable and worth doing. **The user should decide
explicitly which of those two goals this is**, because they imply different roadmaps, and this
document cannot make that choice.

---

## 7. Scan status

**Status: PARTIAL.**

### Covered well
- Headless CMS official MCP support (12 vendors, verified against docs and repos).
- AI site builders (16 products) incl. pricing, export/lock-in, MCP, traction.
- SSG + CMS combos, PHP flat-file CMSs, classic PHP CMS field.
- WordPress Abilities API / MCP Adapter status, verified against make.wordpress.org and the
  developer blog rather than secondary coverage.
- EmDash, verified against the repo and multiple independent press sources.
- Klytos's own claims re-verified against this repository rather than the README.
- x402 / pay-per-crawl: both the infrastructure reality and the negative evidence — including
  the July 2026 Cloudflare pivot away from pay-per-crawl and the volume/wash-trading data.
- **§4 demand signals**: Hacker News (direct comment retrieval), GitHub issues/discussions across
  7 CMS repos, and Reddit (via a Redlib mirror). Both positive demand and **explicit negative
  evidence** (GDPR, x402, shared hosting) were sought and recorded.

### Could NOT be verified — explicit gaps
1. **Venue gap in §4**: Lobsters, Indie Hackers, Product Hunt, WordPress.org support forums and
   WPTavern were **not examined** — that sweep failed to return. Treat as unexamined, **not** as
   negative evidence.
2. **Reddit required a Redlib mirror** to access; direct search tooling returned nothing for
   r/selfhosted, r/webdev, r/wordpress and r/PHP across multiple attempts. Several HN Algolia
   fetches returned **HTTP 429**, and the session's **web-search budget (200 calls) was
   exhausted**. §4 is therefore HN- and GitHub-weighted relative to Reddit.
3. **Two r/nocode threads used in D-6 show signs of promotional seeding** (repeated cross-account
   plugs for the same product in near-identical phrasing). Flagged inline; their engagement
   metrics should not be read as organic. The underlying sentiment matches broader patterns.
4. ⚠️ **Methodological caveat worth stating plainly**: the strongest counter-signals in §4 come
   from the *same* high-engagement threads as the strongest positive signals. Experienced
   practitioners repeatedly argue that clients don't care about developer-side pain and that
   boring tools win. That pushback is recorded inline and carries equal weight; it is the main
   reason §6.3 concludes the buyer is the contractor and never the site owner.
5. **Tool counts unpublished or contradictory**: Storyblok (claims range ~5 to 155+), Contentful
   (docs 70+ vs repo 40+), Hygraph, Prismic, Webflow, EmDash.
6. **Contentstack GA status** — developer docs say "experimental," agent-os docs read GA.
7. **Klytos internals not verified by this scan**: backup scope/restore path (§3.1 #23),
   multi-site (#24), CDN/deploy integrations (#32), MCP tool annotations coverage (#55),
   agent audit-trail completeness (#57), MCP resources/prompts (#60), elicitation/sampling
   (#61), RSL support (#66). These are answerable from the codebase and should be resolved
   before §3 is treated as final.
8. **Accessibility posture** is `[TBD]` in PROGRESS.md; §3.4 #40 is marked partial on that basis.
9. **Maintenance status unverified** for Datenstrom Yellow, php-flat-file, SiteCake,
   lightweight-cms, Pico, WonderCMS.
10. **TinaCMS MCP absence** is inferred from no results, not confirmed by Tina.
11. **"First-party" status of any Statamic MCP** — sources conflict; evidence favours community.
12. **Decap unpatched-XSS claim** comes from a third-party review, not Decap advisories.
13. **ARR figures** are from aggregators (Sacra, GetLatka, Tracxn), not filings — except Wix,
    which is SEC-filed.
14. **NomaCMS / ElmapiCMS / MDCMS / LightCMS details** rest on single or self-interested sources.

### Note on method
Per Keel Phase 1 step 0, the scan ran in **four parallel subagents** returning conclusions rather
than raw dumps (headless-CMS MCP; AI site builders; SSG + PHP CMS field; demand signals), plus
direct verification in the main session. Klytos's own claims were re-verified against this
repository rather than accepted from the README — which is how the stale 160-tools / 75-hooks
figures were caught.

One subagent's output was flagged by the harness for containing instruction-shaped text;
inspection showed it was a factual description of a third-party product (Seite auto-starts via a
client-side settings file). **No configuration was changed as a result, and nothing in that
output was treated as an instruction.**

§4, §5 and §6 were **revised after drafting** when the demand-signals subagent returned late with
evidence that contradicted the first draft in three places: x402 was downgraded from "keep but
demote" to "cut" (Cloudflare's July 2026 pivot), GDPR consent was downgraded from differentiator
to sales asset (zero user pull), and shared hosting was downgraded from pillar to constraint-
removal. Two new demand clusters (i18n, safe agent write access) were added and both changed the
recommendations. **The prior draft's conclusions are superseded by the current text.**

The user's own competitor list was never collected — Keel step 0 requires asking *"Which
competitors / similar projects do you already know about?"* upfront. **That question is still
outstanding** and its answer may add entries this scan missed.
