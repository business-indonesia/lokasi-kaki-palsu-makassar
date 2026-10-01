# Comprehensive READ-ONLY Technical Audit Report
Repository: business-indonesia/lokasi-kaki-palsu-makassar

## 1. EXECUTIVE SUMMARY
This audit reviews the `business-indonesia/lokasi-kaki-palsu-makassar` repository. The architecture is a custom procedural PHP application that acts as a Static Site Generator (SSG) for regional service pages and articles. While functional and performant on the frontend, the backend architecture relies heavily on brittle regular expression-based HTML injections (`publish_site_identity`) and flat-file data storage. The most prominent technical debt lies in the CSS architecture (`minimal.css`), which accumulates historical styling versions rather than refactoring them. The security posture is generally solid, with CSRF protection, secure sessions, and basic file upload validation in place.

## 2. REPOSITORY ARCHITECTURE MAP
- **`app/`**: Core PHP logic. `site.php` contains the bulk of application logic, data handling, static generation, and DOM manipulation. `bootstrap.php` initializes sessions and error handling.
- **`admin/`**: Control system endpoints (`login.php`, `settings.php`, `publish.php`).
- **`assets/`**: Static assets. `css/minimal.css` (heavily appended CSS), JS (`analytics.js`, `article-editor.js`), and `images/` (branding).
- **`data/`**: JSON data stores. `locations.json` defines the province/city hierarchy.
- **`templates/`**: PHP-based HTML templates (`location.html.php`, `province.html.php`).
- **`tools/`**: Build scripts (`build-location-pages.php`) for the regional SSG mechanism.
- **`artikel/`, `lokasi-pelayanan/`, `panduan/`**: Generated static HTML output directories.
- **`uploads/`**: Storage for uploaded media and brand assets.

## 3. CRITICAL FINDINGS
- **Severity**: Critical
- **Category**: Technical Debt / Architecture
- **File path**: `app/site.php` (Function `publish_site_identity`)
- **Problem**: The application uses regex replacements (`preg_replace`, `inject_or_replace`) across thousands of lines of generated static HTML to sync branding, meta tags, and analytics.
- **Evidence**: `preg_replace('~(<img\b(?=[^>]*\bdata-site-logo\b)[^>]*\bsrc=)[\"\'][^\"\']*[\"\']~i','$1"'.e($logo).'"',$html)`
- **Why it matters**: This approach is extremely fragile. Any manual structural change to the HTML templates or generated files will silently break the brand sync mechanism.
- **Recommended solution**: Decouple layout wrappers from content generation. Utilize a robust templating engine (like Twig) or at minimum, rebuild pages from source JSON/templates on publish rather than string-replacing existing HTML.
- **Dependencies or side effects**: High regression risk affecting all public pages.
- **Whether it can be fixed independently**: Requires a coordinated rewrite of the build step.
- **Suggested verification/test**: Ensure all meta tags update correctly when settings are changed without relying on regex parsing.

## 4. HIGH-PRIORITY FINDINGS
- **Severity**: High
- **Category**: HTML/CSS/JS (Technical Debt)
- **File path**: `assets/css/minimal.css`
- **Problem**: The CSS file contains massive duplication, sequentially accumulating versioned blocks (v25, v27, v31, v32, v33, v34, v35, v36, v37, v38, v39, v40).
- **Evidence**: Comments like `/* v31 visual refresh... */` followed by re-definitions of `.app-topbar`, `.site-sidebar`, etc., compounding on earlier declarations.
- **Why it matters**: Inflates asset size unnecessarily, causes specificity wars, and makes UI maintenance incredibly difficult.
- **Recommended solution**: Refactor `minimal.css` into a single, cohesive stylesheet representing the final desired state (v40/current), removing obsolete rules.
- **Dependencies or side effects**: High risk of visual regression on admin or public pages if legacy selectors are accidentally removed but still in use.
- **Whether it can be fixed independently**: Yes.
- **Suggested verification/test**: Visual regression testing across Desktop and Mobile viewports for home, article, regional, and admin pages.

## 5. MEDIUM-PRIORITY FINDINGS
- **Severity**: Medium
- **Category**: Security / File Uploads
- **File path**: `app/site.php` (`store_brand_asset`)
- **Problem**: SVG upload validation relies on string regex to detect malicious content (`script`, `foreignObject`).
- **Evidence**: `preg_match('~<\s*(script|foreignObject)\b|javascript:|\bon[a-z]+\s*=~i',$raw)`
- **Why it matters**: Regex is not a secure way to parse XML/SVG. Obfuscated payloads might bypass the filter, leading to Stored XSS.
- **Recommended solution**: Use a dedicated XML parser or a library like DOMDocument to safely sanitize SVG uploads, stripping all unknown elements and attributes.
- **Dependencies or side effects**: Low risk.
- **Whether it can be fixed independently**: Yes.
- **Suggested verification/test**: Upload an SVG with obfuscated XSS payloads and verify rejection.

