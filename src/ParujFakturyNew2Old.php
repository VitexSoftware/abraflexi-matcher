<?php
/**
 * Párování fakrur
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright (c) 2018, Vítězslav Dvořák
 */
define('EASE_APPNAME', 'ParujFakturyNewToOld');

require_once '../vendor/autoload.php';

$shared = new Ease\Shared();
$shared->loadConfig('../client.json', true);
$shared->loadConfig('../matcher.json', true);
//new \Ease\Locale($shared->getConfigValue('LOCALIZE'), '../i18n',
//    'flexibee-matcher');

$odden = 0;
$date1 = new \DateTime();
$date2 = new \DateTime();
$date2->modify('-'.$shared->getConfigValue('DAYS_BACK').' days');

$doden = $date2->diff($date1)->format("%a");

$invoiceSteamer = new FlexiPeeHP\Matcher\OutcomingInvoice($shared->configuration);
$invoiceSteamer->banker->logBanner(constant('EASE_APPNAME') );

if ($shared->getConfigValue('PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'));
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Matching program started'));
while ($odden < $doden) {
    $invoiceSteamer->setStartDay($odden++);
    $invoiceSteamer->outInvoicesMatchingByBank();
}
$invoiceSteamer->addStatusMessage(_('Matching finished'));
