<?php

/**
 * Invoice Matching
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright (c) 2018-2023, Vítězslav Dvořák
 */

use AbraFlexi\Matcher\OutcomingInvoice;
use Ease\Shared;

define('APP_NAME', 'AbraFlexi ParujFakturyNewToOld');
require_once '../vendor/autoload.php';
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], array_key_exists(1, $argv) ? $argv[1] : '../.env');
new \Ease\Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');
$odden = 0;
$date1 = new DateTime();
$date2 = new DateTime();
$daysOfYear = \AbraFlexi\FakturaVydana::overdueDays(new \DateTime(date('Y') . '-01-01'));
$date2->modify('-' . \Ease\Shared::cfg('MATCHER_DAYS_BACK', $daysOfYear) . ' days');
$doden = $date2->diff($date1)->format("%a");
$invoiceSteamer = new OutcomingInvoice();
if (Shared::cfg('APP_DEBUG')) {
    $invoiceSteamer->banker->logBanner();
}
if (Shared::cfg('MATCHER_PULL_BANK') === true) {
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
