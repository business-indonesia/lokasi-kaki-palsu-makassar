# SECOND-PASS VALIDATION AUDIT REPORT
Repository: business-indonesia/lokasi-kaki-palsu-makassar
Status: READ-ONLY VALIDATION

## 1. CSS CONSOLIDATION
**Classification: CONFIRMED**
- **File path**: `assets/css/minimal.css`
- **Selector/Function**: Multiple (e.g., `.app-topbar`, `.site-sidebar`, `.mobile-nav-toggle`)
- **Concrete evidence**: The file is ~61KB. A `grep` reveals 16 distinct `/* vXX ... */` versioned block headers from v25 to v40. The `.app-topbar` selector is redefined 10 times, and `.site-sidebar` 26 times. Later blocks forcefully override earlier layout rules (e.g., changing from `display: block` to `display: flex` and moving absolutely positioned elements).
- **Affected pages/components**: All public and admin pages.
- **Actual risk**: High technical debt. Future UI changes require overriding 10+ layers of specificity, causing bloat and rendering unpredictability. Removing historical blocks without a strict DOM mapping audit runs a high risk of breaking older templates that might still rely on v25/v31 specific cascade rules. Consolidation is necessary but not trivial.
- **Confidence level**: High.
- **Recommended next step**: Safe CSS refactoring by extracting only the final computed styles (post-v40) and auditing them against the current `location.html.php`, `province.html.php`, and `admin/*.php` structures.

## 2. publish_site_identity()
**Classification: CONFIRMED**
- **File path**: `app/site.php`
- **Function**: `publish_site_identity()`
- **Concrete evidence**: This function calls `inject_or_replace` (a `preg_replace` wrapper) 27 times. It iterates over every generated `.html` file (`public_html_files()`) to inject Google Analytics tags, theme colors, logos, and taglines by string-replacing existing DOM tags.
  - Example: `preg_replace('~(<img\b(?=[^>]*\bdata-site-logo\b)[^>]*\bsrc=)[\"\'][^\"\']*[\"\']~i','$1"'.e($logo).'"',$html)`
- **Affected pages/components**: Every static HTML page in the repository (`artikel/*`, `lokasi-pelayanan/*`, `panduan/*`).
- **Actual risk**: Extremely fragile architecture. The SSG tools (`build-location-pages.php` and `publish_article`) generate the HTML files from templates. Later, when an admin changes a site setting, `publish_site_identity` modifies those generated strings. Any change in the base template's HTML structure (newlines, attribute ordering) will cause the regex to fail silently, resulting in desynced branding.
- **Confidence level**: High.
- **Recommended next step**: Deprecate regex HTML manipulation. Change the global "Save Settings" trigger to simply re-execute the SSG builders (`build-location-pages.php` and article loop) using the fresh configuration injected into the PHP templates.

## 3. SVG SECURITY
**Classification: PARTIALLY CONFIRMED (Risk is Low, but theoretically present)**
- **File path**: `app/site.php`
- **Function**: `store_brand_asset()`
- **Concrete evidence**: For SVG uploads, the validation relies on regex: `preg_match('~<\s*(script|foreignObject)\b|javascript:|\bon[a-z]+\s*=~i',$raw)`. It checks file extensions and MIME types. It stores the file in `/uploads/brand/` with a randomized filename.
- **Affected pages/components**: Logo and Favicon uploads.
- **Actual risk**: Regex cannot safely parse XML. An attacker could use XML entities or obfuscated encodings to bypass the regex and embed an XSS payload. However, because the SVG is loaded via `<img src="...">` in the HTML (which modern browsers sandbox and prevent script execution from), the execution risk is severely mitigated unless the user navigates directly to the `/uploads/brand/` SVG file. Also, this endpoint requires authenticated admin access.
- **Confidence level**: High.
- **Recommended next step**: Replace the regex check with DOMDocument to explicitly allowlist `<svg>`, `<path>`, `<circle>`, etc., and strip all attributes starting with `on`.

## 4. CONFIGURATION / SECRETS
**Classification: CONFIRMED (Secure)**
- **File path**: `.gitignore`, `app/config.php`
- **Concrete evidence**: `app/config.php` and `.env` are strictly excluded in `.gitignore`. `git ls-files` confirms no configuration or secret files are tracked in the repository.
- **Affected pages/components**: Core application security.
- **Actual risk**: None observed in the repository tracking.
- **Confidence level**: High.
- **Recommended next step**: Maintain current `.gitignore` practices.

