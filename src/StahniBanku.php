<?php

use Ease\Shared;

/**
 * abraflexi-pull-bank
 *
 * @copyright (c) 2018-2023, Vítězslav Dvořák
 */

define('APP_NAME', 'AbraFlexi StahniBanku');
require_once '../vendor/autoload.php';

\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], array_key_exists(1, $argv) ? $argv[1] : '../.env');
new \Ease\Locale(\Ease\Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$banker = new \AbraFlexi\Banka();
if (\Ease\Shared::cfg('APP_DEBUG')) {
    $banker->logBanner();
}
$banker->addStatusMessage(_('Download online bank statements'), 'debug');
if (!$banker->stahnoutVypisyOnline()) {
    $banker->addStatusMessage('Bank Offline!', 'error');
}
