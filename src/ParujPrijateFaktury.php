<?php
/**
 * php-flexibee-matcher
 * 
 * @copyright (c) 2018, Vítězslav Dvořák
 */
define('EASE_APPNAME', 'ParujPrijateFaktury');
require_once '../vendor/autoload.php';
$shared = new Ease\Shared();
$shared->loadConfig('../client.json', true);
$shared->loadConfig('../matcher.json', true);

//new \Ease\Locale($shared->getConfigValue('LOCALIZE'), '../i18n','flexibee-matcher');

$invoiceSteamer = new \FlexiPeeHP\Matcher\IncomingInvoice($shared->configuration);
$invoiceSteamer->banker->logBanner(constant('EASE_APPNAME'));

if ($shared->getConfigValue('PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'),'debug');
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$begin     = new \DateTime();
$daterange = new \DatePeriod($begin->modify('-'.$shared->getConfigValue('DAYS_BACK').' days'),
    new DateInterval('P1D'), new \DateTime());

$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching begin'),'debug');
if(!empty($shared->getConfigValue('DAYS_BACK'))){
    $invoiceSteamer->setStartDay($shared->getConfigValue('DAYS_BACK'));
}
$invoiceSteamer->inInvoicesMatchingByBank($daterange);
$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching done'),'debug');
