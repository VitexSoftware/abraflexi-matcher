<?php

/**
 * php-abraflexi-matcher
 * 
 * @copyright (c) 2018-2022, Vítězslav Dvořák
 */
use AbraFlexi\Matcher\IncomingInvoice;
use Ease\Functions;
use Ease\Shared;

define('APP_NAME', 'ParujPrijateFaktury');
require_once '../vendor/autoload.php';
$shared = Shared::singleton();
if (file_exists('../.env')) {
    $shared->loadConfig('../.env', true);
}
new Locale(Functions::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$invoiceSteamer = new IncomingInvoice($shared->configuration);
if (Functions::cfg('APP_DEBUG')) {
    $invoiceSteamer->banker->logBanner(Shared::appName());
}

if (Functions::cfg('PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'), 'debug');
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$begin = new DateTime();
$daterange = new DatePeriod($begin->modify('-' . Functions::cfg('MATCHER_DAYS_BACK') . ' days'),
        new DateInterval('P1D'), new DateTime());

$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching begin'), 'debug');
$invoiceSteamer->inInvoicesMatchingByBank($daterange);
$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching done'), 'debug');
