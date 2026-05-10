        </main>
        <a class="vk-floating-whatsapp" href="<?= e(BASE_URL) ?>/modules/whatsapp/index.php" aria-label="WhatsApp support automation">
            <i class="bi bi-whatsapp"></i>
            <span>Support</span>
        </a>
        <footer class="border-top py-3 px-3 text-center small text-muted bg-body-secondary bg-opacity-25">
            &copy; <?= date('Y') ?> VK IT Network — Billing System
        </footer>
    </div>
</div>
<?php
$f = flash_get();
if ($f) {
    $type = match ($f['type']) {
        'error' => 'danger',
        'success' => 'success',
        'warning' => 'warning',
        default => 'info',
    };
    echo '<script>document.addEventListener("DOMContentLoaded",function(){showToast(' . json_encode($f['message']) . ',' . json_encode($type) . ');});</script>';
}
require __DIR__ . '/footer.php';
