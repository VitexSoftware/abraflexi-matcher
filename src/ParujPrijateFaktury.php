<?php
/**
 * php-abraflexi-matcher
 * 
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */

use Ease\Shared;
use AbraFlexi\Matcher\IncomingInvoice;

define('APP_NAME', 'ParujPrijateFaktury');
require_once '../vendor/autoload.php';
$shared = Shared::singleton();
if (file_exists('../.env')) {
    $shared->loadConfig('../.env', true);
}
new \Ease\Locale($shared->getConfigValue('MATCHER_LOCALIZE'), '../i18n',    'abraflexi-matcher');

$invoiceSteamer = new IncomingInvoice($shared->configuration);
$invoiceSteamer->banker->logBanner(constant('APP_NAME'));

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