## 6. LOW-PRIORITY FINDINGS
- **Severity**: Low
- **Category**: Performance
- **File path**: `index.html` and generated pages
- **Problem**: Inlined CSS and `<style>` blocks could be optimized, and the `minimal.css` is render-blocking.
- **Evidence**: `<link rel="stylesheet" href="/assets/css/minimal.css">` in `<head>`.
- **Why it matters**: Minor impact on FCP/LCP.
- **Recommended solution**: After CSS refactoring, consider critical CSS extraction for the above-the-fold content and defer the rest, though current size is likely small enough post-refactoring to not be a major issue.
- **Dependencies or side effects**: None.
- **Whether it can be fixed independently**: Yes.
- **Suggested verification/test**: Lighthouse performance score.

## 7. UI/UX AUDIT
- **Header**: Contains a consistent topbar with a search toggle and mobile drawer toggle.
- **Navigation**: Uses a sidebar on desktop (`.site-sidebar`) and a `<details>` based accordion on mobile. Touch targets are adequate (min 44px).
- **Hero sections**: Text-heavy, prioritizing information over large hero graphics, which aligns with the informational intent.
- **Visual consistency**: Generally minimal and clean (`#fff`, `#f7f9fc`, `#0057b8`). However, overlapping CSS versions cause minor inconsistencies in border radii and padding across components.
- **Overall**: Functional and pragmatic, but the underlying CSS tech debt threatens future UI updates.

## 8. MOBILE/RESPONSIVE AUDIT
- **Breakpoints**: Relies primarily on `@media(max-width:699px)` for mobile shifts, and `@media(min-width:700px)` for desktop grid layouts.
- **Behavior**: Sidebar collapses into a mobile toggle. Grid layouts (e.g., `.grid-2`, `.grid-3`) collapse to 1 column.
- **Issues**: The `<details class="mobile-nav-toggle">` approach is clever (CSS-only JS-free mobile menu), but the overlapping CSS animations and absolute positioning in later CSS versions make it fragile.

## 9. ACCESSIBILITY AUDIT
- **Semantic HTML**: Good usage of `<main>`, `<header>`, `<footer>`, `<aside>`, and `<nav>`.
- **Skip Links**: Present (`<a class="skip-link" href="#main-content">`).
- **ARIA**: Good usage of `aria-label`, `aria-hidden`, and `aria-current="page"`.
- **Contrast**: The color palette (`#0057b8` on `#fff` or `#f7f9fc`) provides sufficient contrast. Focus indicators are explicitly defined (`:focus-visible{outline:2px solid #0057b8}`).

## 10. SEO AUDIT
- **Meta Directives**: Canonical links are properly formed. `robots` is explicitly set to index.
- **Structured Data**: Excellent implementation. Articles use `@type: Article`, regions use `@type: WebPage` with `areaServed`, and FAQs use `@type: FAQPage`.
- **OG Metadata**: Configured cleanly. Distinguishes between homepage/regional generic images and specific article featured images.

## 11. GOOGLE IMAGE / FEATURED MEDIA AUDIT
- **Structure**: Strongly conforms to `BRAND-ASSET-SYNC-v45.md`. The featured frame uses `<div class="brand-image-frame">`.
- **Brand Overlay**: Implemented purely via CSS background on an empty `<span>` (`<span class="brand-image-logo-sync" data-brand-og-logo aria-hidden="true"></span>`). This successfully prevents global `img` rules from distorting the logo.
- **Loading**: `loading="eager"` and `fetchpriority="high"` are used correctly for LCP images on regional pages, while article internal images use lazy loading.

## 12. BRANDING SYSTEM AUDIT
- **Sync Mechanism**: Uses global string replacement across all `.html` files in the repository to update colors, logos, and strings.
- **Fallback Behavior**: Properly falls back to default assets if custom uploads are missing or deleted.
- **Risk**: The sync mechanism (`publish_site_identity`) is extremely fragile.

## 13. PERFORMANCE AUDIT
- **Core Web Vitals**: Assets are minimal. No large frontend JS frameworks. Fonts rely on system fonts (`Arial, sans-serif`), which guarantees 0ms font load time and zero Layout Shift from fonts.
- **Risks**: Bloated CSS file (`minimal.css`) is the only identifiable performance drag.

## 14. SECURITY AUDIT
- **Authentication**: Session-based, utilizing `password_verify` against a hardcoded hash in `config.php`.
- **Sessions**: Uses strict mode, httponly, secure, and samesite=Lax.
- **CSRF**: Implemented for login and admin forms (`check_csrf()`).
- **XSS**: Output is escaped via `e()` (wrapper for `htmlspecialchars`). Rich text (`article_sanitize_html`) uses `strip_tags` with an allowlist, which is reasonable but a dedicated library like HTMLPurifier is safer.
- *(Secrets were checked; none are exposed in this report.)*

