<?php
/**
 * Invoice Matching
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */

use Ease\Shared;
use AbraFlexi\Matcher\OutcomingInvoice;

define('APP_NAME', 'ParujFakturyNewToOld');

require_once '../vendor/autoload.php';

$shared = Shared::singleton();
if (file_exists('../.env')) {
    $shared->loadConfig('../.env', true);
}
new \Ease\Locale($shared->getConfigValue('MATCHER_LOCALIZE'), '../i18n',    'abraflexi-matcher');

$odden = 0;
$date1 = new DateTime();
$date2 = new DateTime();
$date2->modify('-' . $shared->getConfigValue('MATCHER_DAYS_BACK') . ' days');

$doden = $date2->diff($date1)->format("%a");

$invoiceSteamer = new OutcomingInvoice($shared->configuration);
$invoiceSteamer->banker->logBanner(constant('APP_NAME'));

if ($shared->getConfigValue('PULL_BANK') === true) {
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
