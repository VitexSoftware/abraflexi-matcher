<?php

declare(strict_types=1);

/**
 * This file is part of the  AbraFlexi Matcher package.
 *
 * (c) Vítězslav Dvořák <https://vitexsoftware.cz/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

\define('EASE_LOGGER', 'syslog|console');

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

/**
 * Prepare Testing Invoice.
 *
 * @param array $initialData
 * @param mixed $dayBack
 * @param mixed $evidence
 *
 * @return \AbraFlexi\FakturaVydana
 */
function makeInvoice($initialData = [], $dayBack = 1, $evidence = 'vydana')
{
    $yesterday = new \DateTime();
    $yesterday->modify('-'.$dayBack.' day');
    $testCode = 'INV_'.\Ease\Functions::randomString();
    $invoice = new \AbraFlexi\FakturaVydana(
        null,
        ['evidence' => 'faktura-'.$evidence],
    );
    $invoice->takeData(array_merge([
        'kod' => $testCode,
        'varSym' => \Ease\Functions::randomNumber(1111, 9999),
        'specSym' => \Ease\Functions::randomNumber(111, 999),
        'bezPolozek' => true,
        'popis' => 'php-abraflexi-matcher Test invoice',
        'datVyst' => \AbraFlexi\Functions::dateToFlexiDate($yesterday),
        'typDokl' => \AbraFlexi\Functions::code('FAKTURA'),
    ], $initialData));

    if ($invoice->sync()) {
        $invoice->addStatusMessage(
            $invoice->getApiURL().' '.\AbraFlexi\Functions::uncode($invoice->getDataValue('typDokl')).' '.\AbraFlexi\Functions::uncode($invoice->getRecordIdent()).' '.\AbraFlexi\Functions::uncode($invoice->getDataValue('sumCelkem')).' '.\AbraFlexi\Functions::uncode($invoice->getDataValue('mena')),
            'success',
        );
    } else {
        $invoice->addStatusMessage(json_encode($invoice->getData()), 'debug');
    }

    return $invoice;
}

/**
 * Prepare testing payment.
 *
 * @param array $initialData
 * @param mixed $dayBack
 *
 * @return \AbraFlexi\Banka
 */
function makePayment($initialData = [], $dayBack = 1)
{
    $yesterday = new \DateTime();
    $yesterday->modify('-'.$dayBack.' day');
    $testCode = 'PAY_'.\Ease\Functions::randomString();
    $payment = new \AbraFlexi\Banka($initialData);
    $payment->takeData(array_merge([
        'kod' => $testCode,
        'banka' => 'code:HLAVNI',
        'typPohybuK' => 'typPohybu.prijem',
        'popis' => 'abraflexi-matcher Test bank record',
        'varSym' => \Ease\Functions::randomNumber(1111, 9999),
        'specSym' => \Ease\Functions::randomNumber(111, 999),
        'bezPolozek' => true,
        'datVyst' => \AbraFlexi\Functions::dateToFlexiDate($yesterday),
        'typDokl' => \AbraFlexi\Functions::code('code:STANDARD'),
    ], $initialData));

    if ($payment->sync()) {
        $payment->addStatusMessage(
            $payment->getApiURL().' '.\AbraFlexi\Functions::uncode($payment->getDataValue('typPohybuK')).' '.\AbraFlexi\Functions::uncode($payment->getRecordIdent()).' '.\AbraFlexi\Functions::uncode($payment->getDataValue('sumCelkem')).' '.\AbraFlexi\Functions::uncode($payment->getDataValue('mena')),
            'success',
        );
    } else {
        $payment->addStatusMessage(json_encode($payment->getData()), 'debug');
    }

    return $payment;
}

$labeler = new AbraFlexi\Stitek();

if (\Ease\Shared::cfg('APP_DEBUG')) {
    $labeler->logBanner();
}

if ($labeler->recordExists(\AbraFlexi\Functions::code(\Ease\Shared::cfg('MATCHER_LABEL_PREPLATEK'))) === false) {
    try {
        $labeler->createNew(\Ease\Shared::cfg('MATCHER_LABEL_PREPLATEK'), ['banka']);
    } catch (AbraFlexi\Exception $exc) {
    }
}

if ($labeler->recordExists(\AbraFlexi\Functions::code(\Ease\Shared::cfg('MATCHER_LABEL_CHYBIFAKTURA'))) === false) {
    try {
        $labeler->createNew(\Ease\Shared::cfg('MATCHER_LABEL_CHYBIFAKTURA'), ['banka']);
    } catch (AbraFlexi\Exception $exc) {
    }
}

if ($labeler->recordExists(\AbraFlexi\Functions::code(\Ease\Shared::cfg('MATCHER_LABEL_NEIDENTIFIKOVANO'))) === false) {
    try {
        $labeler->createNew(\Ease\Shared::cfg('MATCHER_LABEL_NEIDENTIFIKOVANO'), ['banka']);
    } catch (AbraFlexi\Exception $exc) {
    }
}

$banker = new AbraFlexi\Banka(null, ['evidence' => 'bankovni-ucet']);

if (!$banker->recordExists(['kod' => 'HLAVNI'])) {
    $banker->insertToAbraFlexi(['kod' => 'HLAVNI', 'nazev' => 'Main Account']);
}

$addresar = new AbraFlexi\Evidence(
    new \AbraFlexi\Adresar(),
    ['typVztahuK' => 'typVztahu.odberDodav', 'relations' => 'bankovniSpojeni'],
);
$adresser = new \AbraFlexi\Adresar();
// $allAddresses = $adresser->getColumnsFromAbraFlexi(['kod'],
//    ['typVztahuK' => 'typVztahu.odberDodav','relations'=>'bankovniSpojeni']);

