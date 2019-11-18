<?php

/**
 * Copyright Shopgate Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author    Shopgate Inc, 804 Congress Ave, Austin, Texas 78701 <interfaces@shopgate.com>
 * @copyright Shopgate Inc
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

namespace Shopgate\Import\Helper\Payment;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Braintree\Model\InstantPurchase\CreditCard\TokenFormatter;

class Braintree
{
    /**
     * @param string $expirationYear
     * @param string $expirationMonth
     *
     * @return string
     * @throws Exception
     */
    public function calculateExpirationDate(int $expirationYear, int $expirationMonth): string
    {
        $expDate = new DateTime(
            $expirationYear
            . '-'
            . $expirationMonth
            . '-'
            . '01'
            . ' '
            . '00:00:00',
            new DateTimeZone('UTC')
        );
        $expDate->add(new DateInterval('P1M'));

        return $expDate->format('Y-m-d 00:00:00');
    }

    /**
     * @param int $expirationMonth
     *
     * @return string
     */
    public function formatExpirationMonth(int $expirationMonth): string
    {
        return sprintf('%02d', $expirationMonth);
    }

    /**
     * @param int $expirationMonth
     * @param int $expirationYear
     *
     * @return string
     */
    public function formatExpirationDate(int $expirationMonth, int $expirationYear): string
    {
        return sprintf('%s/%s', $this->formatExpirationMonth($expirationMonth), $expirationYear);
    }

    /**
     * @param string $ccType
     *
     * @return string
     */
    public function formatVisibleCCType(string $ccType): string
    {
        return TokenFormatter::$baseCardTypes[$ccType] ?? $ccType;
    }

    /**
     * @param string $ccNumber
     *
     * @return string
     */
    public function formatVisibleCCNumber(string $ccNumber): string
    {
        return sprintf('xxxx-%s', str_replace('*', '', $ccNumber));
    }

    /**
     * @param string $ccNumber
     *
     * @return string
     */
    public function getLastCCNumbers(string $ccNumber): string
    {
        return str_replace('*', '', $ccNumber);
    }
}
