# Website Strategy, UX, SEO & Launch — v45.7

## Product concept
Patient-first information and consultation hub for people with amputation and families seeking prosthetic and orthotic services: lower/upper-limb prostheses, finger prostheses, and orthotic support.

## Core principle
Start from the user's condition, activity, comfort and consultation needs—not from a product SKU.

## Sitemap
- Beranda
- Layanan
  - Kaki palsu
  - Tangan palsu
  - Jari palsu
  - Ortotik
- Proses
- Panduan
- Artikel
- Wilayah
  - Provinsi
  - Kabupaten/Kota
- Cari
- Legal

## Primary user flow
Landing → identify need → choose service → read guide → find service area → prepare questions → WhatsApp consultation.

## Featured media contract
Regional and article content use the same `featured-media > brand-image-frame > image + brand-image-logo-sync` structure. Both are 16:9, responsive, lazy/eager behavior is retained according to content priority, and the square Logo OG Image is rendered only as a small CSS background inside the frame.

Regional source artwork may contain an older embedded HM mark. v45.7 visually clears only that embedded mark in the featured frame and places the configured square Logo OG Image in the same area. The source artwork is not duplicated or re-encoded.

## Brand architecture
- Logo Website: header/site identity.
- Favicon: browser, touch icon and manifest.
- Logo OG Image: square 1:1 brand mark used only inside featured media.
- Social OG Image: wide social preview asset, kept separate from featured media.

## SEO / Google Image
- Keep the actual content image as the `Article.image` / regional `WebPage.image`.
- Keep descriptive `alt`, intrinsic width/height, crawlable image URLs, canonical URLs, Open Graph image metadata and JSON-LD.
- Do not use the small brand logo as the primary content image in structured data.
- Preserve WebP regional artwork and existing article featured media.

## Performance
No UI framework or new runtime dependency. The square logo is ~7 KB WebP. The overlay is CSS, not a second `<img>` element. Regional artwork is not rewritten; the embedded legacy mark is visually covered only in the featured frame. This avoids increasing the 552-image regional asset set.

## Accessibility
- Content images retain meaningful alt text.
- Brand overlay is `aria-hidden` because it is decorative branding inside an already-described media frame.
- Focus-visible styles and 44px controls remain part of the shared UI system.

## Launch checklist
1. Test admin brand uploads and save/sync on a real configured installation.
2. Verify Logo Website, Favicon and Logo OG Image independently.
3. Open one province, one city/regency and one article on desktop/tablet/mobile.
4. Verify no duplicate logo, no oversized overlay, no image overflow.
5. Validate canonical, robots, sitemap, Open Graph and JSON-LD.
6. Check Google Search Console URL inspection and image crawlability after deployment.
7. Verify WhatsApp CTA, analytics and security headers.
8. Run broken-link and PHP lint checks before release.
