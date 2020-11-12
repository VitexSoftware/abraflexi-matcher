<?php
/**
 * php-flexibee-matcher
 * 
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */

use Ease\Shared;
use AbraFlexi\Matcher\IncomingInvoice;

define('EASE_APPNAME', 'ParujPrijateFaktury');
require_once '../vendor/autoload.php';
$shared = new Shared();
if (file_exists('../client.json')) {
    $shared->loadConfig('../client.json', true);
}
if (file_exists('../matcher.json')) {
    $shared->loadConfig('../matcher.json', true);
}
//new \Ease\Locale($shared->getConfigValue('LOCALIZE'), '../i18n','flexibee-matcher');

$invoiceSteamer = new IncomingInvoice($shared->configuration);
$invoiceSteamer->banker->logBanner(constant('EASE_APPNAME'));

if ($shared->getConfigValue('PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'), 'debug');
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$begin = new DateTime();
$daterange = new DatePeriod($begin->modify('-' . $shared->getConfigValue('DAYS_BACK') . ' days'),
        new DateInterval('P1D'), new DateTime());

$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching begin'), 'debug');
$invoiceSteamer->inInvoicesMatchingByBank($daterange);
$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching done'), 'debug');
