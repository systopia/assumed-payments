<?php
define('CIVICRM_UF', 'UnitTests');

use Composer\Autoload\ClassLoader;

$host = getenv('TEST_DB_HOST');
$user = getenv('TEST_DB_USER');
$pass = getenv('TEST_DB_PASS');
$name = getenv('TEST_DB_NAME');

if (!$host || !$user || !$name) {
  fwrite(STDERR, "Missing TEST_DB_* environment variables\n");
  exit(1);
}

$dsn = sprintf(
  'mysql://%s:%s@%s/%s?new_link=true',
  rawurlencode($user),
  rawurlencode($pass),
  $host,
  $name
);

$GLOBALS['_CV'] = [
  'TEST_DB_DSN' => $dsn,
];

$settings = '/var/www/html/private/civicrm.settings.php';
if (!is_readable($settings)) {
  fwrite(STDERR, "Missing $settings\n");
  exit(1);
}
require $settings;

$loader = new ClassLoader();
$loader->addPsr4('Stubs\\', [__DIR__ . '/Stubs']);
$loader->register();

CRM_Core_Config::singleton()->cleanup(1, false);
\Civi::cache('long')->clear();
\Civi::cache('short')->clear();
\Civi::cache('metadata')->clear();
