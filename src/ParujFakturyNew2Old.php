<?php

/**
 * Invoice Matching
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright (c) 2018-2023, Vítězslav Dvořák
 */

use AbraFlexi\Matcher\OutcomingInvoice;
use Ease\Functions;
use Ease\Shared;

define('APP_NAME', 'ParujFakturyNewToOld');

require_once '../vendor/autoload.php';

\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MATCHER_DAYS_BACK'], '../.env');

new \Ease\Locale(Functions::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$odden = 0;
$date1 = new DateTime();
$date2 = new DateTime();
$date2->modify('-' . Functions::cfg('MATCHER_DAYS_BACK') . ' days');

$doden = $date2->diff($date1)->format("%a");

$invoiceSteamer = new OutcomingInvoice();
if (Functions::cfg('APP_DEBUG')) {
    $invoiceSteamer->banker->logBanner(Shared::appName());
}
if (Functions::cfg('MATCHER_PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'));
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Matching program started'), 'debug');
while ($odden < $doden) {
    $invoiceSteamer->setStartDay($odden++);
    $invoiceSteamer->outInvoicesMatchingByBank();
}
$invoiceSteamer->addStatusMessage(_('Matching finished'));