## 5. FEATURED MEDIA / BRAND SYSTEM
**Classification: CONFIRMED (Strict Adherence)**
- **File path**: `templates/location.html.php`, `assets/css/minimal.css`
- **Selector**: `.brand-image-logo-sync`, `.brand-image-frame`
- **Concrete evidence**: The codebase adheres perfectly to `BRAND-ASSET-SYNC-v45.md`. The `data-brand-og-logo` is applied as a CSS background variable (`--brand-og-logo`) to an empty `<span class="brand-image-logo-sync" aria-hidden="true"></span>`.
- **Affected pages/components**: Regional and Article featured images.
- **Actual risk**: None. The design prevents global `img` rules from destroying the logo aspect ratio, and avoids duplicate logo tags. Fallback mechanisms correctly map `site_og_logo_url()` back to `site_logo_url()` if empty.
- **Confidence level**: High.
- **Recommended next step**: No action needed. Implementation is correct and safe.

## 6. MOBILE RESPONSIVE
**Classification: PARTIALLY CONFIRMED (Overstated robustness)**
- **File path**: `assets/css/minimal.css`
- **Selector**: `.mobile-nav-toggle`
- **Concrete evidence**: The application successfully uses `<details>` and `<summary>` for CSS-only dropdowns, avoiding JS. However, because of the CSS technical debt (Finding #1), the `.mobile-nav-toggle` rules are defined in v25, v31, and v32 with conflicting layout directives (`position: absolute`, `display: block`, `animation: menu-in`).
- **Affected pages/components**: Mobile header navigation on viewports `<=699px`.
- **Actual risk**: The overlapping rules make the mobile menu fragile. Changing padding or z-index in a future update could easily break the layout across 320px-414px viewports due to the messy cascade.
- **Confidence level**: High.
- **Recommended next step**: Clean up the mobile navigation CSS during the Phase 1 CSS consolidation to ensure only the final state (v32+) is applied.

## 7. SEO
**Classification: CONFIRMED (Excellent)**
- **File path**: `tools/build-location-pages.php`, `app/site.php`
- **Function**: `render_schema_location()`, `render_schema_province()`, `article_page_html()`
- **Concrete evidence**: JSON-LD is dynamically injected using `json_encode(..., JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)`. The schemas properly distinguish `BreadcrumbList`, `WebPage`, `Article`, and `FAQPage`. Canonical URLs (`<link rel="canonical">`), Open Graph metadata, and Twitter Cards are explicitly defined per content type. Image `alt` text is gracefully generated or falls back to titles.
- **Affected pages/components**: All public pages.
- **Actual risk**: None.
- **Confidence level**: High.
- **Recommended next step**: No action needed.

---

## FINDING CLASSIFICATIONS

### A. Findings that should NOT be changed
- The `.gitignore` tracking logic.
- The `BRAND-ASSET-SYNC-v45.md` CSS-based overlay logic (`brand-image-logo-sync`).
- The JSON-LD schema generation and SEO meta structures.
- The JS-free `<details>` approach for mobile menus (conceptually, though the CSS needs cleanup).
- The CSRF token validation and session configurations.

### B. Findings that should be fixed
- CSS redundancy in `minimal.css` (Consolidate v25-v40 into a single ruleset).

### C. Findings requiring architectural redesign
- `publish_site_identity()`: The regex-based HTML modification of generated static files must be replaced by a clean template rebuild mechanism.

### D. Findings requiring security hardening
- `store_brand_asset()`: SVG validation via Regex should be replaced with DOMDocument/XML parsing to securely strip scripts.

### E. Findings requiring only cleanup
- Remove `document.execCommand` from `assets/js/article-editor.js` and replace it with a modern, lightweight standard if editing functionality is expanded.

### F. Findings that were overstated or insufficiently supported
- The previous audit implied the CSS-only mobile navigation was "robust." While functional, the underlying CSS cascade makes it highly fragile to future edits. This is classified as Technical Debt rather than a bulletproof feature.
