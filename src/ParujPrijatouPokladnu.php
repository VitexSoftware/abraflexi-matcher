<?php

/**
 * php-abraflexi-matcher
 * 
 * @copyright (c) 2022, Vítězslav Dvořák
 */
use AbraFlexi\Matcher\OutcomingInvoice;
use Ease\Functions;
use Ease\Locale;
use Ease\Shared;

define('APP_NAME', 'ParujPrijatouPokladnu');
require_once '../vendor/autoload.php';
$shared = Shared::singleton();
if (file_exists('../.env')) {
    $shared->loadConfig('../.env', true);
}
new Locale(Functions::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$invoiceSteamer = new OutcomingInvoice($shared->configuration);
if (Functions::cfg('APP_DEBUG')) {
    $invoiceSteamer->banker->logBanner(Shared::appName());
}

if ($shared->getConfigValue('MATCHER_PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'), 'debug');
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching begin'), 'debug');
$invoiceSteamer->outInvoicesMatchingByBank();
$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching done'), 'debug');