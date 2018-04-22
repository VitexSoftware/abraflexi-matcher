<?php
/**
 * Párování fakrur
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright  2015-2018 Spoje.Net
 */
define('EASE_APPNAME', 'ParujFakturyNewToOld');

require_once '../vendor/autoload.php';

$shared = new Ease\Shared();
$shared->loadConfig('../client.json');
$shared->loadConfig('../matcher.json');

$odden = 0;
$date1 = new \DateTime();
$date2 = new \DateTime(); //Parujeme do zacatku roku
$date2->modify('-3 month');

$doden = $date2->diff($date1)->format("%a");

$invoiceSteamer = new \FlexiPeeHP\Bricks\ParovacFaktur();

if ($shared->getConfigValue('PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'));
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Zahajuji programové párování'));
while ($odden < $doden) {
    $invoiceSteamer->setStartDay($odden++);
    $invoiceSteamer->invoicesMatchingByBank();
}
$invoiceSteamer->addStatusMessage(_('Párování hotovo'));
