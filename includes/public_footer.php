<?php
$footerCompany = vk_app_setting('company_name', 'VK Network');
$footerTagline = vk_app_setting('company_tagline', 'Multi-Service Solutions');
$footerText = vk_app_setting('footer_text', 'Premium local service operations with transparent booking, tracking, and field support for homes and businesses.');
$footerBottom = vk_app_setting('footer_bottom_text', 'Made with care in Sri Lanka');
$footerAddress = vk_app_setting('company_address', '26/3 Thiruvaiyaru, Kilinochchi, Sri Lanka');
$footerPhone = vk_app_setting('contact_phone', '077 887 0135');
$footerEmail = vk_app_setting('support_email', '');
$socials = [
    'facebook' => vk_app_setting('facebook_url', ''),
    'instagram' => vk_app_setting('instagram_url', ''),
    'linkedin' => vk_app_setting('linkedin_url', ''),
    'youtube' => vk_app_setting('youtube_url', ''),
    'twitter' => vk_app_setting('twitter_url', ''),
];
?>
</main>
<footer class="vk-public-footer py-5 mt-auto">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <a class="vk-footer-logo d-inline-flex align-items-center gap-3 mb-3" href="<?= e(BASE_URL) ?>/index.php">
                    <span class="vk-footer-mark">VK</span>
                    <div>
                        <div class="vk-footer-brand"><?= e((string) $footerCompany) ?></div>
                        <div class="small text-muted"><?= e((string) $footerTagline) ?></div>
                    </div>
                </a>
                <p class="vk-footer-copy"><?= e((string) $footerText) ?></p>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <?php foreach ($socials as $name => $url): if (trim((string) $url) === '') continue; ?>
                        <a class="vk-social-btn" href="<?= e((string) $url) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(ucfirst($name)) ?>"><i data-lucide="<?= e($name) ?>"></i></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <h3 class="vk-footer-heading">Quick Links</h3>
                <ul class="vk-footer-links list-unstyled mb-0">
                    <li><a href="<?= e(BASE_URL) ?>/index.php">Home</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/book.php">Book Service</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/track.php">Track Status</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/portfolio.php">Our Work</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <h3 class="vk-footer-heading">Services</h3>
                <ul class="vk-footer-links list-unstyled mb-0">
                    <li><a href="<?= e(BASE_URL) ?>/book.php?type=computer">Computer Repair</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/book.php?type=cctv">CCTV Installation</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/book.php?type=electrical">Electrical Service</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/book.php?type=vehicle">Vehicle Support</a></li>
                </ul>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <h3 class="vk-footer-heading">Contact</h3>
                <p class="small mb-3"><?= nl2br(e((string) $footerAddress)) ?></p>
                <?php if ($footerPhone): ?><p class="small mb-3">Phone: <a class="link-footer" href="tel:<?= e(preg_replace('/\D+/', '', (string) $footerPhone)) ?>"><?= e((string) $footerPhone) ?></a></p><?php endif; ?>
                <?php if ($footerEmail): ?><p class="small mb-3">Email: <a class="link-footer" href="mailto:<?= e((string) $footerEmail) ?>"><?= e((string) $footerEmail) ?></a></p><?php endif; ?>
                <form class="vk-newsletter-form mb-3" action="<?= e(BASE_URL) ?>/book.php" method="get">
                    <label class="visually-hidden" for="vkNewsletterEmail">Email address</label>
                    <div class="input-group">
                        <input id="vkNewsletterEmail" class="form-control" type="email" name="email" placeholder="Service updates by email" autocomplete="email">
                        <button class="btn" type="submit" aria-label="Subscribe">
                            <i data-lucide="send"></i>
                        </button>
                    </div>
                </form>
                <div class="vk-footer-apps d-flex flex-wrap gap-2">
                    <a class="vk-app-badge" href="#" aria-label="Download on the App Store"><i data-lucide="apple"></i> App Store</a>
                    <a class="vk-app-badge" href="#" aria-label="Get it on Google Play"><i data-lucide="smartphone"></i> Google Play</a>
                </div>
            </div>
        </div>
        <div class="vk-footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 pt-4 mt-4 border-top border-secondary border-opacity-10">
            <p class="small mb-0 text-muted">&copy; <?= (int) date('Y') ?> <?= e((string) $footerCompany) ?>. All rights reserved.</p>
            <p class="small mb-0 text-muted"><?= e((string) $footerBottom) ?></p>
        </div>
    </div>
</footer>
<?php
$waDigits = vk_app_setting('whatsapp_number', defined('VK_PUBLIC_WHATSAPP_NUMBER') ? (string) VK_PUBLIC_WHATSAPP_NUMBER : '94778870135');
$waMsg = rawurlencode((string) vk_app_setting('whatsapp_default_message', 'Hello, I need service from VK Network.'));
$waHref = 'https://wa.me/' . preg_replace('/\D+/', '', $waDigits) . '?text=' . $waMsg;
$vkPublicScriptVersion = is_file(__DIR__ . '/../assets/js/public-site.js') ? (string) filemtime(__DIR__ . '/../assets/js/public-site.js') : (string) time();
if (function_exists('vk_json_ld_local_business')) {
    echo "\n" . vk_json_ld_local_business() . "\n";
}
?>
<a href="<?= e($waHref) ?>" class="vk-float-wa" target="_blank" rel="noopener noreferrer" title="WhatsApp" aria-label="Contact us on WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
</a>
<a href="<?= e($waHref) ?>" class="vk-support-widget" target="_blank" rel="noopener noreferrer" aria-label="Open live support">
    <span><i data-lucide="message-circle"></i></span>
    <strong>Live support</strong>
    <small>Online now</small>
</a>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js" crossorigin="anonymous" defer></script>
<script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js" crossorigin="anonymous" defer></script>
<script src="<?= e(base_url('assets/js/public-site.js')) ?>?v=<?= e($vkPublicScriptVersion) ?>" defer></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
