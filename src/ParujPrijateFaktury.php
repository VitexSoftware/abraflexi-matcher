<?php
/**
 * php-flexibee-matcher
 * 
 * @copyright (c) 2018, Vítězslav Dvořák
 */
require_once '../vendor/autoload.php';
$shared = new Ease\Shared();
$shared->loadConfig('../client.json');
$shared->loadConfig('../matcher.json');

//new \Ease\Locale($shared->getConfigValue('LOCALIZE'), '../i18n',
//    'flexibee-matcher');

$invoiceSteamer = new \FlexiPeeHP\Bricks\ParovacFaktur($shared->configuration);

if ($shared->getConfigValue('PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'));
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$begin     = new \DateTime();
$daterange = new \DatePeriod($begin->modify('-'.$shared->getConfigValue('DAYS_BACK').' days'),
    new DateInterval('P1D'), new \DateTime());

$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching begin'));

$invoiceSteamer->inInvoicesMatchingByBank($daterange);

$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching done'));
