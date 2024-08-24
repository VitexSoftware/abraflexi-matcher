<?php

/**
 * Invoice Matcher wrapper
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright (c) 2018-2024, Vítězslav Dvořák
 */

namespace AbraFlexi\Matcher;

use AbraFlexi\Banka;
use AbraFlexi\FakturaVydana;

/**
 * Invoice matching class
 *
 * @copyright (c) 2018-2023, Vítězslav Dvořák
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class ParovacFaktur extends \Ease\Sand
{
    /**
     * Invoice handler object
     * @var  FakturaVydana|\AbraFlexi\FakturaPrijata
     */
    private $invoicer;

    /**
     * account statements handler object
     * @var \AbraFlexi\Banka
     */
    public $banker;

    /**
     * @var int Since when to start tracing documents?
     */
    public $daysBack = 1;

    /**
     * Configuration options
     * @var array
     */
    private $config = ['limit' => 0];

    /**
     * Requied Config Keys
     * @var array
     */
    public $cfgRequed = ["LABEL_OVERPAY", "LABEL_INVOICE_MISSING", "LABEL_UNIDENTIFIED"];

    /**
     *
     * @var array
     */
    private $docTypes;

    /**
     *
     * @var array
     */
    public $defaultHttpHeaders;

    /**
     * Invoice matcher
     *
     * @param array $configuration
     */
    public function __construct($configuration = [])
    {
        $configuration['LABEL_OVERPAY'] = \Ease\Shared::cfg('MATCHER_LABEL_PREPLATEK', 'PREPLATEK');
        $configuration['LABEL_INVOICE_MISSING'] = \Ease\Shared::cfg('MATCHER_LABEL_CHYBIFAKTURA', 'CHYBIFAKTURA');
        $configuration['LABEL_UNIDENTIFIED'] = \Ease\Shared::cfg('MATCHER_LABEL_NEIDENTIFIKOVANO', 'NEIDENTIFIKOVANO');
        $this->config = array_merge($this->config, $configuration);
        foreach ($this->cfgRequed as $key) {
            if ((array_key_exists($key, $this->config) === false) || empty($this->config[$key])) {
                throw new \Ease\Exception(sprintf(
                    _('Configuration key %s is not set'),
                    $key
                ));
            }
        }
        $this->banker = new \AbraFlexi\Banka(null, $this->config);
        $this->invoicer = new \AbraFlexi\FakturaVydana(null, $this->config);
        $this->setObjectName();
    }

    /**
     * Start set date
     *
     * @param int $daysBack
     */
    public function setStartDay($daysBack)
    {
        if (!is_null($daysBack)) {
            $this->addStatusMessage('Start Date ' . date(
                'Y-m-d',
                mktime(0, 0, 0, date("m"), date("d") - $daysBack, date("Y"))
            ));
        }
        $this->daysBack = $daysBack;
    }

    /**
     * Prepare invoice helper
     *
     * @return  FakturaVydana
     */
    public function getInvoicer()
    {
        if (!is_object($this->invoicer)) {
            $this->invoicer = new FakturaVydana(null, $this->config);
        }
        return $this->invoicer;
    }

    /**
     * Get unmatched payments within given days and direction
     *
     * @param int    $daysBack Maximum age of payment
     * @param string $direction Incoming or outcoming payents in|out
     *
     * @return array
     */
    public function getPaymentsToProcess(int $daysBack = 1, $direction = 'in')
    {
        $result = [];
        $this->banker->defaultUrlParams['order'] = 'datVyst@A';
        $payments = $this->banker->getColumnsFromAbraFlexi(
            [
                    'id',
                    'kod',
                    'varSym',
                    'specSym',
                    'sumCelkem',
                    'buc',
                    'banka',
                    'smerKod',
                    'mena',
                    'datVyst',
                    'typDokl'
                ],
            ["sparovano eq false AND typPohybuK eq '" . (($direction == 'out') ? 'typPohybu.vydej' : 'typPohybu.prijem') . "' AND storno eq false " .
                    ($daysBack ? "AND datVyst gte '" . \AbraFlexi\RW::timestampToFlexiDate(mktime(0, 0, 0, date("m"), date("d") - $daysBack, date("Y"))) . "' " : '')
                ],
            'id'
        );
        if ($this->banker->lastResponseCode == 200) {
            if (empty($payments)) {
                $result = [];
            } else {
                $result = $payments;
            }
        }
        return $result;
    }

    /**
     *
     * @param \DatePeriod $period
     * @param string  $direction
     *
     * @return array
     */
    public function getPaymentsWithinPeriod(
        \DatePeriod $period,
        $direction = 'in'
    ) {
        $result = [];
        $this->banker->defaultUrlParams['order'] = 'datVyst@A';
        $conds['storno'] = false;
        $conds['sparovano'] = false;
        $conds['typPohybuK'] = ($direction == 'out') ? 'typPohybu.vydej' : 'typPohybu.prijem';
        $conds['datVyst'] = $period;
        $payments = $this->banker->getColumnsFromAbraFlexi([
            'id',
            'kod',
            'buc',
            'smerKod',
            'varSym',
            'specSym',
            'sumCelkem',
            'mena',
            'datVyst'], $conds, 'id');
        if ($this->banker->lastResponseCode == 200) {
            if (empty($payments)) {
                $result = [];
            } else {
                $result = $payments;
            }
        }
        return $result;
    }

    /**
     * Vrací neuhrazené faktury
     *
     * @return array
     */
    public function getInvoicesToProcess()
    {
        $this->getInvoicer();
        $this->invoicer->defaultUrlParams['includes'] = '/faktura-vydana/typDokl';
        return $this->searchInvoices(["(stavUhrK is null OR stavUhrK eq 'stavUhr.castUhr') AND storno eq false"]);
    }

    /**
     * Match Invoice with Payment
     *
     * @param \AbraFlexi\Banka $payment
     *
     * @return boolean
     */
    public function outInvoiceMatchByBank($invoiceData, $payment)
    {
        $typDokl = $invoiceData['typDokl'];
        $docType = $typDokl->value[0]['typDoklK'];
        $docTypeShowAs = $typDokl->value[0]['typDoklK@showAs'];
        $invoiceData['typDokl'] = \AbraFlexi\RO::code($typDokl->value[0]['kod']);
        $invoice = new FakturaVydana($invoiceData, $this->config);
        /*
         *    Standardní faktura (typDokladu.faktura)
         *    Dobropis/opravný daň. d. (typDokladu.dobropis)
         *    Zálohová faktura (typDokladu.zalohFaktura)
         *    Zálohový daňový doklad (typDokladu.zdd)
         *    Dodací list (typDokladu.dodList)
         *    Proforma (neúčetní) (typDokladu.proforma)
         *    Pohyb Kč / Zůstatek Kč (typBanUctu.kc)
         *    Pohyb měna / Zůstatek měna (typBanUctu.mena)
         */
        $matched = false;
        switch ($docType) {
            case 'typDokladu.zalohFaktura':
            case 'typDokladu.faktura':
                $matched = $this->settleInvoice($invoice, $payment);
                break;
            case 'typDokladu.proforma':
                $matched = $this->settleProforma($invoice, $payment);
                break;
            case 'typDokladu.dobropis':
                $matched = $this->settleCreditNote($invoice, $payment);
                break;
            default:
                $this->addStatusMessage(
                    sprintf(
                        _('Unsupported document type: %s %s'),
                        $docTypeShowAs . ' (' . $docType . '): ' . $invoiceData['typDokl'],
                        $invoice->getApiURL()
                    ),
                    'warning'
                );
                break;
        }

        if (strlen($invoice->getDataValue('firma'))) {
            if (
                $matched && $this->savePayerAccount(
                    $invoice->getDataValue('firma'),
                    $payment
                )
            ) {
                $this->addStatusMessage(sprintf(
                    _('new Bank account %s assigned to Address %s'),
                    $payment->getDataValue('buc') . '/' . \AbraFlexi\RO::uncode($payment->getDataValue('smerKod')),
                    $invoice->getDataValue('firma')->showAs
                ));
            }
        }
        $this->banker->loadFromAbraFlexi($payment);
        return $this->banker->getDataValue('sparovano');
    }

    /**
     * Matching outgoing invoices according to incoming payments in the bank
     */
    public function issuedInvoicesMatchingByBank()
    {
        $this->getInvoicer();
        foreach ($this->getPaymentsToProcess($this->daysBack, 'in') as $paymentData) {
            $this->banker->dataReset();
            $this->banker->setData($paymentData);
            $this->addStatusMessage(
                sprintf(
                    _('Checking Payment %s %s %s vs: %s from: %s %s'),
                    $paymentData['kod'],
                    $paymentData['sumCelkem'],
                    \AbraFlexi\RO::uncode($paymentData['mena']),
                    $paymentData['varSym'],
                    $paymentData['datVyst'],
                    $this->banker->getRecordCode()
                ),
                'info'
            );
            $invoices = $this->findInvoices($paymentData);
            //  kdyz se vrati jedna faktura:
            //     kdyz  je prijata castka mensi nebo rovno tak zlikviduji celou
            //     kdyz sedi castka, nebo castecne
            //  kdyz se vrati vic faktur  tak kdyz sedi castka uhrazuje se ta nejstarsi
            //  jinak se uhrazuje castecne

            if (count($invoices) && count(current($invoices))) {
                $prijatoCelkem = floatval($paymentData['sumCelkem']);
                $payment = new \AbraFlexi\Banka($paymentData, $this->config);
                foreach ($invoices as $invoiceID => $invoiceData) {
                    if ($this->outInvoiceMatchByBank($invoiceData, $payment)) {
                        break;
                    }
                }
            } else {
                if (!empty($paymentData['varSym'])) {
                    if (!empty($paymentData['varSym'])) {
                        $vInvoices = $this->searchInvoices(['varSym' => intval($paymentData['varSym'])]);
                    }
                }
                if (!empty($paymentData['specSym'])) {
                    if (!empty($paymentData['specSym'])) {
                        $sInvoices = $this->searchInvoices(['specSym' => $paymentData['specSym']]);
                    }
                }

                //                if ($vInvoices || $sInvoices) {
                ////                    $zdd = $this->paymentToZDD($payment);
                ////                    if ($zdd) {
                ////                        $this->addStatusMessage(sprinf(_('advance tax document created'),
                ////                                \AbraFlexi\RO::uncode($zdd)));
                ////                    }
                //
                //                    $this->addStatusMessage(_('Invoice found: - overdue?'),
                //                        'warning');
                //                }
            }
        }
    }

    public function paymentToZDD($invoiceData)
    {
        $return = $this->invoiceCopy($invoiceData, 'ZDD');
    }

    /**
     * Párování prichozich faktur podle odchozich plateb v bance
     *
     * @param  $rang Time range for payment matching
     *
     */
    public function inInvoicesMatchingByBank(\DatePeriod $range = null)
    {
        $this->invoicer = new \AbraFlexi\FakturaPrijata(null, $this->config);
        foreach ($this->getPaymentsWithinPeriod($range, 'out') as $outPaymentId => $outPaymentData) {
            $this->banker->setData($outPaymentData, true);
            $this->banker->setMyKey($outPaymentId);
            $this->addStatusMessage(sprintf(
                'Processing Outgoing Payment %s %s %s vs: %s ss: %s %s',
                $outPaymentData['kod'],
                $outPaymentData['sumCelkem'],
                \AbraFlexi\RO::uncode($outPaymentData['mena']),
                $outPaymentData['varSym'],
                $outPaymentData['specSym'],
                $this->banker->getApiURL()
            ), 'info');
            $inInvoicesToMatch = $this->findInvoices($outPaymentData);
            //  kdyz se vrati jedna faktura:
            //     kdyz  je prijata castka mensi nebo rovno tak zlikviduji celou
            //     kdyz sedi castka, nebo castecne
            //  kdyz se vrati vic faktur  tak kdyz sedi castka uhrazuje se ta nejstarsi
            //  jinak se uhrazuje castecne



            switch (count($inInvoicesToMatch)) {
                case 0:
                    $this->addStatusMessage(_('No incoming invoice found for outcoming payment'));
                    break;
                case 1:
                    $invoiceData = current($inInvoicesToMatch);
                    $invoiceID = key($inInvoicesToMatch);
                    $inInvoice = new FakturaVydana(
                        $invoiceData,
                        array_merge(
                            $this->config,
                            ['evidence' => 'faktura-prijata']
                        )
                    );
                    if ($this->settleInvoice($inInvoice, $this->banker)) {
                        //Post match action here
                    }
                    break;
                default:
                    if (self::isSameCompany($inInvoicesToMatch)) {
                        foreach ($inInvoicesToMatch as $invoiceID => $invoiceData) {
                            $inInvoice = new FakturaVydana(
                                $invoiceData,
                                array_merge(
                                    $this->config,
                                    ['evidence' => 'faktura-prijata']
                                )
                            );
                            if ($this->settleInvoice($inInvoice, $this->banker)) {
                            }
                        }
                    } else {
                        $this->addStatusMessage(_('Match by bank here'));
                        foreach ($inInvoicesToMatch as $invoiceID => $invoiceData) {
                            $inInvoice = new FakturaVydana(
                                $invoiceData,
                                array_merge(
                                    $this->config,
                                    ['evidence' => 'faktura-prijata']
                                )
                            );
                        }
                    }
                    break;
            }


            if (count($inInvoicesToMatch) && count(current($inInvoicesToMatch))) {
                $uhrazenoCelkem = floatval($outPaymentData['sumCelkem']);
                $payment = new \AbraFlexi\Banka($outPaymentData, $this->config);
            }
        }
    }

    /**
     * Obtain AbraFlexi company code for given bank account number
     *
     * @param string $account
     * @param string $bankCode
     *
     * @return string Company Code
     */
    public function getCompanyForBUC($account, $bankCode = null)
    {
        $bucer = new \AbraFlexi\RW(null, ['evidence' => 'adresar-bankovni-ucet']);
        $companyRaw = $bucer->getColumnsFromAbraFlexi(
            ['firma'],
            empty($bankCode) ? ['buc' => $account] : ['buc' => $account, 'smerKod' => $bankCode]
        );
        return array_key_exists(0, $companyRaw) ? $companyRaw[0]['firma'] : null;
    }

    /**
     * Check for common company
     *
     * @param array $documents invoices or payments data
     *
     * @return boolean All records have same company
     */
    public static function isSameCompany($documents)
    {
        return count(\Ease\Functions::reindexArrayBy($documents, 'firma')) == 1;
    }

    /**
     * Check for common bank account
     *
     * @param array $documents invoices or payments data
     *
     * @return boolean All records have same bank account
     */
    public static function isSameAccount($documents)
    {
        return count(\Ease\Functions::reindexArrayBy($documents, 'buc')) == 1;
    }

    /**
     * Párování faktur dle nezaplacenych faktur
     */
    public function invoicesMatchingByInvoices()
    {
        foreach ($this->getInvoicesToProcess() as $invoiceData) {
            $payments = $this->findPayments($invoiceData);
            if (!empty($payments) && count(current($payments))) {
                $typDokl = $invoiceData['typDokl'][0];
                $docType = $typDokl['typDoklK'];
                $invoiceData['typDokl'] = \AbraFlexi\RO::code($typDokl['kod']);
                $invoice = new FakturaVydana($invoiceData, $this->config);
                $this->invoicer->setMyKey($invoiceData['id']);
                /*
                 *    Standardní faktura (typDokladu.faktura)
                 *    Dobropis/opravný daň. d. (typDokladu.dobropis)
                 *    Zálohová faktura (typDokladu.zalohFaktura)
                 *    Zálohový daňový doklad (typDokladu.zdd)
                 *    Dodací list (typDokladu.dodList)
                 *    Proforma (neúčetní) (typDokladu.proforma)
                 *    Pohyb Kč / Zůstatek Kč (typBanUctu.kc)
                 *    Pohyb měna / Zůstatek měna (typBanUctu.mena)
                 */

                foreach ($payments as $paymentData) {
                    $payment = new \AbraFlexi\Banka($paymentData, $this->config);
                    switch ($docType) {
                        case 'typDokladu.zalohFaktura':
                        case 'typDokladu.faktura':
                            if ($this->settleInvoice($invoice, $payment)) {
                            }
                            break;
                        case 'typDokladu.proforma':
                            $this->settleProforma($invoice, $payments);
                            break;
                        case 'typDokladu.dobropis':
                            $this->settleCreditNote($invoice, $payments);
                            break;
                        default:
                            $this->addStatusMessage(
                                sprintf(
                                    _('Unsupported document type: %s %s'),
                                    $typDokl['typDoklK']->showAs . ' (' . $docType . '): ' . $invoiceData['typDokl'],
                                    $invoice->getApiURL()
                                ),
                                'warning'
                            );
                            break;
                    }
                }
            }
        }
    }

    /**
     * Provede "Zaplacení" vydaného dobropisu
     *
     * @param  FakturaVydana $invoice
     * @param \AbraFlexi\Banka $payment
     *
     * @return int vysledek 0 = chyba, 1 = sparovano
     */
    public function settleCreditNote(FakturaVydana $invoice, Banka $payment)
    {
        $success = 0;
        $prijataCastka = (float) $payment->getDataValue('sumCelkem');
        if ($prijataCastka < $invoice->getDataValue('zbyvaUhradit')) { //Castecna uhrada
            $this->addStatusMessage(
                sprintf(
                    _('Castecna uhrada - DOBROPIS: prijato: %s ma byt zaplaceno %s'),
                    $prijataCastka,
                    $invoice->getDataValue('zbyvaUhradit')
                ),
                'warning'
            );
        }
        if ($prijataCastka > $invoice->getDataValue('zbyvaUhradit')) { //Castecna uhrada
            $this->addStatusMessage(
                sprintf(
                    _('Přeplatek - DOBROPIS: prijato: %s ma byt zaplaceno %s'),
                    $prijataCastka,
                    $invoice->getDataValue('zbyvaUhradit')
                ),
                'warning'
            );
            $this->banker->dataReset();
            $this->banker->setDataValue('id', $payment['id']);
            $this->banker->setDataValue('stitky', $this->config['LABEL_OVERPAY']);
            $this->banker->insertToAbraFlexi();
        }

        if ($invoice->sparujPlatbu($payment, 'castecnaUhrada')) { //Jak se ma AbraFlexi zachovat pri preplatku/nedoplatku
            $success = 1;
            $invoice->addStatusMessage(
                sprintf(
                    _('Platba %s  %s byla sparovana s dobropisem %s'),
                    (string) $payment,
                    $prijataCastka,
                    (string) $invoice
                ),
                'success'
            );
            //PDF Danoveho dokladu priloz k nemu samemu
            //PDF Danoveho dokladu odesli mailem zakaznikovi y ABRAFLEXI( nasledne pouzit tabulku Mail/Gandalf)
        }

        return $success;
    }

    /**
     * Will make "payment" issued advance invoices
     *
     * @param \AbraFlexi\FakturaVydana $zaloha
     * @param \AbraFlexi\Banka         $payment
     *
     * @return int vysledek 0 = chyba, 1 = sparovano, 2 sparovano a vytvorena faktura, -1 sparovnano ale chyba vytvoreni faktury
     */
    public function settleProforma(
        \AbraFlexi\FakturaVydana $zaloha,
        \AbraFlexi\Banka $payment
    ) {
        $success = 0;
        $prijataCastka = (float) $payment->getDataValue('sumCelkem');
        $platba = new \AbraFlexi\Banka(
            \AbraFlexi\RO::code($payment->getDataValue('kod')),
            $this->config
        );

        try {
            if ($zaloha->sparujPlatbu($platba, 'castecnaUhrada')) {
                $success = 1;
                $zaloha->addStatusMessage(
                    sprintf(
                        _('Platba %s  %s %s byla sparovana s zalohou %s'),
                        \AbraFlexi\RO::uncode($platba),
                        $prijataCastka,
                        \AbraFlexi\RO::uncode($payment->getDataValue('mena')),
                        (string) $zaloha
                    ),
                    'success'
                );
                if ($zaloha->getDataValue('zbyvaUhradit') > $prijataCastka) { // Castecna Uhrada
                    //                //Castecna uhrada
                    //                //Vytvorit ZDD ve vysi payment
                    //                $zdd = new  FakturaVydana(['firma' => $zaloha->getDataValue('firma'),
                    //                    'zavTxt' => $zaloha->getDataValue('zavTxt').' DOPLNIT!!! ',
                    //                    'varSym' => $zaloha->getDataValue('varSym'),
                    //                    'popis' => 'Částečná úhrada '.$zaloha->getDataValue('kod')
                    //                ]);
                    //
                    //                $zdd->setDataValue('typDokl', 'code:ZDD');
                    ////                $zdd->setDataValue('zbyvaUhradit', 0); //Mozna nemusime resit -vymazat
                    ////                $zdd->setDataValue('sumCelkem', $prijataCastka);
                    //                $zdd->setDataValue('szbDphZakl',
                    //                    $zaloha->getDataValue('szbDphZakl'));
                    //                $zdd->setDataValue('bezPolozek', true);
                    ////                $zdd->setDataValue('stavUhrK', '');
                    //                $zdd->unsetDataValue('polozkyFaktury');
                    //
                    //                // ---------- Tady se resi sazby - nahrdit objektem pro praci s castkami --------------//
                    //                // DPH21
                    //                if ((float) $zaloha->getDataValue('sumCelkZakl')) {
                    //                    $sumZklZakl = $prijataCastka / ( 1 + (float) $zaloha->getDataValue('szbDphZakl')
                    //                        / 100 );
                    //
                    ////                    $zdd->setDataValue('sumZklZakl', round($sumZklZakl, 2));
                    ////                    $zdd->setDataValue('sumDphZakl',
                    ////                        round($prijataCastka - $sumZklZakl, 2));
                    //                    $zdd->setDataValue('sumCelkZakl', round($prijataCastka, 2));
                    //                    // DPH00
                    //                } else {
                    //                    if ((float) $zaloha->getDataValue('sumOsv')) {
                    ////                        $zdd->setDataValue('sumOsv', round($prijataCastka),
                    ////                            2);
                    //                    }
                    //                }
                    //                $result = $zdd->insertToAbraFlexi();
                    //
                    //                $zdd->loadFromAbraFlexi();
                    //                $zaloha->debug = true;
                    //                $zdd->debug    = true;
                    //
                    //
                    //                $targt      = $platba->apiURL.'/vytvor-zdd.json';
                    //                $zauctovani = '01-02';
                    //                $value      = $zaloha->getDataValue('kod').'^^^'.$zauctovani;
                    //                $sender     = new \AbraFlexi\RW();
                    //                $sender->setPostFields(['zalohaACleneni' => $value]);
                    //                $result     = $sender->performRequest($targt, 'POST', 'json');
                    //
                    //                $result = $zdd->odpocetZDD($zaloha,
                    //                    ['castkaMen' => $prijataCastka]);
                    //                if (isset($result['success']) && ($result['success'] == 'true')) {
                    //                    $success = 2;
                    //                    $zaloha->addStatusMessage(sprintf(_('Faktura #%s byla sparovana se ZDD'),
                    //                            $kod), 'success');
                    //                } else {
                    //                    $success = -1;
                    //                    $zaloha->addStatusMessage(sprintf(_('Faktura #%s nebyla sparovana se ZDD'),
                    //                            $kod), 'error');
                    //                }
                    $zaloha->addStatusMessage(sprintf(
                        _('Částečná úhrada %s'),
                        self::apiUrlToLink($zaloha->apiURL)
                    ), 'warning');
                    $zaloha->addStatusMessage(
                        sprintf(
                            _('Vytvoř ZDD: %s'),
                            self::apiUrlToLink($platba->apiURL . '/vytvor-zdd')
                        ),
                        'debug'
                    );
                } else {
                    if ($prijataCastka > $zaloha->getDataValue('zbyvaUhradit')) { // Preplatek
                        $zaloha->addStatusMessage(sprintf(
                            _('Přeplatek %s'),
                            self::apiUrlToLink($platba->apiURL)
                        ), 'warning');
                    }

                    //Plna uhrada
                    //$toCopy['sumCelkem'] = $payment->getDataValue('sumCelkem');
                    //Dopsat pro vsechny mozne sazby dane - vytvorit objekt

                    $faktura2 = $this->invoiceCopy(
                        $zaloha,
                        [
                                'duzpUcto' => $platba->getDataValue('datVyst'),
                                'datVyst' => $platba->getDataValue('datVyst'),
                                'stavMailK' => 'stavMail.odeslat'
                            ]
                    );
                    $id = (int) $faktura2->getLastInsertedId();
                    $faktura2->loadFromAbraFlexi($id);
                    $kod = $faktura2->getDataValue('kod');
                    $faktura2->dataReset();
                    $faktura2->setDataValue('id', 'code:' . $kod);
                    $faktura2->setDataValue('typDokl', 'code:FAKTURA');
                    $result = $faktura2->odpocetZalohy($zaloha);
                    if ($result) {
                        $success = 2;
                        $zaloha->addStatusMessage(sprintf(_('The invoice %s has been matched'), $kod), 'success');
                    } else {
                        $success = -1;
                        $zaloha->addStatusMessage(sprintf(_('The invoice %s was not been matched'), $kod), 'error');
                    }
                }
            }
        } catch (\AbraFlexi\Exception $exc) {
            //            echo $exc->getTraceAsString();
        }

        return $success;
    }

    /**
     * Provede "Zaplacení" vydané faktury
     *
     * @param  FakturaVydana    $invoice Invoice to settle
     * @param \AbraFlexi\Banka  $payment Payment to settle by
     *
     * @return int vysledek 0 = chyba, 1 = sparovano
     */
    public function settleInvoice($invoice, $payment)
    {
        $success = 0;
        $zbytek = 'ne';
        $prijataCastka = (float) $payment->getDataValue('sumCelkem');
        $zbyvaUhradit = $invoice->getDataValue('zbyvaUhradit');
        if ($prijataCastka < $zbyvaUhradit) { // Partial payment
            $this->addStatusMessage(
                sprintf(
                    _('Partial payment - INVOICE: recieved: %s %s has to be payed %s %s'),
                    $prijataCastka,
                    \AbraFlexi\RO::uncode($payment->getDataValue('mena')),
                    $zbyvaUhradit,
                    \AbraFlexi\RO::uncode($invoice->getDataValue('mena'))
                ),
                'warning'
            );
            $zbytek = 'castecnaUhrada';
        }
        if ($prijataCastka > $zbyvaUhradit) { // Overpay
            $this->addStatusMessage(
                sprintf(
                    _('Overpay - INVOICE: recieved: %s %s excepted %s %s'),
                    $prijataCastka,
                    \AbraFlexi\RO::uncode($payment->getDataValue('mena')),
                    $zbyvaUhradit,
                    \AbraFlexi\RO::uncode($invoice->getDataValue('mena'))
                ),
                'event'
            );
            //$this->banker->insertToAbraFlexi(['id'=>$payment->getDataValue('id'), 'stitky'=>$this->config['LABEL_CASTECNAUHRADA']]);
            $zbytek = 'ignorovat';
        } else {
            try {
                if ($invoice->sparujPlatbu($payment, $zbytek)) { //Jak se ma AbraFlexi zachovat pri preplatku/nedoplatku
                    $success = 1;
                    $invoice->addStatusMessage(
                        sprintf(
                            _('Payment %s  %s %s was matched with invoice %s'),
                            \AbraFlexi\RO::uncode($payment->getRecordIdent()),
                            $prijataCastka,
                            \AbraFlexi\RO::uncode($payment->getDataValue('mena')),
                            \AbraFlexi\RO::uncode($invoice->getRecordIdent())
                        ),
                        'success'
                    );
                }
            } catch (\AbraFlexi\Exception $exc) {
                $success = 0;
            }
        }
        return $success;
    }

    /**
     * Provizorní zkopírování faktury
     *
     * @link https://www.abraflexi.eu/podpora/Tickets/Ticket/View/28848 Chyba při Provádění akcí přes REST API JSON
     * @param  FakturaVydana $invoice
     * @param array                     $extraValues Extra hodnoty pro kopii faktury
     *
     * @return  FakturaVydana
     */
    public function invoiceCopy($invoice, $extraValues = [])
    {

        if (isset($extraValues['datVyst'])) {
            $today = $extraValues['datVyst'];
        } else {
            $today = date('Y-m-d');
        }
        $copyer = new Convertor(
            $invoice,
            new FakturaVydana(array_merge(
                ['typDokl' => 'code:FAKTURA',
                                    'duzpPuv' => $today,
                                    'duzpUcto' => $today,
                                    'datUcto' => $today,
                                    'stitky' => 'SYSTEM',
                                    'stavMailK' => 'stavMail.neodesilat'
                                ],
                $extraValues
            ))
        );
        $invoice2 = $copyer->conversion();
        ////        $invoice2->debug = true;

        if (!array_key_exists('datSplat', $extraValues)) {
            $invoice2->unsetDataValue('datSplat');
        }
        if ($invoice2->getDataValue('stavUhrK') != 'stavUhr.uhrazenoRucne') {
            $invoice2->unsetDataValue('stavUhrK');
        }

        $polozky = $invoice2->getDataValue('polozkyDokladu');
        $invoice2->unsetDataValue('polozkyDokladu');
        if (count($polozky)) {
            foreach ($polozky as $pid => $polozka) {
                unset($polozka['id']);
                unset($polozka['datUcto']);
                unset($polozka['doklFak']);
                unset($polozka['ucetni']);
                if (array_key_exists('stitky', $polozka)) {
                    $labelsFiltered = [];
                    $labels = is_array($polozka['stitky']) ? $polozka['stitky'] : \AbraFlexi\Stitek::listToArray($polozka['stitky']);
                    foreach ($labels as $label) {
                        if (!preg_match('/^API/', $label)) {
                            $labelsFiltered[] = $label;
                        }
                    }
                    if (count($labelsFiltered)) {
                        $polozka['stitky'] = $labelsFiltered;
                    } else {
                        unset($polozka['stitky']);
                    }
                }
                $invoice2->addArrayToBranch($polozka);
            }
        }

        if ($invoice2->sync()) {
            $invoice->addStatusMessage(sprintf(
                _('Invoice %s %s was created from %s %s'),
                \AbraFlexi\RO::uncode($invoice2->getRecordCode()),
                $invoice2->getApiURL(),
                \AbraFlexi\RO::uncode($invoice->getRecordCode()),
                $invoice->getApiURL()
            ), 'success');
        }
        return $invoice2;
    }

    /**
     *
     *
     * @param FakturaVydana $invoice ZDD
     * @param Banka         $payment Income
     *
     * @return type
     */
    public function hotfixDeductionOfAdvances($invoice, $payment)
    {
        return $this->vytvorVazbuZDD($payment->getData(), $invoice);
    }

    /**
     * Filter duplicate invoices
     *
     * @param array $vInvoices new invoices
     * @param array $invoices  current invoices
     */
    public static function unifyInvoices(array $vInvoices, &$invoices)
    {
        if (count($vInvoices)) {
            foreach ($vInvoices as $invoiceID => $invoice) {
                if (!array_key_exists($invoiceID, $invoices)) {
                    $invoices[$invoiceID] = $invoice;
                }
            }
        }
    }

    /**
     * Najde vydané faktury
     *
     * @param array $paymentData
     *
     * @return array
     */
    public function findInvoices($paymentData)
    {
        $invoices = [];
        $vInvoices = [];
        $sInvoices = [];
        $uInvoices = [];
        $bInvoices = [];
        $invoicesForBuc = [];

        $typDokl = "((typDokl.typDoklK eq 'typDokladu.faktura') OR (typDokl.typDoklK eq 'typDokladu.zalohFaktura') OR (typDokl.typDoklK eq 'typDokladu.proforma'))";

        if (!empty($paymentData['varSym'])) {
            $vInvoices = $this->findInvoice(['varSym' => intval($paymentData['varSym']), $typDokl]);
        }

        if (empty($vInvoices)) {
            if (!empty($paymentData['specSym'])) {
                // Faktury vydane "firma":"code:02100",
                // Adresar: ext:lms.cstmr:2365
                $uInvoices = $this->findInvoice(['firma' => sprintf(
                    "code:%05s",
                    $paymentData['specSym'],
                    $typDokl
                )]);
            }

            if (!empty($paymentData['specSym'])) {
                $sInvoices = $this->findInvoice(['specSym' => $paymentData['specSym'], $typDokl]);
            }

            if ($paymentData['buc']) {
                $bInvoices = $this->findInvoice(['buc' => $paymentData['buc']]);
                $addressForBuc = $this->bucToAddress($paymentData['buc']);
                if (strlen($addressForBuc)) {
                    $invoicesForBuc = $this->findInvoice(['firma' => $addressForBuc, $typDokl]);
                }
            }
        }

        self::unifyInvoices($vInvoices, $invoices);
        self::unifyInvoices($uInvoices, $invoices);
        self::unifyInvoices($sInvoices, $invoices);
        self::unifyInvoices($bInvoices, $invoices);
        self::unifyInvoices($invoicesForBuc, $invoices);
        return self::reorderInvoicesByAge($invoices);
    }

    /**
     * Reorder invoices by Age.
     *
     * @param array $invoices
     *
     * @return array Older First sorted invoices
     */
    public static function reorderInvoicesByAge(array $invoices)
    {
        $invoicesByAge = [];
        $invoicesByAgeRaw = [];
        foreach ($invoices as $invoiceData) {
            $invoicesByAgeRaw[$invoiceData['datVyst']->getTimestamp()] = $invoiceData;
        }
        ksort($invoicesByAgeRaw);
        foreach ($invoicesByAgeRaw as $invoiceData) {
            $invoicesByAge[$invoiceData['kod']] = $invoiceData;
        }
        return $invoicesByAge;
    }

    /**
     * Search for incomming payments
     *
     * @param array $invoiceData
     *
     * @return array
     */
    public function findPayments($invoiceData)
    {
        $pays = [];
        $sPays = [];
        $bPays = [];
        if (array_key_exists('varSym', $invoiceData) && !empty($invoiceData['varSym'])) {
            $sPays = $this->findPayment(['varSym' => $invoiceData['varSym']]);
            if (is_array($sPays)) {
                $pays = $sPays;
            }
        }

        if (array_key_exists('specSym', $invoiceData) && !empty($invoiceData['specSym'])) {
            $sPays = $this->findPayment(['specSym' => $invoiceData['specSym']]);
            if (is_array($bPays)) {
                $pays = $bPays;
            }
        }

        if (array_key_exists('buc', $invoiceData) && !empty($invoiceData['buc'])) {
            $bPays = $this->findPayment(['buc' => $invoiceData['buc']]);
            if ($bPays) {
                foreach ($bPays as $payID => $payment) {
                    if (!array_key_exists($payID, $pays)) {
                        $pays[$payID] = $payment;
                    }
                }
            }
        }

        return $pays;
    }

    /**
     * Returns invoices corresponding to the specified parameters
     *
     * @param array $what
     *
     * @return array
     */
    public function findInvoice($what)
    {
        return $this->searchInvoices(["(" . \AbraFlexi\RO::flexiUrl($what, 'AND') . ") AND (stavUhrK is null OR stavUhrK eq 'stavUhr.castUhr') AND storno eq false"]);
    }

    /**
     * Vrací neuhrazene faktury odpovídající zadaným parametrům
     *
     * @param array $what
     *
     * @return array
     */
    public function searchInvoices($what)
    {
        $result = null;
        $this->getInvoicer()->defaultUrlParams['order'] = 'datVyst@A';
        $this->getInvoicer()->defaultUrlParams['limit'] = 0;
        $this->invoicer->defaultUrlParams['includes'] = '/faktura-vydana/typDokl';
        $invoices = $this->invoicer->getColumnsFromAbraFlexi([
            'id',
            'kod',
            'stavUhrK',
            'zbyvaUhradit',
            'firma',
            'buc',
            'mena',
            'varSym',
            'specSym',
            'typDokl(typDoklK,kod)',
            'sumCelkem',
            'duzpPuv',
            'stitky',
            'typDokl',
            'datVyst'
                ], $what, 'id');
        if ($this->invoicer->lastResponseCode == 200) {
            $result = $invoices;
        }
        unset($this->invoicer->defaultUrlParams['includes']);
        return $result;
    }

    /**
     * Vrací nesparovane platby odpovídající zadaným parametrům
     *
     * @param array $what
     * @return array
     */
    public function findPayment($what)
    {
        $result = null;
        $this->banker->defaultUrlParams['order'] = 'datVyst@A';
        $payments = $this->banker->getColumnsFromAbraFlexi(
            [
                    'id',
                    'varSym',
                    'specSym',
                    'buc',
                    'sumCelkem',
                    'mena',
                    'stitky',
                    'datVyst'],
            ["(" . \AbraFlexi\RO::flexiUrl($what, 'or') . ") AND sparovano eq 'false'"],
            'id'
        );
        if ($this->banker->lastResponseCode == 200) {
            $result = $payments;
        }

        return $result;
    }

    /**
     * Najde nejlepší platbu pro danou fakturu
     *
     * @param array $payments pole příchozích plateb
     * @param FakturaVydana $invoice  faktura ke spárování
     *
     * @return \AbraFlexi\Banka Bankovní pohyb
     */
    public function findBestPayment($payments, $invoice)
    {
        $value = $invoice->getDataValue('sumCelkem');
        foreach ($payments as $paymentID => $payment) {
            if ($payment['sumCelkem'] == $value) {
                return new \AbraFlexi\Banka(
                    \AbraFlexi\RO::code($payments[$paymentID]['kod']),
                    $this->config
                );
            }
        }

        $symbol = $invoice->getDataValue('specSym');
        $this->addStatusMessage(sprintf(
            _('Platba pro fakturu %s nebyla dohledána'),
            self::apiUrlToLink($invoice->apiURL)
        ), 'warning');
        return null;
    }

    /**
     * Change url to html link
     *
     * @param string $apiURL
     *
     * @return string
     */
    public static function apiUrlToLink($apiURL)
    {
        return str_replace(
            '.json?limit=0',
            '',
            preg_replace(
                "#(^|[\n ])([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is",
                "\\1<a href=\"\\2\" target=\"_blank\" rel=\"nofollow\">\\2</a>",
                $apiURL
            )
        );
    }

    /**
     * Return Document original type
     *
     * @param string $typDokl
     *
     * @return string typDokladu.faktura|typDokladu.dobropis|
     *                typDokladu.zalohFaktura|typDokladu.zdd|
     *                typDokladu.dodList|typDokladu.proforma|
     *                typBanUctu.kc|typBanUctu.mena
     */
    public function getOriginDocumentType($typDokl)
    {
        if (empty($this->docTypes)) {
            $this->docTypes = $this->getDocumentTypes();
        }
        $documentType = \AbraFlexi\RO::uncode($typDokl);
        return array_key_exists($documentType, $this->docTypes) ? $this->docTypes[$documentType] : 'typDokladu.neznamy';
    }

    /**
     * Assign Bank Account to Address
     *
     * @param \AbraFlexi\Adresar|string $payer    Object or code: identier
     * @param \AbraFlexi\Banka          $payment  Payment object
     *
     * @return boolean account was assigned to Address
     */
    public function savePayerAccount($payer, $payment)
    {
        $result = null;
        $buc = $payment->getDataValue('buc');
        if (
            !empty($buc) && !empty($payer) && self::isKnownBankAccountForAddress(
                $payer,
                $buc
            )
        ) {
            $result = $this->assignBankAccountToAddress($payer, $payment);
        }
        return $result;
    }

    /**
     *
     * @param \AbraFlexi\Adresar $address
     * @param string $buc
     *
     * @return boolean
     */
    public static function isKnownBankAccountForAddress($address, $buc)
    {
        $result = null;
        $accounts = [];
        $bucer = new \AbraFlexi\RW(null, ['evidence' => 'adresar-bankovni-ucet']);
        $accountsRaw = $bucer->getColumnsFromAbraFlexi(
            ['buc', 'smerKod'],
            ['firma' => $address]
        );
        if (!empty($accountsRaw)) {
            $accounts = \Ease\Functions::reindexArrayBy($accountsRaw, 'buc');
        }
        return !array_key_exists($buc, $accounts);
    }

    /**
     *
     * @param string $buc
     *
     * @return string Address code
     */
    public function bucToAddress($buc)
    {
        $bucer = new \AbraFlexi\RW(null, ['evidence' => 'adresar-bankovni-ucet']);
        $accountsRaw = $bucer->getColumnsFromAbraFlexi(
            ['firma'],
            ['buc' => $buc]
        );
        if (count($accountsRaw) > 1) {
            $this->addStatusMessage($buc . ' is assigned to multiple companies ', 'event');
            $accountsRaw = [];
        }
        return empty($accountsRaw) ? '' : $accountsRaw[0]['firma'];
    }

    /**
     * Assign Bank Account to Address
     *
     * @param \AbraFlexi\Adresar|string $address Object or code: identier
     * @param \AbraFlexi\Banka                   $payment
     *
     * @return boolean added ?
     */
    public static function assignBankAccountToAddress($address, $payment)
    {
        $bucer = new \AbraFlexi\RW(null, ['evidence' => 'adresar-bankovni-ucet']);
        $bucer->insertToAbraFlexi(['firma' => $address, 'buc' => $payment->getDataValue('buc'),
            'smerKod' => $payment->getDataValue('smerKod'), 'poznam' => _('Added by script')]);
        return $bucer->lastResponseCode == 201;
    }

    /**
     *
     * @param array $paymentData
     * @param int   $invoiceId
     */
    public function vytvorVazbuZDD($paymentData, $invoiceId)
    {
        $modul = 'banka'; //pokladna

        $this->banker->setData($paymentData);
        $this->banker->ignore404(true);
        if ($this->banker->lastResponseCode == 200) {
            $headersBackup = $this->defaultHttpHeaders;
            $bankID = $this->banker->getDataValue('id');
            $this->defaultHttpHeaders['Accept'] = 'text/html';
            $this->setPostFields(http_build_query(['modul' => $modul, 'submit' => 'OK']));
            $this->performRequest(
                $invoiceId . '/vytvor-vazbu-zdd/' . $bankID,
                'GET',
                'json'
            );
            $responseArr = explode("\n", $this->lastCurlResponse);
            $result = true;
            $message = '';
            foreach ($responseArr as $lineNo => $responseLine) {
                if (strstr($responseLine, '<ul class = "abraflexi-errors">')) {
                    $message = trim($responseArr[$lineNo + 1]);
                    $result = false;
                }
                if (strstr($responseLine, '<div class = "alert alert-success">')) {
                    $message = strip_tags(html_entity_decode(trim($responseArr[$lineNo + 1])));
                    $result = true;
                }
            }

            if ($result === true) {
                $this->addStatusMessage(empty($message) ? $this->getDataValue('kod') . '/vytvor-vazbu-zdd/' . $bankID : $message, 'success');
            } else {
                $this->addStatusMessage($this->getDataValue('kod') . '/vytvor-vazbu-zdd/' . $bankID, 'warning');
            }

            $this->defaultHttpHeaders = $headersBackup;
        }
    }

    /**
     * Reindex array of Invoice Data by datVyst or another column with date
     *
     * @param array $invoices
     *
     * @return array
     */
    public static function reindexInvoicesByDate($invoices, $sortBy = 'datVyst')
    {
        return \Ease\Functions::reindexArrayBy($invoices, $indexBy);
    }

    /**
     * Document types of Invoices
     *
     * @return array
     */
    public function getDocumentTypes()
    {
        return $this->invoicer->getColumnsFromAbraFlexi(['kod'], ['evidence' => 'typ-faktury-vydane'], 'kod');
    }

    /**
     * Match invoices for given payment
     *
     * @param \AbraFlexi\Banka $payment
     *
     * @return int
     */
    public function matchingByBank($payment = null)
    {
        $success = 0;
        $varSym = $this->banker->getDataValue('varSym');
        if ($varSym) {
            $invoices = $this->findInvoices(['varSym' => $varSym]);
            foreach ($invoices as $invoice) {
                $success += $this->settleInvoice(new \AbraFlexi\FakturaVydana($invoice), $payment ? $payment : $this->banker);
            }
        }
        return $success;
    }
}
