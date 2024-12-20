<?php

declare(strict_types=1);

/**
 * This file is part of the  AbraFlexi Matcher package.
 *
 * (c) Vítězslav Dvořák <https://vitexsoftware.cz/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

\define('APP_NAME', 'AbraFlexi StahniBanku');

require_once '../vendor/autoload.php';

\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], \array_key_exists(1, $argv) ? $argv[1] : '../.env');
new \Ease\Locale(\Ease\Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$banker = new \AbraFlexi\Banka();

if (\Ease\Shared::cfg('APP_DEBUG')) {
    $banker->logBanner();
}

$banker->addStatusMessage(_('Download online bank statements'), 'debug');

try {
    if (!$banker->stahnoutVypisyOnline()) {
        $banker->addStatusMessage('Bank Offline!', 'error');
    }
} catch (\AbraFlexi\Exception $exc) {
    switch ($banker->lastResponseCode) {
        case 400:
            foreach ($banker->getErrors() as $message) {
                $banker->addStatusMessage(\is_array($message) ? current($message) : $message, 'warning');
            }

            exit(400);

        default:
            foreach ($banker->getErrors() as $message) {
                $banker->addStatusMessage(\is_array($message) ? current($message) : $message, 'error');
            }

            exit($banker->lastResponseCode);
    }
}
