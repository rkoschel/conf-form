<?php
declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

$testDir = sys_get_temp_dir() . '/conf-form-test-' . getmypid();
if (!is_dir($testDir)) {
    mkdir($testDir, 0700, true);
}
putenv('CONF_FORM_TEST_DIR=' . $testDir);
putenv('CONF_FORM_CONFIG=' . __DIR__ . '/config.test.php');

register_shutdown_function(static fn () => remove_dir($testDir));

require ROOT_DIR . '/web/app/bootstrap.php';
require __DIR__ . '/Support/DbTestCase.php';
require __DIR__ . '/Support/HttpTestCase.php';

function remove_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}
