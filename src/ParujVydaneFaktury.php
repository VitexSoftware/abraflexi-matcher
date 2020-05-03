<?php

/**
 * php-flexibee-matcher
 * 
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */
define('EASE_APPNAME', 'ParujVydaneFaktury');
require_once '../vendor/autoload.php';
$shared = new Ease\Shared();
if (file_exists('../client.json')) {
    $shared->loadConfig('../client.json', true);
}
$shared->loadConfig('../matcher.json', true);

//new \Ease\Locale($shared->getConfigValue('LOCALIZE'), '../i18n','flexibee-matcher');

$invoiceSteamer = new FlexiPeeHP\Matcher\OutcomingInvoice($shared->configuration);
$invoiceSteamer->banker->logBanner(constant('EASE_APPNAME'));

if ($shared->getConfigValue('PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'), 'debug');
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching begin'), 'debug');
$invoiceSteamer->outInvoicesMatchingByBank();
$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching done'), 'debug');
