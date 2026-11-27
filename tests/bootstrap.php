<?php

declare(strict_types=1);

require \dirname(__DIR__) . '/vendor/autoload.php';

// psr/simple-cache, needed by the CacheBridge tests, comes with the toolchain
// kcode installs (devkit.php, ADR-010). The project's autoloader is
// registered first, so its packages win over any copy in .kcode/.
require \dirname(__DIR__) . '/.kcode/vendor/autoload.php';
