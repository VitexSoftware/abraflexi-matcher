<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
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
