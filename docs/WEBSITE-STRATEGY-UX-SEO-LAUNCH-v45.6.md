# WEBSITE STRATEGY, UX, UI, SEO & LAUNCH — v45.6

## A. Temuan audit v45.5
- Dua featured image sebelumnya memakai struktur CSS berbeda: regional `brand-image-frame` + `brand-image-logo-sync`, artikel `article-featured-frame` + `article-brand-logo`.
- Posisi logo artikel berada di pojok kanan atas, sedangkan regional berada di area kanan-tengah; ini membuat pengalaman visual tidak konsisten.
- Rule global `figure img{width:100%}` pernah menjadi sumber risiko logo membesar ketika logo dirender sebagai `<img>`.
- Sinkronisasi brand sekarang menggunakan satu CSS variable `--brand-og-logo`; overlay tidak lagi berupa `<img>` terpisah.
- Cache settings sudah memiliki invalidation berdasarkan mtime/size + bust flag sehingga update brand dapat dibaca ulang sebelum static publishing.

## B. Struktur featured image v45.6
Semua konten wilayah dan artikel memakai struktur identik:

`figure.featured-media > div.brand-image-frame > img + span.brand-image-logo-sync`

Aturan visual:
- frame 16:9 responsif;
- artwork/gambar konten tetap menjadi gambar utama;
- logo persegi kecil hanya satu kali di dalam frame;
- logo memakai CSS background dari `--brand-og-logo`;
- posisi dan ukuran logo sama pada wilayah dan artikel;
- caption tetap berada di luar gambar;
- tidak ada OG Image sosial besar yang disisipkan di atas judul.

## C. Branding source of truth
- Logo Website: `logo_url` → header/topbar.
- Favicon: `favicon_url` → favicon + Apple touch icon.
- Logo OG Image: `og_logo_url` → hanya overlay featured image wilayah + artikel.
- Social OG Image: `og_image_url` → metadata sosial generik; tidak ditampilkan sebagai featured image.
- `assets/css/minimal.css` memiliki tepat satu marker `BRAND_OG_LOGO_START/END`.
- URL logo overlay ditulis relatif bila aset berada pada host yang sama, mengurangi byte dan menghindari ketergantungan domain absolut.

## 1. Riset & validasi ide
Produk digital diposisikan sebagai pusat informasi dan persiapan konsultasi prostetik/ortotik, bukan sekadar katalog jualan. Pengguna membutuhkan jawaban atas: layanan apa yang relevan, apa yang perlu disiapkan, bagaimana prosesnya, di mana layanan tersedia, dan bagaimana menghubungi penyedia layanan.

WHO menekankan layanan prostetik/ortotik yang people-centred, dengan pengguna dan keluarga terlibat dalam keputusan, serta layanan yang memperhatikan produk, personel, dan penyediaan layanan. WHO juga menempatkan prostesis sebagai assistive technology yang mendukung fungsi dan kemandirian.

Konsep: **need-first service directory**.

## 2. Struktur website
Beranda → Layanan → Proses → Panduan → Artikel → Wilayah → Cari → Legal.

Layanan: kaki palsu, tangan palsu, jari palsu, ortotik.

Tujuan tiap area:
- Beranda: orientasi + CTA.
- Layanan: memahami pilihan layanan tanpa memaksa memilih produk teknis.
- Proses: mengurangi ketidakpastian.
- Panduan: persiapan konsultasi.
- Artikel: edukasi dan organic search.
- Wilayah: discovery berbasis lokasi.
- Cari: jalur cepat menuju kebutuhan spesifik.
- Legal: kepercayaan dan kepatuhan.

## 3. User flow
Masuk → pahami kebutuhan → pilih layanan → baca proses/panduan → cek wilayah → siapkan pertanyaan → konsultasi WhatsApp.

Hambatan: istilah teknis, ketidakjelasan biaya, lokasi, kesiapan dokumen/pertanyaan, dan kekhawatiran memilih layanan yang tidak sesuai.

Solusi: bahasa sederhana, FAQ, checklist, breadcrumb, pencarian, CTA kontekstual, dan penjelasan bahwa website bukan pengganti pemeriksaan tenaga kesehatan.

## 4. Wireframe
### Beranda
Header/search → hero → layanan → proses → wilayah → FAQ → CTA → footer.

