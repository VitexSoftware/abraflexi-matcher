<?php

/**
 * php-abraflexi-matcher
 * 
 * @copyright (c) 2022, Vítězslav Dvořák
 */
use Ease\Functions;
use Ease\Locale;
use Ease\Shared;

define('APP_NAME', 'ParujPrijatouBanku');
require_once '../vendor/autoload.php';
$shared = Shared::singleton();
if (file_exists('../.env')) {
    $shared->loadConfig('../.env', true);
}
new Locale(Functions::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

foreach (['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'EASE_LOGGER'] as $cfgKey) {
    if (empty(\Ease\Functions::cfg($cfgKey))) {
        echo 'Requied configuration ' . $cfgKey . ' is not set.';
        exit(1);
    }
}

if ($argc > 1) {
    $docId = $argv[1];
} else {
    $docId = \Ease\Functions::cfg('DOCUMENTID');
}

$invoiceSteamer = new \AbraFlexi\Matcher\ParovacFaktur($shared->configuration);

if ($docId) {
    $invoiceSteamer->banker->loadFromAbraFlexi($docId);
    if (Functions::cfg('APP_DEBUG')) {
        $invoiceSteamer->banker->logBanner(Shared::appName());
        $invoiceSteamer->addStatusMessage(_('Incoming bank matching begin'), 'debug');
    }

    $invoiceSteamer->addStatusMessage(sprintf(_('Payment %s matching'), $docId), $invoiceSteamer->matchingByBank() ? 'success' : 'warning');

    if (Functions::cfg('APP_DEBUG')) {
        $invoiceSteamer->addStatusMessage(_('Incomin bank matching done'), 'debug');
    }
} else {
    $invoiceSteamer->addStatusMessage(_('No DOCUMENTID provided. aborting'), 'error');
}
