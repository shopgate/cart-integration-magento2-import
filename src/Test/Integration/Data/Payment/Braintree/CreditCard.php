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

declare(strict_types=1);

namespace Shopgate\Import\Test\Integration\Data\Payment\Braintree;

class CreditCard
{
    public const TRANSACTION_ID = '7mwm7q4a';

    /**
     * Outsourced into a function for re-use
     *
     * @return array
     */
    public static function getAdditionalPayment(): array
    {
        return [
            'shopgate_payment_name'   => 'Credit card (Braintree)',
            'status'                  => 'authorized',
            'transaction_id'          => self::TRANSACTION_ID,
            'transaction_type'        => 'sale',
            'processor_auth_code'     => '02880Q',
            'processor_response_code' => '1000',
            'processor_response_text' => 'Approved',
            'risk_data'               => [],
            'credit_card'             => [
                'holder'        => 'Testi Test',
                'masked_number' => '************9596',
                'type'          => 'mastercard',
                'expiry_year'   => 2021,
                'expiry_month'  => 2,
            ]
        ];
    }
}
