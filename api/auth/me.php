<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
bootstrap(['GET']);
respond(['user' => public_user(require_auth())]);
