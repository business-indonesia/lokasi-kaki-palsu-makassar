# Phase 2 — READ-ONLY ARCHITECTURE AUDIT: publish_site_identity() / Static Site Generation Refactor

## A. Current Execution Flow
The `publish_site_identity(string $scope='full', string $note='')` function in `app/site.php` follows this high-level execution flow:
1. **Initialize Dependencies & Variables**: Extracts site settings (name, description, URLs, logos, favicon, contact info) from the global state/configuration.
2. **Sync CSS Brand Logo**: Invokes `sync_brand_overlay_css($ogLogo)` to explicitly mutate `minimal.css` and ensure the CSS variable `--brand-og-logo` matches the square `ogLogo`.
3. **Scan Target Files**: Calls `public_html_files()` to generate an array of paths for all public static `.html` files in the repository.
4. **Iterate & Mutate HTML**: Iterates through each `.html` file.
   - Reads the file into a `$html` string.
   - Sequentially applies approx. 54 regex operations using `inject_or_replace()` and `preg_replace()` to manipulate the `theme-color`, robots tag, Google Site Verification, GA4 analytics, favicon paths, Open Graph tags, Twitter card tags, phone numbers, WhatsApp links, site brand text/logo across elements like topbar and footer, tagline injections, and homepage-specific SEO adjustments.
   - Evaluates context conditionals (like `$isHome`, `$isArticle`, `$isRegional`) to apply selective modifications (e.g., maintaining specific OG tags for articles/regional pages vs homepage).
5. **Disk Write**: Checks if `$html` string was mutated compared to the original, then writes the entire string back to the file with `file_put_contents`.
6. **Manifest Sync**: Processes `manifest.webmanifest`, decodes JSON, updates site name, colors, and icons, then encodes and writes it back to disk.
7. **Cleanup & Logging**: Generates an audit log to `storage/logs/` and clears public search caches (`public-search-index.json`, `public-html-files.json`).

## B. All publish_site_identity() mutation points
Below is an enumeration of the mutation targets handled by `inject_or_replace` and `preg_replace`:

*   **Branding & Visuals**:
    *   `theme-color` meta tag (injects/updates theme color).
    *   `img data-site-logo` (updates src attribute of images matching data-site-logo).
    *   `span.article-brand-logo` & `img.brand-image-logo-sync` (updates/replaces classes/data tags to `<span ... data-brand-og-logo ...></span>`).
    *   `a.topbar-brand` (updates label, brand string, and image).
    *   `footer strong` (updates copyright/brand string).
    *   `<small data-site-tagline>` (updates tagline content or injects it near footer).
    *   `<[tag] data-site-name>` & `<[tag] data-site-tagline>` (generic brand/tagline updates).
*   **SEO, Canonical & Discovery**:
    *   `robots` meta tag (forces `index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1`).
    *   `google-site-verification` meta tag.
    *   `title` and `meta description` (updates specifically on `$isHome`).
    *   `author` meta tag (updates on non-article pages).
*   **Open Graph (OG) & Twitter metadata**:
    *   `og:site_name`.
    *   `og:title`, `og:description`, `og:url` (updates on non-article pages).
    *   `og:image`, `twitter:image` (updates on non-article/non-regional pages).
    *   `twitter:card` (forces `summary_large_image`).
    *   `twitter:title`, `twitter:description` (updates on `$isHome`).
*   **Favicon & App Manifest**:
    *   `link rel="icon"`, `link rel="shortcut icon"`, `link rel="apple-touch-icon"`.
    *   Mutates `manifest.webmanifest` file directly (via JSON decode/encode).
*   **Contact Info (WhatsApp/Phone)**:
    *   Regex replacement of `https://wa.me/[0-9]+`.
    *   String replacement of hardcoded phone numbers like `0853 9484 9766` or `085394849766`.
*   **Analytics**:
    *   Injects/Removes `<script src="/assets/js/analytics.js" data-ga4="...">` right before `</body>`.

## C. Dependency/Helper map
*   `public_html_files()`: Recursively iterates over the file tree, filtering out specific paths to return public static `.html` files.
*   `sync_brand_overlay_css()`: Mutates `minimal.css` based on the given OG logo URL.
*   `inject_or_replace()`: Wrapper for `preg_replace`. Replaces if pattern matches exactly once, else leaves unmodified. Used for "safe" single injections.
*   `site_setting()`, `site_logo_url()`, `site_og_logo_url()`, `site_og_image_url()`, `site_favicon_url()`: Helpers to fetch settings and format URLs.
*   `gsc_verification_token()`, `ga4_measurement_id()`: Fetches analytics/verification keys.
*   `site_favicon_type()`: Infers MIME type for the favicon.

## D. Current risks
*   **HIGH**: **Idempotency and Strict Layout Coupling**. Repeated regex against generated HTML requires HTML to match perfectly. If templates or third-party markup change slightly (e.g., varying whitespaces, rearranged attributes, or multi-line structures), replacements will silently fail or match unintended markup.
*   **HIGH**: **Vulnerability to Duplicate Insertion**. When tags are missing, fallback operations append to `</head>` or `</body>`. Improper detection of existing tags could lead to duplicated scripts or meta elements.
*   **MEDIUM**: **File I/O Performance**. The current setup iterates over every HTML file on the file system, reads the entire contents into memory, processes ~54 regex patterns over the string, and writes it back synchronously. As the directory grows (especially with regional and article files), memory and execution time bloat significantly.
*   **MEDIUM**: **Order-Dependent Mutation**. Variables and checks rely on the fact that some injections happen before others. If logic changes, regex passes might collide or override one another.
*   **LOW/MEDIUM**: **Security/Trust-Boundary Issues**. Injections currently rely on passing strings through `e()` (which is `htmlspecialchars`), but injecting complex user-controlled metadata via regex opens edge-case XSS risks if escaping contexts mismatch.