### Wilayah
Breadcrumb → H1 → ringkasan wilayah → data wilayah → **featured image standar + logo kecil** → jawaban → alur → persiapan → wilayah terkait → FAQ → CTA.

### Artikel
Breadcrumb → eyebrow/tanggal → H1 → excerpt → jawaban singkat → **featured image standar + logo kecil** → isi → related → sumber → CTA.

### Cari
Search input → hasil → empty state → CTA konsultasi.

## 5. Copywriting
Headline: **Mulai dari kebutuhan Anda, bukan dari pilihan alat.**

Subheadline: Informasi kaki palsu, tangan palsu, jari palsu, dan alat bantu ortotik untuk membantu persiapan konsultasi sesuai kondisi, aktivitas, kenyamanan, dan tujuan penggunaan.

CTA utama: **Konsultasi WhatsApp**.
CTA sekunder: **Buka panduan**.

Microcopy: “Belum tahu harus mulai dari mana? Mulai dari panduan.”

Disclaimer: “Informasi ini membantu persiapan konsultasi dan bukan pengganti pemeriksaan tenaga kesehatan.”

## 6. Visual design
Primary #0057B8; text #111; surface #FFF; soft surface #F3F6FA. Radius 10–16px. Touch target minimum 44px. Satu font system stack. Tidak menggunakan framework UI berat atau JavaScript untuk layout.

Featured media:
- satu frame 16:9;
- object-fit cover;
- logo 44–84px desktop dan 42–68px mobile;
- logo diposisikan konsisten pada wilayah dan artikel;
- tidak ada efek berat, blur besar, atau shadow berlebihan.

## 7. Development
Public pages tetap static HTML. PHP dipakai untuk admin, upload, settings, publishing, dan generator regional.

Reusable brand pipeline:
`Admin upload → validate → save settings → invalidate settings cache → publish_site_identity → update CSS variable + static HTML metadata → selesai`.

Tidak menanam URL logo ke ratusan halaman. Overlay mengambil satu CSS variable.

## 8. SEO & Google Image
- Gambar konten mempunyai `alt` yang menggambarkan isi, bukan alt logo.
- Width/height image dipertahankan untuk mengurangi layout shift.
- Featured image artikel menggunakan `fetchpriority=high`, `loading=eager`, dan `decoding=async`.
- Regional image menggunakan WebP.
- `og:image` artikel menunjuk featured image artikel, bukan logo.
- `og:image` regional menunjuk artwork regional.
- JSON-LD Article menyertakan image konten.
- JSON-LD regional WebPage sekarang menyertakan image konten regional.
- Logo overlay tidak dipakai sebagai `image` konten schema.
- Sitemap tetap menjadi jalur discovery.

## 9. Audit hasil v45.6
- 552 halaman wilayah menggunakan featured frame unified.
- 6 artikel published menggunakan featured frame unified.
- 0 legacy `article-featured-frame`.
- 0 legacy `article-brand-logo`.
- 0 `<img data-brand-og-logo>`.
- 0 duplicate canonical pada HTML yang diaudit.
- 0 duplicate `og:image` pada HTML yang diaudit.
- 0 content image tanpa alt pada wilayah + artikel.
- 1 CSS brand marker pair.
- PHP lint app/site.php dan admin/settings.php: OK.
- Regional generator `--check`: OK, 553 URL.
- Regional generator build: OK, 553 URL.

## 10. Launch checklist
Domain/HTTPS; PHP version; permission; upload validation; favicon; logo; Logo OG Image 1:1; social OG image; canonical; robots; sitemap; JSON-LD; alt text; image dimensions; responsive desktop/tablet/mobile; keyboard/focus; 44px touch target; WhatsApp CTA; search; CSRF/session; SVG sanitization; admin noindex; broken links; Search Console; analytics opsional; cache/CDN; backup; error logging.

## Rujukan
WHO — Assistive Technology: https://www.who.int/news-room/fact-sheets/detail/assistive-technology
WHO — Rehabilitation: https://www.who.int/news-room/fact-sheets/detail/rehabilitation
WHO — Standards for prosthetics and orthotics: https://qualityhealthservices.who.int/quality-toolkit/qt-catalog-item/who-standards-for-prosthetics-and-orthotics
Google Search Central — Article structured data: https://developers.google.com/search/docs/appearance/structured-data/article
Google Search Central — Search appearance / structured data: https://developers.google.com/search/docs/appearance
