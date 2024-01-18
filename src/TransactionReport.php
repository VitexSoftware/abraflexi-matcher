<?php

/**
 * AbraFlexi Transaction Reporter.
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.com>
 * @copyright  (C) 2024 VitexSoftware
 */

namespace SpojeNet\FioApi;

use Ease\Shared;

require_once('../vendor/autoload.php');

define('APP_NAME', 'AbraFlexi Transaction Reporter');

Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'ABRAFLEXI_BANK'], array_key_exists(3, $argv) ? $argv[3] : '../.env');
$banker = new \AbraFlexi\Matcher\BankProbe();

if (\Ease\Shared::cfg('APP_DEBUG', false)) {
    $banker->logBanner();
}
$banker->setScope('last_month');

$payments = [
    'source' => \Ease\Logger\Message::getCallerName($banker),
    'account' => $banker->accuntNumber(),
    'in' => [],
    'out' => [],
    'in_total' => 0,
    'out_total' => 0,
    'in_sum_total' => 0,
    'out_sum_total' => 0,
    'iban' => $banker->getIban(),
    'from' => $banker->getSince()->format('Y-m-d'),
    'to' => $banker->getUntil()->format('Y-m-d')
];

$transactionList = $banker->transactionsFromTo();
if (empty($transactionList) === false) {
    foreach ($transactionList as $transaction) {
        $direction = ($transaction['typPohybuK'] == 'typPohybu.prijem');
        $payments[$direction ? 'in' : 'out'][$transaction['id']] = $transaction['sumCelkem'];
        $payments[$direction ? 'in_sum_total' : 'out_sum_total'] += $transaction['sumCelkem'];
        $payments[$direction ? 'in_total' : 'out_total'] += 1;
    }

    echo json_encode($payments, \Ease\Shared::cfg('DEBUG') ? JSON_PRETTY_PRINT : 0);
} else {
    echo "no statements returned\n";
}
