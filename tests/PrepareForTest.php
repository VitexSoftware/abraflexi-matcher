<?php
/**
 * php-flexibee-matecher - Prepare Testing Data
 * 
 * @copyright (c) 2018, Vítězslav Dvořák
 */
define('EASE_LOGGER', 'syslog|console');
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
    $shared = new Ease\Shared();
    $shared->loadConfig('../client.json');
    $shared->loadConfig('../matcher.json');
} else {
    require_once './vendor/autoload.php';
    $shared = new Ease\Shared();
    $shared->loadConfig('./client.json');
    $shared->loadConfig('./matcher.json');
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
    $yesterday = new \DateTime();
    $yesterday->modify('-'.$dayBack.' day');
    $testCode  = 'TEST_'.\Ease\Sand::randomString();
    $invoice   = new \FlexiPeeHP\FakturaVydana(null,
        ['evidence' => 'faktura-'.$evidence]);
    $invoice->takeData(array_merge([
        'kod' => $testCode,
        'varSym' => \Ease\Sand::randomNumber(1111, 9999),
        'specSym' => \Ease\Sand::randomNumber(111, 999),
        'bezPolozek' => true,
        'popis' => 'php-flexibee-matcher Test invoice',
        'datVyst' => \FlexiPeeHP\FlexiBeeRO::dateToFlexiDate($yesterday),
        'typDokl' => \FlexiPeeHP\FlexiBeeRO::code('FAKTURA')
            ], $initialData));
    if ($invoice->sync()) {
        $invoice->addStatusMessage($invoice->getApiURL(), 'debug');
    }

    return $invoice;
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
    $yesterday = new \DateTime();
    $yesterday->modify('-'.$dayBack.' day');

    $testCode = 'TEST_'.\Ease\Sand::randomString();

    $payment = new \FlexiPeeHP\Banka($initialData);

    $payment->takeData(array_merge([
        'kod' => $testCode,
        'banka' => 'code:HLAVNI',
        'typPohybuK' => 'typPohybu.prijem',
        'popis' => 'php-flexibee-matcher Test bank record',
        'varSym' => \Ease\Sand::randomNumber(1111, 9999),
        'specSym' => \Ease\Sand::randomNumber(111, 999),
        'bezPolozek' => true,
        'datVyst' => \FlexiPeeHP\FlexiBeeRO::dateToFlexiDate($yesterday),
        'typDokl' => \FlexiPeeHP\FlexiBeeRO::code('STANDARD')
            ], $initialData));
    if ($payment->sync()) {
        $payment->addStatusMessage($payment->getApiURL(), 'debug');
    }
    return $payment;
}
$labeler = new FlexiPeeHP\Stitek();
$labeler->createNew('PREPLATEK', ['banka']);
$labeler->createNew('CHYBIFAKTURA', ['banka']);
$labeler->createNew('NEIDENTIFIKOVANO', ['banka']);

$banker = new FlexiPeeHP\Banka(null, ['evidence' => 'bankovni-ucet']);
$banker->insertToFlexiBee(['kod' => 'HLAVNI', 'nazev' => 'Main Account']);


for ($i = 0; $i <= constant('DAYS_BACK') + 3; $i++) {
    $banker->addStatusMessage($i.'/'.constant('DAYS_BACK'));
    $varSym  = \Ease\Sand::randomNumber(1111, 9999);
    $specSym = \Ease\Sand::randomNumber(111, 999);
    $price   = \Ease\Sand::randomNumber(11, 99);

    $invoiceSs = makeInvoice(['varSym' => $varSym, 'specSym' => $specSym, 'sumZklZakl' => $price],
        $i);
    $paymentSs = makePayment(['specSym' => $specSym, 'sumOsv' => $price], $i);
    $invoiceVs = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => $price], $i);
    $paymentVs = makePayment(['varSym' => $varSym, 'sumOsv' => $price], $i);

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
}
