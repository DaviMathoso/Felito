<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

$expired = (new App\Services\OrderService())->expireDueReservations();
(new App\Services\StockService())->syncAlerts();
fwrite(STDOUT, "Reservas expiradas: $expired\nAlertas de estoque sincronizados.\n");
