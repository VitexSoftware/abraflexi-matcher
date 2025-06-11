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

use Ease\Locale;
use Ease\Shared;

\define('APP_NAME', 'AbraFlexi ParujPrijatouBanku');

require_once '../vendor/autoload.php';
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], \array_key_exists(1, $argv) ? $argv[1] : '../.env');
new Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

if ($argc > 1) {
    $docId = $argv[1];
} else {
    $docId = \Ease\Shared::cfg('DOCUMENTID');
}

$invoiceSteamer = new \AbraFlexi\Matcher\ParovacFaktur();

$report = ['matched' => [], 'unmatched' => []];
$exitcode = 0;
$destination = \Ease\Shared::cfg('RESULT_FILE', 'php://stdout');
if ($docId) {
    $invoiceSteamer->banker->loadFromAbraFlexi($docId);

    if (Shared::cfg('APP_DEBUG')) {
        $invoiceSteamer->banker->logBanner(Shared::appName());
        $invoiceSteamer->addStatusMessage(_('Incoming bank matching begin'), 'debug');
    }

    $matched = [];
    $unmatched = [];
    $result = $invoiceSteamer->matchingByBank();
    if ($result) {
        // Try to get invoice code from banker or related object if possible
        $matched[] = $invoiceSteamer->banker->getDataValue('kod') ?? $docId;
    } else {
        $unmatched[] = $invoiceSteamer->banker->getDataValue('kod') ?? $docId;
    }
    $report['matched'] = $matched;
    $report['unmatched'] = $unmatched;
    $invoiceSteamer->addStatusMessage(sprintf(_('Payment %s matching'), $docId), $result ? 'success' : 'warning');

    if (Shared::cfg('APP_DEBUG')) {
        $invoiceSteamer->addStatusMessage(_('Incomin bank matching done'), 'debug');
    }
} else {
    $invoiceSteamer->addStatusMessage(_('No DOCUMENTID provided. aborting'), 'error');
}
$written = file_put_contents($destination, json_encode($report, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$invoiceSteamer->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');
exit($exitcode);
