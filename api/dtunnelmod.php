<?php
/**
 * Alias de compatibilidad: dtunnelmod.php → update.php
 * Necesario para APKs ya distribuidas que apuntan a este endpoint.
 * Las nuevas APKs apuntan directamente a /api/update.php.
 */
require __DIR__ . '/update.php';
