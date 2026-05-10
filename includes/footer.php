<?php
declare(strict_types=1);
$extraScripts = $extraScripts ?? '';
$vkAdminScriptVersion = is_file(__DIR__ . '/../assets/js/app.js') ? (string) filemtime(__DIR__ . '/../assets/js/app.js') : (string) time();
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= e($vkAdminScriptVersion) ?>"></script>
<?= $extraScripts ?>
</body>
</html>
