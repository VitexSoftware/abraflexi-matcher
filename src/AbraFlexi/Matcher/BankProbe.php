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

namespace AbraFlexi\Matcher;

/**
 * Description of BankProbe.
 *
 * @author vitex
 */
class BankProbe extends \AbraFlexi\Banka
{
    public $account;
    public $iban;
    protected \DateTime $since;
    protected \DateTime $until;
    private $accounter;

    public function __construct($init = null, $options = [])
    {
        parent::__construct($init, $options);
        $this->accounter = new \AbraFlexi\RO(\AbraFlexi\Functions::code(\Ease\Shared::cfg('ABRAFLEXI_BANK')), ['evidence' => 'bankovni-ucet']);
        $this->account = $this->accounter->getDataValue('buc');
        $this->iban = $this->accounter->getDataValue('iban');
    }

    /**
     * Prepare processing interval.
     *
     * @param string $scope
     *
     * @throws \Exception
     */
    public function setScope($scope): void
    {
        switch ($scope) {
            case 'yesterday':
                $this->since = (new \DateTime('yesterday'))->setTime(0, 0);
                $this->until = (new \DateTime('yesterday'))->setTime(23, 59);

                break;
            case 'last_week':
                $this->since = new \DateTime('first day of last week');
                $this->until = new \DateTime('last day of last week');

                break;
            case 'current_month':
                $this->since = new \DateTime('first day of this month');
                $this->until = new \DateTime();

                break;
            case 'last_month':
                $this->since = new \DateTime('first day of last month');
                $this->until = new \DateTime('last day of last month');

                break;
            case 'last_two_months':
                $this->since = (new \DateTime('first day of last month'))->modify('-1 month');
                $this->until = (new \DateTime('last day of last month'));

                break;
            case 'previous_month':
                $this->since = new \DateTime('first day of -2 month');
                $this->until = new \DateTime('last day of -2 month');

                break;
            case 'two_months_ago':
                $this->since = new \DateTime('first day of -3 month');
                $this->until = new \DateTime('last day of -3 month');

                break;
            case 'this_year':
                $this->since = new \DateTime('first day of January '.date('Y'));
                $this->until = new \DateTime('last day of December'.date('Y'));

                break;
            case 'January':  // 1
            case 'February': // 2
            case 'March':    // 3
            case 'April':    // 4
            case 'May':      // 5
            case 'June':     // 6
            case 'July':     // 7
            case 'August':   // 8
            case 'September':// 9
            case 'October':  // 10
            case 'November': // 11
            case 'December': // 12
                $this->since = new \DateTime('first day of '.$scope.' '.date('Y'));
                $this->until = new \DateTime('last day of '.$scope.' '.date('Y'));

                break;

            default:
                throw new \Exception('Unknown scope '.$scope);
        }

        $this->since = $this->since->setTime(0, 0);
        $this->until = $this->until->setTime(23, 59, 59, 999999);
    }

    public function getUntil()
    {
        return $this->until;
    }

    public function getSince()
    {
        return $this->since;
    }

    public function accuntNumber()
    {
        return $this->account;
    }

    public function getIban()
    {
        return $this->iban;
    }

    public function transactionsFromTo()
    {
        $conds = ['limit' => 0, 'banka' => $this->accounter];

        if ($this->getSince()->format('Y-m-d') === $this->getUntil()->format('Y-m-d')) {
            $conds[] = 'datVyst eq '.$this->getSince()->format(\AbraFlexi\Functions::$DateFormat);
        } else {
            $conds[] = 'datVyst gt '.$this->getSince()->format(\AbraFlexi\Functions::$DateFormat);
            $conds[] = 'datVyst lt '.$this->getUntil()->format(\AbraFlexi\Functions::$DateFormat);
        }

        return $this->getColumnsFromAbraFlexi(['typPohybuK', 'id', 'sumCelkem'], $conds);
    }
}
