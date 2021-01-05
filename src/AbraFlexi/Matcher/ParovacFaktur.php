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
     * Requied Config Keys
     * @var array 
     */
    public $cfgRequed = ["MATCHER_LABEL_PREPLATEK", "MATCHER_LABEL_CHYBIFAKTURA", "MATCHER_LABEL_NEIDENTIFIKOVANO"];
}
