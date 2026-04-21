<?php
// Debian autoloader for abraflexi-matcher
// Load dependency autoloaders
require_once '/usr/share/php/AbraFlexiBricks/autoload.php';

// Ensure local package classes under AbraFlexi\\Matcher are autoloaded.
// This covers both repository layout and installed package layout.
spl_autoload_register(function (string $class) {
    // Only handle classes in the AbraFlexi\Matcher namespace
    if (strpos($class, 'AbraFlexi\\Matcher\\') === 0) {
        // Convert namespace to file path
        $relativeClass = substr($class, strlen('AbraFlexi\\Matcher\\'));
        $filePath = '/usr/lib/php/AbraFlexi/Matcher/' . str_replace('\\', '/', $relativeClass) . '.php';

        // Check if the file exists and include it
        if (file_exists($filePath)) {
            require_once $filePath;
        }
    }
});

require_once '/usr/share/php/Composer/InstalledVersions.php';

(function (): void {
    $versions = [];
    foreach (\Composer\InstalledVersions::getAllRawData() as $d) {
        $versions = array_merge($versions, $d['versions'] ?? []);
    }
    $name    = 'unknown';
    $version = defined('APP_VERSION') ? APP_VERSION : '0.0.0';
    $versions[$name] = ['pretty_version' => $version, 'version' => $version,
        'reference' => null, 'type' => 'library', 'install_path' => __DIR__,
        'aliases' => [], 'dev_requirement' => false];
    \Composer\InstalledVersions::reload([
        'root' => ['name' => $name, 'pretty_version' => $version, 'version' => $version,
            'reference' => null, 'type' => 'project', 'install_path' => __DIR__,
            'aliases' => [], 'dev' => false],
        'versions' => $versions,
    ]);
})();
