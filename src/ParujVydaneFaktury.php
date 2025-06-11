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

use AbraFlexi\Matcher\OutgoingInvoice;
use Ease\Locale;
use Ease\Shared;

\define('APP_NAME', 'AbraFlexi ParujVydaneFaktury');

require_once '../vendor/autoload.php';
$shared = Shared::singleton();

new Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

/**
 * Get today's Statements list.
 */
$options = getopt('o::e::', ['output::environment::']);
Shared::init(     
    ['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'],
    \array_key_exists('environment', $options) ? $options['environment'] : (\array_key_exists('e', $options) ? $options['e'] : '../.env'),
);
$destination = \array_key_exists('o', $options) ? $options['o'] : (\array_key_exists('output', $options) ? $options['output'] : \Ease\Shared::cfg('RESULT_FILE', 'php://stdout'));


$invoiceSteamer = new OutgoingInvoice($shared->configuration);
$invoiceSteamer->setStartDay(30);

if (Shared::cfg('APP_DEBUG')) {
    $beginDate = $invoiceSteamer->getStartingDay();
    $daysBefore = (new \DateTime())->diff($beginDate)->days;
    $endDate = (new \DateTime());

    $rangeInfo = sprintf(
        _('Incoming payments between %s and %s (%d days)'),
        $beginDate->format('Y-m-d'),
        $endDate->format('Y-m-d'),
        $daysBefore
    );
    $invoiceSteamer->banker->logBanner(Shared::appName().' '.$rangeInfo);
}

if ($shared->getConfigValue('MATCHER_PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'), 'debug');

    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching begin'), 'debug');
$result = $invoiceSteamer->issuedInvoicesMatchingByBank();
$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching done'), 'debug');

$report['matched'] = $result['matched'];
$report['unmatched'] = $result['unmatched'];
$exitcode = 0;
$written = file_put_contents($destination, json_encode($report, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$invoiceSteamer->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
