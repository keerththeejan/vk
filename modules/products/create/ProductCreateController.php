<?php
declare(strict_types=1);

require_once __DIR__ . '/ProductCreateService.php';

final class ProductCreateController
{
    public static function run(PDO $pdo): void
    {
        (new ProductCreateService($pdo))->handleRequest();
    }
}