## E. Behavior that must be preserved
1. **Generated HTML Structure**: The ultimate static structural output MUST remain identical to clients and search engines.
2. **Context-Aware Metadata**:
   - `isArticle`: Articles MUST preserve their own SEO titles, descriptions, canonical URLs, and featured images.
   - `isRegional`: Regional files MUST keep their own artwork/images (not overwritten by the canonical wide social image).
   - `isHome`: Homepage MUST sync explicitly specified homepage title, desc, and OG metadata.
3. **Brand Overlays**: The Featured Media brand overlay (`<span class="brand-image-logo-sync" data-brand-og-logo aria-hidden="true"></span>`) MUST be preserved as finalized in Phase 1.
4. **Site Manifest**: `manifest.webmanifest` synchronization behavior.
5. **Contact Injections**: Dynamic WhatsApp and phone number adjustments MUST propagate to all static elements.
6. **Analytics/GSC**: GA4 and Google Site Verification tokens MUST securely appear when defined and disappear when removed.

## F. Recommended architecture
**Option E: Hybrid Approach (Structured Generation + Limited Deterministic Post-Processing)**

**Rationale**:
The current site heavily relies on static HTML regeneration via functions like `article_page_html()`. A pure "canonical template variables" approach (Option A) would mean tearing up every generation function and replacing string concatenation with a robust templating engine (like Twig) — which exceeds the scope of a targeted refactor and breaks the current codebase's low-dependency philosophy.

Instead, the safest architectural refactor is to:
1. **Push static logic upstream**: Modify HTML-generating functions (`article_page_html()` and whatever generates regional/home pages) to dynamically request `site_identity` context directly during their initial string construction. This ensures newly built templates always have the correct analytics, metadata, and branding from the source.
2. **Placeholder/Tag replacement**: For files that *must* be updated post-generation without a full rebuild, implement a limited set of strictly defined data-attributes or placeholder strings (e.g., `<!-- SITE_IDENTITY_GA4 -->`) instead of fragile tag-matching regex.
3. **DOM Manipulation Engine (If post-processing is mandatory)**: For mutating thousands of existing static files without placeholders, use `DOMDocument` or a structured HTML parser instead of regex. A parser natively handles whitespace, quotes, and attribute ordering, completely mitigating the risk of malformed HTML and broken regex.

## G. Alternative architectures considered
*   **A. Canonical template variables / placeholders**: Ideal, but requires replacing the entire repository's string-based generation with a standard templating engine. High risk of missing hardcoded strings.
*   **B. Centralized site-identity rendering**: Creating a central class or function `SiteIdentity::renderHead()`. Recommended for the upstream changes in the hybrid approach, but doesn't solve mutating legacy static files alone.
*   **C. DOMDocument parsing (D. Limited deterministic post-processing)**: Safer than regex for modifying existing `.html` payloads, but comes with minor memory overhead.
*   **Conclusion**: Moving generation logic upstream (so `publish_site_identity` doesn't *have* to mutate files that were just built) combined with structured DOM modification for bulk updates is the safest path.

## H. Refactor boundaries
*   **In Scope**: `app/site.php` (specifically `publish_site_identity`, `inject_or_replace`, and generation helpers like `article_page_html` that can be refactored to pull identity data upstream).
*   **Out of Scope**: CSS modifications (Phase 1 completed this). Administrative UI logic, content writing workflows, or restructuring the `app/` bootstrap phase outside of `site.php`.

## I. Regression-test matrix
A thorough validation protocol for the implementation phase:
1. **Homepage Check**: Verify canonical title, desc, `og:image` (wide canonical), Twitter card, and `isHome` specific branding text injections.
2. **Article Page Check**: Verify custom SEO metadata, JSON-LD, article featured image (no canonical wide image overwrite), and correct brand frame markup.
3. **Regional Page Check**: Verify regional image preservation and correct contact info injection.
4. **Missing Brand Assets**: Verify fallback paths for `favicon`, `logo`, and `og_image` when optional assets are stripped from settings.
5. **Analytics**: Verify GA4/GSC injection correctly formats when present, and leaves no artifact tags when completely absent.
6. **Mobile UI / Manifest**: Verify `manifest.webmanifest` parsing/injection correctly reflects theme color and icon sizing.

## J. Risk assessment
*   **HIGH**: Risk of breaking legacy HTML structures if we switch to `DOMDocument` and the legacy files have unclosed tags (PHP's DOMDocument can be strict/quirky with malformed HTML5).
*   **MEDIUM**: Modifying `article_page_html()` upstream might accidentally omit a meta tag currently covered by the post-generation regex net.
*   **LOW**: Overwriting `minimal.css` in `sync_brand_overlay_css`. The regex here is scoped and simple.

## K. Exact files that would need modification in a future implementation
*   `app/site.php`

## L. Suggested implementation phases
1. **Upstream Standardization**: Refactor HTML generation functions (like `article_page_html()`) to call a unified `get_site_identity_head_tags()` and `get_site_identity_footer_tags()` function during creation.
2. **Structured Post-Processing Engine**: Replace the 50+ regex calls in `publish_site_identity()` with a robust HTML manipulation strategy (e.g., strict placeholder replacement or targeted DOM-based modification using data-attributes).
3. **Cleanup**: Deprecate `inject_or_replace()` once no longer needed.
4. **Validation**: Execute the regression-test matrix against `index.html`, `/artikel/...`, and `/lokasi-pelayanan/...`.