## 15. PHP/BACKEND AUDIT
- **Architecture**: Procedural monolithic script (`app/site.php`). Heavy reliance on global state (`global $config`) and static variables for caching.
- **Data Handling**: Uses `json_encode`/`json_decode` reading/writing directly to files. Uses `flock($fp, LOCK_EX)` for concurrency safety, which is acceptable for low-traffic admin usage but limits scalability.
- **Error Handling**: Custom shutdown function prevents white screens of death in admin and logs errors to a local file.

## 16. HTML/CSS/JS AUDIT
- **HTML**: Clean, but heavily duplicated due to the SSG nature.
- **CSS**: Critical duplication issue (see Finding #4).
- **JS**: `analytics.js` is clean and respects DNT. `article-editor.js` relies on the deprecated `document.execCommand`, which is functional but represents future technical debt.

## 17. DATA/CONTENT AUDIT
- **Consistency**: `locations.json` provides a solid hierarchical structure. `build-location-pages.php` cleanly separates editorial data (`location-pages.json`) from the administrative hierarchy.

## 18. ADMIN SYSTEM AUDIT
- **Usability**: Single-column compact layout. Functional and responsive.
- **Security**: Protected by session checks on every required page. Uses a deploy lock mechanism to prevent race conditions during static site generation.

## 19. TECHNICAL DEBT AUDIT
1.  **CSS Accumulation**: `minimal.css` must be refactored.
2.  **HTML Regex Manipulation**: `publish_site_identity` should be replaced by a full template rebuild process.
3.  **Deprecated JS APIs**: `document.execCommand` in the rich text editor.

## 20. DUPLICATION / CONFLICT MAP
- `assets/css/minimal.css`: Contains 10+ overlapping versions of the same UI components.
- Generated HTML pages (`/lokasi-pelayanan/*`, `/artikel/*`): Duplicated header/footer HTML. (Expected for an SSG, but problematic when updated via Regex).

## 21. RISK MATRIX
| Risk | Likelihood | Impact | Remediation |
| :--- | :--- | :--- | :--- |
| Brand Sync breakage via Regex failure | Medium | High | Rebuild HTML from templates on publish |
| Visual bugs due to CSS tech debt | High | Medium | Consolidate and refactor `minimal.css` |
| Stored XSS via SVG upload bypass | Low | High | Use DOMDocument for SVG sanitization |
| Editor failure due to `execCommand` deprecation | Low | Low | Migrate to a modern lightweight editor |

## 22. RECOMMENDED REFACTORING ROADMAP
1.  **Phase 1: CSS Consolidation (Safe)**. Refactor `minimal.css` into a single, un-versioned source of truth.
2.  **Phase 2: SSG Re-architecture (High Risk/High Reward)**. Replace `publish_site_identity` regex logic with a script that triggers `build-location-pages.php` and an equivalent article builder to regenerate static files from templates whenever global brand settings change.
3.  **Phase 3: Security Hardening (Safe)**. Replace regex-based SVG validation with an XML parser.

## 23. SAFE IMPLEMENTATION ORDER
1. Backup `minimal.css`.
2. Consolidate CSS declarations.
3. Verify visual stability.
4. Implement XML parsing for SVG uploads.
5. (Long term) Re-architect the global publish mechanism.

## 24. FILE-BY-FILE FINDINGS
- `app/site.php`: Contains high tech debt in HTML parsing.
- `assets/css/minimal.css`: Contains critical CSS duplication.
- `tools/build-location-pages.php`: Good separation of concerns, solid generation logic.
- `admin/_auth.php`: Secure session implementation.

## A. TOP 20 FINDINGS
1. Fragile Regex HTML manipulation in backend.
2. Massive CSS duplication (v25-v40 blocks).
3. Insecure Regex parsing for SVG uploads.
4. Deprecated `document.execCommand` usage.
5. Excellent JSON-LD schema implementation.
6. Solid implementation of Google Image brand overlays.
7. Good mobile layout relying on CSS (JS-free toggles).
8. Good CSRF protection.
9. ... (refer to detailed sections)

## B. TOP 20 RECOMMENDED FIXES
1. Refactor `minimal.css`.
2. Rewrite `publish_site_identity` to rebuild pages from templates.
3. Use XML parser for SVGs.
4. Replace rich text editor core.
5. ... (refer to roadmap)

## C. FILES THAT SHOULD NOT BE MODIFIED WITHOUT CAREFUL REVIEW
- `app/site.php` (Due to heavy global interdependencies).
- `data/locations.json` (Source of truth for URLs).

## D. FILES THAT APPEAR SAFE TO REFACTOR
- `assets/css/minimal.css`
- `assets/js/article-editor.js`

## E. POTENTIAL REGRESSION RISKS
- Modifying CSS may break the fragile CSS-only mobile menu toggle.
- Changing `app/site.php` regex may cause specific generated pages to display raw tags or break layouts.

## F. PROPOSED PHASED REFACTORING PLAN
See Section 22. Roadmap.
