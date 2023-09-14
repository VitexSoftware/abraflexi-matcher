<?php

/**
 * php-abraflexi-matcher - Prepare Testing Data
 * 
 * @copyright (c) 2018-2023, Vítězslav Dvořák
 */
define('EASE_LOGGER', 'syslog|console');
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
    if (file_exists('../tests/test.env')) {
        \Ease\Shared::instanced()->loadConfig('../tests/test.env', true);
    }
    new \Ease\Locale(\Ease\Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');
} else {
    require_once './vendor/autoload.php';
    if (file_exists('./tests/test.env')) {
        \Ease\Shared::instanced()->loadConfig('./tests/test.env', true);
    }
    new \Ease\Locale(\Ease\Shared::cfg('MATCHER_LOCALIZE'), './i18n', 'abraflexi-matcher');
}



new \Ease\Locale(\Ease\Shared::instanced()->getConfigValue('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

function unc($code)
{
    return \AbraFlexi\RO::uncode($code);
}

/**
 * Prepare Testing Invoice
 * 
 * @param array $initialData
 * 
 * @return \AbraFlexi\FakturaVydana
 */
function makeInvoice($initialData = [], $dayBack = 1, $evidence = 'vydana')
{
    $yesterday = new \DateTime();
    $yesterday->modify('-' . $dayBack . ' day');
    $testCode = 'INV_' . \Ease\Functions::randomString();
    $invoice = new \AbraFlexi\FakturaVydana(null,
            ['evidence' => 'faktura-' . $evidence]);
    $invoice->takeData(array_merge([
        'kod' => $testCode,
        'varSym' => \Ease\Functions::randomNumber(1111, 9999),
        'specSym' => \Ease\Functions::randomNumber(111, 999),
        'bezPolozek' => true,
        'popis' => 'php-abraflexi-matcher Test invoice',
        'datVyst' => \AbraFlexi\RO::dateToFlexiDate($yesterday),
        'typDokl' => \AbraFlexi\RO::code('FAKTURA')
                    ], $initialData));
    if ($invoice->sync()) {
        $invoice->addStatusMessage($invoice->getApiURL() . ' ' . unc($invoice->getDataValue('typDokl')) . ' ' . unc($invoice->getRecordIdent()) . ' ' . unc($invoice->getDataValue('sumCelkem')) . ' ' . unc($invoice->getDataValue('mena')),
                'success');
    } else {
        $invoice->addStatusMessage(json_encode($invoice->getData()), 'debug');
    }

    return $invoice;
}

/**
 * Prepare testing payment
 * 
 * @param array $initialData
 * 
 * @return \AbraFlexi\Banka
 */
function makePayment($initialData = [], $dayBack = 1)
{
    $yesterday = new \DateTime();
    $yesterday->modify('-' . $dayBack . ' day');
    $testCode = 'PAY_' . \Ease\Functions::randomString();
    $payment = new \AbraFlexi\Banka($initialData);
    $payment->takeData(array_merge([
        'kod' => $testCode,
        'banka' => 'code:HLAVNI',
        'typPohybuK' => 'typPohybu.prijem',
        'popis' => 'abraflexi-matcher Test bank record',
        'varSym' => \Ease\Functions::randomNumber(1111, 9999),
        'specSym' => \Ease\Functions::randomNumber(111, 999),
        'bezPolozek' => true,
        'datVyst' => \AbraFlexi\RO::dateToFlexiDate($yesterday),
        'typDokl' => \AbraFlexi\RO::code('code:STANDARD')
                    ], $initialData));
    if ($payment->sync()) {
        $payment->addStatusMessage($payment->getApiURL() . ' ' . unc($payment->getDataValue('typPohybuK')) . ' ' . unc($payment->getRecordIdent()) . ' ' . unc($payment->getDataValue('sumCelkem')) . ' ' . unc($payment->getDataValue('mena')),
                'success');
    } else {
        $payment->addStatusMessage(json_encode($payment->getData()), 'debug');
    }
    return $payment;
}
$labeler = new AbraFlexi\Stitek();
try {
    $labeler->createNew(\Ease\Functions::cfg('MATCHER_LABEL_PREPLATEK'), ['banka']);
} catch (AbraFlexi\Exception $exc) {
    
}
try {
    $labeler->createNew(\Ease\Functions::cfg('MATCHER_LABEL_CHYBIFAKTURA'), ['banka']);
} catch (AbraFlexi\Exception $exc) {
    
}
try {
    $labeler->createNew(\Ease\Functions::cfg('MATCHER_LABEL_NEIDENTIFIKOVANO'), ['banka']);
} catch (AbraFlexi\Exception $exc) {
    
}

$banker = new AbraFlexi\Banka(null, ['evidence' => 'bankovni-ucet']);
if (!$banker->recordExists(['kod' => 'HLAVNI'])) {
    $banker->insertToAbraFlexi(['kod' => 'HLAVNI', 'nazev' => 'Main Account']);
}

$addresar = new AbraFlexi\Evidence(new \AbraFlexi\Adresar(),
        ['typVztahuK' => 'typVztahu.odberDodav', 'relations' => 'bankovniSpojeni']);
$adresser = new \AbraFlexi\Adresar();
//$allAddresses = $adresser->getColumnsFromAbraFlexi(['kod'],
//    ['typVztahuK' => 'typVztahu.odberDodav','relations'=>'bankovniSpojeni']);

$pu = new \AbraFlexi\RW(['kod' => '9999', 'nazev' => 'TEST Bank'],
        ['evidence' => 'penezni-ustav']);
if (!$pu->recordExists()) {
    $pu->insertToAbraFlexi();
}


$pf = new \AbraFlexi\Matcher\ParovacFaktur(\Ease\Shared::instanced()->configuration);
foreach ($addresar->getEvidenceObjects() as $address) {
    $allAddresses[] = $address->getData();
    if (empty($address->getDataValue('bankovniSpojeni'))) {
        $fap = new AbraFlexi\Banka(['buc' => time(), 'smerKod' => 'code:9999'],
                ['offline' => true]);
        $pf->assignBankAccountToAddress($address, $fap);
    }
}

$customer = $allAddresses[array_rand($allAddresses)];
do {
    $firmaA = $allAddresses[array_rand($allAddresses)];
    $bucA = $adresser->getBankAccountNumber(\AbraFlexi\RO::code($firmaA['kod']));
} while (empty($bucA));
if (!\Ease\Functions::isAssoc($bucA)) {
    $bucA = current($bucA);
}


$adresser->addStatusMessage('Company A: ' . $firmaA['kod']);
do {
    $firmaB = $allAddresses[array_rand($allAddresses)];
    $bucB = $adresser->getBankAccountNumber(\AbraFlexi\RO::code($firmaB['kod']));
} while (empty($bucB));
if (!\Ease\Functions::isAssoc($bucB)) {
    $bucB = current($bucB);
}

$adresser->addStatusMessage('Company B: ' . $firmaB['kod']);
$firma = \AbraFlexi\RO::code($customer['kod']);
$buc = $customer['id'] . $customer['id'] . $customer['id'];
$bank = 'code:0300';
for ($i = 0; $i <= \Ease\Functions::cfg('MATCHER_DAYS_BACK') + 3; $i++) {
    $banker->addStatusMessage($i . '/' . (\Ease\Functions::cfg('MATCHER_DAYS_BACK') + 3));
    $varSym = \Ease\Functions::randomNumber(1111, 9999);
    $specSym = \Ease\Functions::randomNumber(111, 999);
    $price = \Ease\Functions::randomNumber(11, 99);
    $invoiceSs = makeInvoice(['varSym' => $varSym, 'specSym' => $specSym, 'sumZklZaklMen' => $price,
        'mena' => 'code:EUR', 'firma' => $firma], $i);
    $paymentSs = makePayment(['specSym' => $specSym, 'sumZklZaklMen' => $price, 'mena' => 'code:EUR',
        'buc' => $buc, 'smerKod' => $bank], $i);
    $invoiceVs = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => $price, 'firma' => $firma],
            $i);
    $paymentVs = makePayment(['varSym' => $varSym, 'sumZklZakl' => $price, 'buc' => $buc,
        'smerKod' => $bank], $i);
    $dobropis = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => -$price, 'typDokl' => \AbraFlexi\RO::code('ZDD')],
            $i);
    $zaloha = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => $price, 'typDokl' => \AbraFlexi\RO::code('ZÁLOHA')],
            $i);
    $varSym = \Ease\Functions::randomNumber(1111, 9999);
    $price = \Ease\Functions::randomNumber(11, 99);
    $prijata = makeInvoice(['cisDosle' => $varSym, 'varSym' => $varSym, 'sumZklZakl' => $price,
        'datSplat' => \AbraFlexi\RW::dateToFlexiDate(new DateTime()),
        'typDokl' => \AbraFlexi\RO::code((rand(0, 1) == 1) ? 'FAKTURA' : 'ZÁLOHA')],
            $i, 'prijata');
    $paymentin = makePayment(['varSym' => $varSym, 'sumOsv' => $price, 'typPohybuK' => 'typPohybu.vydej'],
            $i);
    $varSym = \Ease\Functions::randomNumber(1111, 9999);
    $price = \Ease\Functions::randomNumber(11, 99);
    $prijataA = makeInvoice(['cisDosle' => $varSym, 'varSym' => $varSym, 'sumZklZakl' => $price,
        'datSplat' => \AbraFlexi\RW::dateToFlexiDate(new DateTime()),
        'firma' => \AbraFlexi\RO::code($firmaA['kod']),
        'buc' => $bucA['buc'], 'smerKod' => $bucA['smerKod'],
        'typDokl' => \AbraFlexi\RO::code('FAKTURA')], $i, 'prijata');
    $prijataB = makeInvoice(['cisDosle' => $varSym, 'varSym' => $varSym, 'sumZklZakl' => $price,
        'datSplat' => \AbraFlexi\RW::dateToFlexiDate(new DateTime()),
        'firma' => \AbraFlexi\RO::code($firmaB['kod']),
        'buc' => $bucB['buc'], 'smerKod' => $bucB['smerKod'],
        'typDokl' => \AbraFlexi\RO::code('FAKTURA')], $i, 'prijata');
    $paymentin1 = makePayment(['varSym' => $varSym, 'sumOsv' => $price, 'typPohybuK' => 'typPohybu.vydej',
        'buc' => $bucA['buc'], 'smerKod' => $bucA['smerKod']], $i);
    $paymentin2 = makePayment(['varSym' => $varSym, 'sumOsv' => $price, 'typPohybuK' => 'typPohybu.vydej',
        'buc' => $bucB['buc'], 'smerKod' => $bucB['smerKod']], $i);
}
 