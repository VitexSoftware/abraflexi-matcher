<?php

use Ease\Shared;

/**
 * abraflexi-pull-bank
 * 
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */
define('APP_NAME', 'StahniBanku');
require_once '../vendor/autoload.php';
$shared = Shared::singleton();
if (file_exists('../.env')) {
    $shared->loadConfig('../.env', true);
}
new \Ease\Locale($shared->getConfigValue('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$banker = new \AbraFlexi\Banka();
$banker->logBanner(\Ease\Shared::appName());
$banker->addStatusMessage(_('Download online bank statements'), 'debug');
if (!$banker->stahnoutVypisyOnline()) {
    $banker->addStatusMessage('Bank Offline!', 'error');
}
    