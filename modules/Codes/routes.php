<?php

use Core\Router;
use Modules\Codes\Controllers\CodesController;
use Modules\Codes\Controllers\PackageController;

return static function (Router $router): void {
    $prefixes = ['', '/public/index.php'];

    foreach ($prefixes as $prefix) {
        $router->get($prefix . '/codes', static function (\PDO $pdo): void {
            (new CodesController($pdo))->index();
        });

        $router->get($prefix . '/codes/create', static function (\PDO $pdo): void {
            (new CodesController($pdo))->create();
        });

        $router->post($prefix . '/codes', static function (\PDO $pdo): void {
            (new CodesController($pdo))->store();
        });

        $router->get($prefix . '/codes/{id}/edit', static function (\PDO $pdo, string $id): void {
            (new CodesController($pdo))->edit((int) $id);
        });

        $router->post($prefix . '/codes/{id}', static function (\PDO $pdo, string $id): void {
            (new CodesController($pdo))->update((int) $id);
        });

        $router->post($prefix . '/codes/{id}/delete', static function (\PDO $pdo, string $id): void {
            (new CodesController($pdo))->destroy((int) $id);
        });

        $router->post($prefix . '/codes/{id}/toggle', static function (\PDO $pdo, string $id): void {
            (new CodesController($pdo))->toggleActive((int) $id);
        });

        $router->post($prefix . '/codes/{id}/relate', static function (\PDO $pdo, string $id): void {
            (new CodesController($pdo))->addRelation((int) $id);
        });

        $router->post($prefix . '/codes/{id}/relate/del', static function (\PDO $pdo, string $id): void {
            (new CodesController($pdo))->removeRelation((int) $id);
        });

        $router->get($prefix . '/codes/datatable', static function (\PDO $pdo): void {
            (new CodesController($pdo))->datatable();
        });

        $router->get($prefix . '/codes/packages', static function (\PDO $pdo): void {
            (new PackageController($pdo))->index();
        });

        $router->get($prefix . '/codes/api/packages', static function (\PDO $pdo): void {
            (new PackageController($pdo))->list();
        });

        $router->get($prefix . '/codes/api/packages/{id}', static function (\PDO $pdo, string $id): void {
            (new PackageController($pdo))->show((int) $id);
        });

        $router->post($prefix . '/codes/api/packages', static function (\PDO $pdo): void {
            (new PackageController($pdo))->store();
        });

        $router->post($prefix . '/codes/api/packages/{id}', static function (\PDO $pdo, string $id): void {
            (new PackageController($pdo))->update((int) $id);
        });

        $router->post($prefix . '/codes/api/packages/{id}/delete', static function (\PDO $pdo, string $id): void {
            (new PackageController($pdo))->delete((int) $id);
        });

        $router->get($prefix . '/codes/api/search', static function (\PDO $pdo): void {
            (new PackageController($pdo))->searchCodes();
        });
    }
};