$pu = new \AbraFlexi\RW(
    ['kod' => '9999', 'nazev' => 'TEST Bank'],
    ['evidence' => 'penezni-ustav'],
);

if ($pu->recordExists() === false) {
    $pu->insertToAbraFlexi();
}

$pf = new \AbraFlexi\Matcher\ParovacFaktur(\Ease\Shared::instanced()->configuration);

foreach ($addresar->getEvidenceObjects() as $address) {
    $allAddresses[] = $address->getData();

    if (empty($address->getDataValue('bankovniSpojeni'))) {
        $fap = new AbraFlexi\Banka(
            ['buc' => time(), 'smerKod' => 'code:9999'],
            ['offline' => true],
        );
        $pf->assignBankAccountToAddress($address, $fap);
    }
}

$customer = $allAddresses[array_rand($allAddresses)];

do {
    $firmaA = $allAddresses[array_rand($allAddresses)];
    $bucA = $adresser->getBankAccountNumber(\AbraFlexi\Functions::code($firmaA['kod']));
} while (empty($bucA));

if (!\Ease\Functions::isAssoc($bucA)) {
    $bucA = current($bucA);
}

$adresser->addStatusMessage('Company A: '.$firmaA['kod']);

do {
    $firmaB = $allAddresses[array_rand($allAddresses)];
    $bucB = $adresser->getBankAccountNumber(\AbraFlexi\Functions::code($firmaB['kod']));
} while (empty($bucB));

if (!\Ease\Functions::isAssoc($bucB)) {
    $bucB = current($bucB);
}

$adresser->addStatusMessage('Company B: '.$firmaB['kod']);
$firma = \AbraFlexi\Functions::code($customer['kod']);
$buc = $customer['id'].$customer['id'].$customer['id'];
$bank = 'code:0300';

for ($i = 0; $i <= \Ease\Shared::cfg('MATCHER_DAYS_BACK') + 3; ++$i) {
    $pf->addStatusMessage($i.'/'.(\Ease\Shared::cfg('MATCHER_DAYS_BACK') + 3));
    $varSym = \Ease\Functions::randomNumber(1111, 9999);
    $specSym = \Ease\Functions::randomNumber(111, 999);
    $price = \Ease\Functions::randomNumber(11, 99);
    $invoiceSs = makeInvoice(['varSym' => $varSym, 'specSym' => $specSym, 'sumZklZaklMen' => $price, 'mena' => 'code:EUR', 'firma' => $firma], $i);
    $paymentSs = makePayment(['specSym' => $specSym, 'sumZklZaklMen' => $price, 'mena' => 'code:EUR', 'buc' => $buc, 'smerKod' => $bank], $i);
    $invoiceVs = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => $price, 'firma' => $firma], $i);
    $paymentVs = makePayment(['varSym' => $varSym, 'sumZklZakl' => $price, 'buc' => $buc, 'smerKod' => $bank], $i);
    $dobropis = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => -$price, 'typDokl' => \AbraFlexi\Functions::code('ZDD')], $i);
    $zaloha = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => $price, 'typDokl' => \AbraFlexi\Functions::code('ZÁLOHA')], $i);
    $varSym = \Ease\Functions::randomNumber(1111, 9999);
    $price = \Ease\Functions::randomNumber(11, 99);
    $prijata = makeInvoice(
        ['cisDosle' => $varSym, 'varSym' => $varSym, 'sumZklZakl' => $price,
            'datSplat' => \AbraFlexi\Functions::dateToFlexiDate(new DateTime()),
            'typDokl' => \AbraFlexi\Functions::code((mt_rand(0, 1) === 1) ? 'FAKTURA' : 'ZDD')],
        $i,
        'prijata',
    );
    $paymentin = makePayment(
        ['varSym' => $varSym, 'sumOsv' => $price, 'typPohybuK' => 'typPohybu.vydej'],
        $i,
    );
    $varSym = \Ease\Functions::randomNumber(1111, 9999);
    $price = \Ease\Functions::randomNumber(11, 99);
    $prijataA = makeInvoice(['cisDosle' => $varSym, 'varSym' => $varSym, 'sumZklZakl' => $price,
        'datSplat' => \AbraFlexi\Functions::dateToFlexiDate(new DateTime()),
        'firma' => \AbraFlexi\Functions::code($firmaA['kod']),
        'buc' => $bucA['buc'], 'smerKod' => $bucA['smerKod'],
        'typDokl' => \AbraFlexi\Functions::code('FAKTURA')], $i, 'prijata');
    $prijataB = makeInvoice(['cisDosle' => $varSym, 'varSym' => $varSym, 'sumZklZakl' => $price,
        'datSplat' => \AbraFlexi\Functions::dateToFlexiDate(new DateTime()),
        'firma' => \AbraFlexi\Functions::code($firmaB['kod']),
        'buc' => $bucB['buc'], 'smerKod' => $bucB['smerKod'],
        'typDokl' => \AbraFlexi\Functions::code('FAKTURA')], $i, 'prijata');
    $paymentin1 = makePayment(['varSym' => $varSym, 'sumOsv' => $price, 'typPohybuK' => 'typPohybu.vydej',
        'buc' => $bucA['buc'], 'smerKod' => $bucA['smerKod']], $i);
    $paymentin2 = makePayment(['varSym' => $varSym, 'sumOsv' => $price, 'typPohybuK' => 'typPohybu.vydej',
        'buc' => $bucB['buc'], 'smerKod' => $bucB['smerKod']], $i);
}
