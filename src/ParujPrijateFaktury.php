<?php

/**
 * php-abraflexi-matcher
 *
 * @copyright (c) 2018-2023, Vítězslav Dvořák
 */

use AbraFlexi\Matcher\IncomingInvoice;
use Ease\Shared;

define('APP_NAME', 'AbraFlexi ParujPrijateFaktury');
require_once '../vendor/autoload.php';
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], array_key_exists(1, $argv) ? $argv[1] : '../.env');
new \Ease\Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');
$invoiceSteamer = new IncomingInvoice();
if (Shared::cfg('APP_DEBUG')) {
    $invoiceSteamer->banker->logBanner();
}

if (Shared::cfg('MATCHER_PULL_BANK', false)) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'), 'debug');
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$begin = new DateTime();
$daterange = new DatePeriod(
    $begin->modify('-' . Shared::cfg('MATCHER_DAYS_BACK', 365) . ' days'),
    new DateInterval('P1D'),
    new DateTime()
);
$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching begin'), 'debug');

//TODO Variabilni symbol je nespolehlivy, parovat nejprve podle bankovniho uctu
$invoiceSteamer->inInvoicesMatchingByBank($daterange);
$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching done'), 'debug');
