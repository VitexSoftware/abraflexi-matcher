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

use AbraFlexi\Matcher\IncomingInvoice;
use Ease\Shared;

\define('APP_NAME', 'AbraFlexi MatchRecievedPaymentsByVarSym');

require_once '../vendor/autoload.php';
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], \array_key_exists(1, $argv) ? $argv[1] : '../.env');
new \Ease\Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');
$invoiceSteamer = new IncomingInvoice();

if (Shared::cfg('APP_DEBUG')) {
    $invoiceSteamer->banker->logBanner();
}

if (Shared::cfg('MATCHER_PULL_BANK', false)) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'), 'debug');

    try {
        if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
            $invoiceSteamer->addStatusMessage(_('Bank Offline!'), 'error');
        }
    } catch (\Exception $exc) {
        $invoiceSteamer->addStatusMessage($exc->getMessage(), 'error');
    }
}

$begin = new DateTime();
$daterange = new DatePeriod(
    $begin->modify('-'.Shared::cfg('MATCHER_DAYS_BACK', 365).' days'),
    new DateInterval('P1D'),
    new DateTime(),
);
$invoiceSteamer->addStatusMessage(_('Variable symbol based matching begin'), 'debug');

$paymentsRaw = $invoiceSteamer->findPayment(['varSym' => 'is not empty', 'datVyst' => $daterange]);

$matchingResults = [];

foreach ($paymentsRaw as $paymentData) {
    $payment = new \AbraFlexi\Banka($paymentData);
    $invoicesRaw = $invoiceSteamer->findInvoice(['varSym' => $payment->getDataValue('varSym')]);

    if (empty($invoicesRaw)) {
        $invoiceSteamer->addStatusMessage('no invoice found for varSym: '.$payment->getDataValue('varSym'));
        $matchingResults['noInvoice'][] = $payment->getRecordCode();
    } elseif (\count($invoices) === 1) {
        // Match payment
        $invoice = new \AbraFlexi\FakturaVydana(current($invoices));

        switch ($docType) {
            case 'typDokladu.zalohFaktura':
            case 'typDokladu.faktura':
                if ($invoiceSteamer->settleInvoice($invoice, $payment)) {
                    $matchingResults['settled'][] = [$invoice->getRecordCode() => $payment->getRecordCode()];
                }

                break;
            case 'typDokladu.proforma':
                if ($invoiceSteamer->settleProforma($invoice, $payment)) {
                    $matchingResults['settled'][] = [$invoice->getRecordCode() => $payment->getRecordCode()];
                }

                break;
            case 'typDokladu.dobropis':
                $invoiceSteamer->settleCreditNote($invoice, $payment);

                break;

            default:
                $matchingResults['unsupported'][] = [$invoice->getRecordCode() => $payment->getRecordCode()];
                $invoiceSteamer->addStatusMessage(
                    sprintf(
                        _('Unsupported document type: %s %s'),
                        $typDokl['typDoklK']->showAs.' ('.$docType.'): '.$invoiceData['typDokl'],
                        $invoice->getApiURL(),
                    ),
                    'warning',
                );

                break;
        }
    } else {
        // Multiple Invoices found
        $invoiceSteamer->addStatusMessage('Multiple invoices found for varSym: '.$payment['varSym']);

        foreach ($invoices as $invoice) {
            $matchingResults['multiple'][] = [$invoice['kod'] => $payment->getRecordCode()];
        }
    }
}

$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching done'), 'debug');

$exitcode = 0;

// Build the report according to the schema
$report = [
    'producer' => APP_NAME,
    'status' => $exitcode === 0 ? 'success' : 'error',
    'timestamp' => (new DateTime())->format(DateTime::ATOM),
    'message' => _('Variable symbol based matching completed'),
    'artifacts' => [
        'result' => [$destination],
    ],
    'metrics' => [
        'settled' => \count($matchingResults['settled'] ?? []),
        'noInvoice' => \count($matchingResults['noInvoice'] ?? []),
        'multiple' => \count($matchingResults['multiple'] ?? []),
        'unsupported' => \count($matchingResults['unsupported'] ?? []),
    ],
];

$destination = \Ease\Shared::cfg('RESULT_FILE', 'php://stdout');
$written = file_put_contents($destination, json_encode($report, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$invoiceSteamer->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
