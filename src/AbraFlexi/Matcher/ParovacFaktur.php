<?php

/**
 * Invoice Matcher wrapper
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright (c) 2018-2022, Vítězslav Dvořák
 */

namespace AbraFlexi\Matcher;

/**
 * Description of ParovacFaktur
 *
 * @author vitex
 */
class ParovacFaktur extends \AbraFlexi\Bricks\ParovacFaktur {

    /**
     * 
     * @param array $configuration
     */
    public function __construct($configuration = []) {
        
        $configuration['LABEL_OVERPAY'] = \Ease\Functions::cfg('MATCHER_LABEL_PREPLATEK');
        $configuration['LABEL_INVOICE_MISSING'] = \Ease\Functions::cfg('MATCHER_LABEL_CHYBIFAKTURA');
        $configuration['LABEL_UNIDENTIFIED'] = \Ease\Functions::cfg('MATCHER_LABEL_NEIDENTIFIKOVANO');
        parent::__construct($configuration);
    }
    
}
