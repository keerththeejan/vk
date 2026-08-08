<?php
declare(strict_types=1);
$extraScripts = $extraScripts ?? '';
$vkAdminScriptVersion = vk_asset_mtime_version('assets/js/app.js');
$vkCurrencyScriptVersion = vk_asset_mtime_version('assets/js/vk-currency.js');
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous" defer></script>
<script src="<?= e(base_url('assets/js/vk-currency.js')) ?>?v=<?= e($vkCurrencyScriptVersion) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($vkAdminScriptVersion) ?>" defer></script>
<?= $extraScripts ?>
</body>
</html>
