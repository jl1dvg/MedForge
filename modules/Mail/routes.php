<?php

use Core\Router;
use Modules\Mail\Controllers\MailboxController;

return static function (Router $router): void {
    $router->get('/mailbox', static function (\PDO $pdo): void {
        (new MailboxController($pdo))->index();
    });

    $router->get('/mail', static function (\PDO $pdo): void {
        (new MailboxController($pdo))->index();
    });

    $router->get('/mailbox/feed', static function (\PDO $pdo): void {
        (new MailboxController($pdo))->feed();
    });

    $router->post('/mailbox/compose', static function (\PDO $pdo): void {
        (new MailboxController($pdo))->compose();
    });
};
