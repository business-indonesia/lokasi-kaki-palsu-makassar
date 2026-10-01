# BRAND ASSET SYNC v45.4

## Canonical brand assets

- **Logo Website Custom** — PNG/JPG/WebP/SVG, max 2 MB. Used for the site header and brand identity.
- **Favicon Custom** — PNG/ICO/SVG, max 1 MB. Used for browser/app identity.
- **Logo OG Image** — PNG/JPG/WebP, **square 1:1**, 64–2048 px, max 2 MB. This is the small logo overlay inserted inside featured-image frames.

## Logo OG Image semantics

The field named **Logo OG Image** is intentionally a square logo asset. It is **not** the social Open Graph cover image and must not be uploaded as 1200×630.

The uploaded square asset is used in exactly one place per featured image:

1. Province/regional featured image frame.
2. District/city regional featured image frame.
3. Article featured image frame.

It is rendered as a small overlay inside the existing frame. The source featured image is not replaced, cropped, duplicated, or moved above the title.

## Social OG metadata

The site's social OG image remains a separate wide 1200×630 asset/default. It is not exposed as the square **Logo OG Image** upload and is never rendered as an extra hero image above article text.

Article pages continue to use their own featured image for `og:image`; the square Logo OG Image is only a visual in-frame brand mark.

## Synchronization rules

- Header `data-site-logo` always resolves to **Logo Website Custom**.
- Featured-frame `data-brand-og-logo` always resolves to **Logo OG Image**.
- Favicon links resolve to **Favicon Custom**.
- Existing featured-image artwork remains the source image for each region/article.
- No duplicate logo card, duplicate brand block, or duplicate site name/tagline is introduced.
- A global `figure img { width:100% }` rule must never resize the square brand overlay to full frame width.

## v45.6 audit hardening
- Regional and article featured media now share exactly one markup/CSS contract: `featured-media > brand-image-frame > image + brand-image-logo-sync`.
- Legacy article overlay classes removed from generated content and CSS.
- `data-brand-og-logo` is no longer attached to an `<img>`; the logo is a CSS background on a non-image span, preventing global image rules from enlarging it.
- Brand overlay CSS uses one `--brand-og-logo` variable and prefers a same-host relative URL.
- Four accidental nested duplicate directories were removed: `cari/cari`, `artikel/artikel`, `panduan/panduan`, `lokasi-pelayanan/lokasi-pelayanan`.
- The regional builder now removes those known legacy duplicate directories on a normal build and the audit confirms zero duplicate canonical URLs.
- Regional WebPage JSON-LD now includes the actual regional content image; the logo remains excluded from content image metadata.
- Province featured images now have descriptive alt text.
