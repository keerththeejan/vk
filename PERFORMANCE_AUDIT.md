# VK Network Performance Audit and Optimization Roadmap

## 1. Summary of changes applied

### Critical rendering path
- Deferred public CSS using `media="print"` + `onload` and `preload` for `style.css` and `public-premium.css`.
- Converted Google Fonts to `preload` + onload to avoid render-blocking font CSS.
- Added inline critical CSS for the hero, navbar, and key above-the-fold elements.
- Disabled heavy hero decor on mobile by hiding `.vk-hero-shine`, `.vk-hero-grain`, and `.vk-hero-particles` for devices below 992px.

### JavaScript
- Removed static AOS library load from the footer.
- Updated `assets/js/public-site.js` to lazy-load AOS assets only when `[data-aos]` is present.
- Deferred all public scripts and kept bootstrap bundle deferred.
- Reworked pointermove interactions to batch updates with `requestAnimationFrame` and eliminate layout thrashing.
- Moved heavy interactions, carousel setup, and counters to idle/deferred execution.
- Added intersection-observer-based counter animation.

### Server optimizations
- Extended `.htaccess` with Brotli and GZIP output filters.
- Added long-term immutable caching for static assets and short caching for HTML/PHP.
- Added security headers: `X-Content-Type-Options`, `X-Frame-Options`, and `Referrer-Policy`.

## 2. Root causes identified

- Global AOS CSS and JS loaded on every public page, adding parse and blocking time.
- Two large render-blocking CSS files loaded normally before page paint.
- Google Fonts CSS loaded as a blocking stylesheet.
- `pointermove` handlers triggered layout reads and writes on every frame.
- Bootstrap bundle and public JS execution were scheduled without explicit deferral even though they are non-critical.
- No Brotli/GZIP optimization and broad static caching configuration in `.htaccess`.

## 3. Files updated

- `includes/public_header.php`
- `includes/public_footer.php`
- `assets/js/public-site.js`
- `.htaccess`
- `PERFORMANCE_AUDIT.md`

## 4. Expected impact

- Mobile FCP improvements from inline critical CSS and deferred CSS.
- Reduced TBT through deferred heavy script initialization and requestAnimationFrame batching.
- Lower LCP by reducing initial style and script blocking.
- More consistent 60fps interactions with pointermove batching.
- Better cache efficiency for repeat visits via HTTP caching rules.

## 5. Additional enterprise-grade recommendations

### Frontend architecture
- Migrate to a build pipeline that bundles and tree-shakes site JS using Vite / esbuild.
- Split public JS into a small "critical shell" and deferred feature modules.
- Convert AOS-based reveal animations to a CSS-only `opacity/transform` intersection observer system.
- Replace Bootstrap JS with a minimal vanilla navigation/collapse controller for the public site.
- Self-host fonts or use a font CDN with `font-display: swap` and `preload`.

### Image optimization
- Convert all public images to WebP/AVIF and serve via responsive `srcset`.
- Add `width` and `height` attributes to all hero and service images.
- Preload the hero LCP image if the hero uses an actual image.
- Use `decoding="async"` and `loading="lazy"` for non-critical imagery.

### Backend optimizations
- Enable PHP OPCache and validate `opcache.validate_timestamps=0` in production.
- Add Redis or memcached caching for frequently-read database queries.
- Move settings and menu fetches into shared cache keys.
- Use `Cache-Control: public, max-age=600, s-maxage=900, stale-while-revalidate=60` for generated HTML.

### Cloudflare production settings
- Enable Brotli and HTTP/3.
- Turn on Polish (lossless or lossy as needed) and Mirage for mobile device optimization.
- Use Edge Cache TTL for static resources and Browser Cache TTL for assets.
- Enable Early Hints to prime resource loading.
- Use Cloudflare APO if on WordPress-like dynamic content; otherwise use Page Rules to cache HTML selectively.
- Activate Smart Routing / Tiered Cache for better edge efficiency.

## 6. Example optimized head structure

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap"></noscript>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="..." crossorigin="anonymous" media="print" onload="this.media='all'">
<link rel="stylesheet" href="assets/css/style.css" media="print" onload="this.media='all'">
<link rel="stylesheet" href="assets/css/public-premium.css" media="print" onload="this.media='all'">
<noscript>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/public-premium.css">
</noscript>
```

## 7. Notes

- This update is an immediate performance uplift; additional gains are available with a frontend build step and further asset compression.
- The current codebase still uses dynamic server-side rendering. The configuration here is optimized for that architecture.

## 8. Next priority improvements

1. Audit `assets/css/public-premium.css` and remove unused selectors.
2. Replace AOS reveal patterns with a lightweight intersection observer utility.
3. Migrate public JS to ES modules and code-split by page.
4. Add image CDN / responsive image delivery.
5. Configure Cloudflare page rules for caching dynamic HTML with A/B testing of cache TTL.
