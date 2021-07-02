<?php

/**
 * php-abraflexi-matcher
 * 
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */
use Ease\Shared;
use AbraFlexi\Matcher\OutcomingInvoice;

define('APP_NAME', 'ParujVydaneFaktury');
require_once '../vendor/autoload.php';
$shared = Shared::singleton();
if (file_exists('../.env')) {
    $shared->loadConfig('../.env', true);
}
new \Ease\Locale($shared->getConfigValue('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$invoiceSteamer = new OutcomingInvoice($shared->configuration);
$invoiceSteamer->banker->logBanner(constant('APP_NAME'));

if ($shared->getConfigValue('MATCHER_PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'), 'debug');
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching begin'), 'debug');
$invoiceSteamer->outInvoicesMatchingByBank();
$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching done'), 'debug');
