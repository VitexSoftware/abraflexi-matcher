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

namespace SpojeNet\FioApi;

require_once '../vendor/autoload.php';

\define('APP_NAME', 'AbraFlexi Transaction Reporter');

$options = getopt('o::e::', ['output::environment::']);

\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'ABRAFLEXI_BANK'], \array_key_exists('environment', $options) ? $options['environment'] : '../.env');
$localer = new \Ease\Locale('cs_CZ', '../i18n', 'abraflexi-reminder');

$banker = new \AbraFlexi\Matcher\BankProbe();

$destination = \array_key_exists('output', $options) ? $options['output'] : \Ease\Shared::cfg('RESULT_FILE', 'php://stdout');

if (strtolower(\Ease\Shared::cfg('APP_DEBUG', 'false')) === 'true') {
    $banker->logBanner(\Ease\Shared::appName() . ' v' . \Ease\Shared::appVersion());
}

$banker->setScope(\Ease\Shared::cfg('REPORT_SCOPE', 'yesterday'));

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
    'to' => $banker->getUntil()->format('Y-m-d'),
];

$transactionList = $banker->transactionsFromTo();

foreach ($transactionList as $transaction) {
    $direction = ($transaction['typPohybuK'] === 'typPohybu.prijem');
    $payments[$direction ? 'in' : 'out'][$transaction['id']] = $transaction['sumCelkem'];
    $payments[$direction ? 'in_sum_total' : 'out_sum_total'] += $transaction['sumCelkem'];
    ++$payments[$direction ? 'in_total' : 'out_total'];
}

$written = file_put_contents($destination, json_encode($payments, \Ease\Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT : 0));
$banker->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($written ? 0 : 1);
