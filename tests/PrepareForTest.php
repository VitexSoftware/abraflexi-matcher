<?php
/**
 * php-flexibee-matecher - Prepare Testing Data
 * 
 * @copyright (c) 2018-2019, Vítězslav Dvořák
 */
define('EASE_LOGGER', 'syslog|console');
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
    $shared = new Ease\Shared();
    $shared->loadConfig('../client.json', true);
    $shared->loadConfig('../matcher.json', true);
} else {
    require_once './vendor/autoload.php';
    $shared = new Ease\Shared();
    $shared->loadConfig('./client.json', true);
    $shared->loadConfig('./matcher.json', true);
}

function unc($code)
{
    return \FlexiPeeHP\FlexiBeeRO::uncode($code);
}

/**
 * Prepare Testing Invoice
 * 
 * @param array $initialData
 * 
 * @return \FlexiPeeHP\FakturaVydana
 */
function makeInvoice($initialData = [], $dayBack = 1, $evidence = 'vydana')
{
    return \Test\FlexiPeeHP\FakturaVydanaTest::makeTestInvoice($initialData, $dayBack, $evidence);
}

/**
 * Prepare testing payment
 * 
 * @param array $initialData
 * 
 * @return \FlexiPeeHP\Banka
 */
function makePayment($initialData = [], $dayBack = 1)
{
    return \Test\FlexiPeeHP\BankaTest::makeTestPayment($initialData, $dayBack);
}
$labeler = new FlexiPeeHP\Stitek();
$labeler->createNew('PREPLATEK', ['banka']);
$labeler->createNew('CHYBIFAKTURA', ['banka']);
$labeler->createNew('NEIDENTIFIKOVANO', ['banka']);

$banker = new FlexiPeeHP\Banka(null, ['evidence' => 'bankovni-ucet']);
if (!$banker->recordExists(['kod' => 'HLAVNI'])) {
    $banker->insertToFlexiBee(['kod' => 'HLAVNI', 'nazev' => 'Main Account']);
}

$adresser     = new \FlexiPeeHP\Adresar();
$allAddresses = $adresser->getColumnsFromFlexibee(['kod'],
    ['typVztahuK' => 'typVztahu.odberDodav']);

$customer = $allAddresses[array_rand($allAddresses)];


do {
    $firmaA = $allAddresses[array_rand($allAddresses)];
    $bucA   = $adresser->getBankAccountNumber(\FlexiPeeHP\FlexiBeeRO::code($firmaA['kod']));
} while (empty($bucA));
if (!\Ease\Sand::isAssoc($bucA)) {
    $bucA = current($bucA);
}


$adresser->addStatusMessage('Company A: '.$firmaA['kod']);
do {
    $firmaB = $allAddresses[array_rand($allAddresses)];
    $bucB   = $adresser->getBankAccountNumber(\FlexiPeeHP\FlexiBeeRO::code($firmaB['kod']));
} while (empty($bucB));

if (!\Ease\Sand::isAssoc($bucB)) {
    $bucB = current($bucB);
}

$adresser->addStatusMessage('Company B: '.$firmaB['kod']);

$firma = \FlexiPeeHP\FlexiBeeRO::code($customer['kod']);
$buc   = $customer['id'].$customer['id'].$customer['id'];
$bank  = 'code:0300';

for ($i = 0; $i <= constant('DAYS_BACK') + 3; $i++) {
    $banker->addStatusMessage($i.'/'.(constant('DAYS_BACK') + 3));
    $varSym  = \Ease\Sand::randomNumber(1111, 9999);
    $specSym = \Ease\Sand::randomNumber(111, 999);
    $price   = \Ease\Sand::randomNumber(11, 99);

    $invoiceSs = makeInvoice(['varSym' => $varSym, 'specSym' => $specSym, 'sumZklZaklMen' => $price,
        'mena' => 'code:EUR', 'firma' => $firma], $i);
    $paymentSs = makePayment(['specSym' => $specSym, 'sumZklZaklMen' => $price, 'mena' => 'code:EUR',
        'buc' => $buc, 'smerKod' => $bank], $i);

    $invoiceVs = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => $price, 'firma' => $firma],
        $i);
    $paymentVs = makePayment(['varSym' => $varSym, 'sumZklZakl' => $price, 'buc' => $buc,
        'smerKod' => $bank], $i);

    $dobropis = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => -$price, 'typDokl' => \FlexiPeeHP\FlexiBeeRO::code('ZDD')],
        $i);

    $zaloha = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => $price, 'typDokl' => \FlexiPeeHP\FlexiBeeRO::code('ZÁLOHA')],
        $i);

    $varSym    = \Ease\Sand::randomNumber(1111, 9999);
    $price     = \Ease\Sand::randomNumber(11, 99);
    $prijata   = makeInvoice(['cisDosle' => $varSym, 'varSym' => $varSym, 'sumZklZakl' => $price,
        'datSplat' => FlexiPeeHP\FlexiBeeRW::dateToFlexiDate(new DateTime()),
        'typDokl' => \FlexiPeeHP\FlexiBeeRO::code((rand(0, 1) == 1) ? 'FAKTURA' : 'ZÁLOHA')],
        $i, 'prijata');
    $paymentin = makePayment(['varSym' => $varSym, 'sumOsv' => $price, 'typPohybuK' => 'typPohybu.vydej'],
        $i);


    $varSym = \Ease\Sand::randomNumber(1111, 9999);
    $price  = \Ease\Sand::randomNumber(11, 99);



    $prijataA   = makeInvoice(['cisDosle' => $varSym, 'varSym' => $varSym, 'sumZklZakl' => $price,
        'datSplat' => FlexiPeeHP\FlexiBeeRW::dateToFlexiDate(new DateTime()),
        'firma' => \FlexiPeeHP\FlexiBeeRO::code($firmaA['kod']),
        'buc' => $bucA['buc'], 'smerKod' => $bucA['smerKod'],
        'typDokl' => \FlexiPeeHP\FlexiBeeRO::code('FAKTURA')], $i, 'prijata');
    $prijataB   = makeInvoice(['cisDosle' => $varSym, 'varSym' => $varSym, 'sumZklZakl' => $price,
        'datSplat' => FlexiPeeHP\FlexiBeeRW::dateToFlexiDate(new DateTime()),
        'firma' => \FlexiPeeHP\FlexiBeeRO::code($firmaB['kod']),
        'buc' => $bucB['buc'], 'smerKod' => $bucB['smerKod'],
        'typDokl' => \FlexiPeeHP\FlexiBeeRO::code('FAKTURA')], $i, 'prijata');
    $paymentin1 = makePayment(['varSym' => $varSym, 'sumOsv' => $price, 'typPohybuK' => 'typPohybu.vydej',
        'buc' => $bucA['buc'], 'smerKod' => $bucA['smerKod']], $i);
    $paymentin2 = makePayment(['varSym' => $varSym, 'sumOsv' => $price, 'typPohybuK' => 'typPohybu.vydej',
        'buc' => $bucB['buc'], 'smerKod' => $bucB['smerKod']], $i);
}
