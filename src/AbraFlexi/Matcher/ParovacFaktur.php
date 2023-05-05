<?php

/**
 * Invoice Matcher wrapper
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright (c) 2018-2023, Vítězslav Dvořák
 */

namespace AbraFlexi\Matcher;

/**
 * Description of ParovacFaktur
 *
 * @author vitex
 */
class ParovacFaktur extends \AbraFlexi\Bricks\ParovacFaktur
{

    /**
     * 
     * @param array $configuration
     */
    public function __construct($configuration = [])
    {

        $configuration['LABEL_OVERPAY'] = \Ease\Functions::cfg('MATCHER_LABEL_PREPLATEK', 'PREPLATEK');
        $configuration['LABEL_INVOICE_MISSING'] = \Ease\Functions::cfg('MATCHER_LABEL_CHYBIFAKTURA', 'CHYBIFAKTURA');
        $configuration['LABEL_UNIDENTIFIED'] = \Ease\Functions::cfg('MATCHER_LABEL_NEIDENTIFIKOVANO', 'NEIDENTIFIKOVANO');
        parent::__construct($configuration);
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
