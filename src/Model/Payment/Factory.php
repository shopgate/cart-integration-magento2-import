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

namespace Shopgate\Import\Model\Payment;

use Shopgate\Import\Model\Payment\AbstractPayment;

class Factory
{
    const DEFAULT_PAYMENT_METHOD = 'SHOPGATE';

    /** @var array */
    private $paymentMapping;
    /** @var AbstractPayment */
    private $paymentMethod;

    /**
     * @param array $paymentMapping - methods loaded via di.xml
     */
    public function __construct($paymentMapping = [])
    {
        $this->paymentMapping = $paymentMapping;
    }

    /**
     * @param string $paymentMethod
     *
     * @return AbstractPayment
     */
    public function getPayment(string $paymentMethod): AbstractPayment
    {
        if (!$this->paymentMethod) {
            $methodToLoad        = $this->paymentMapping[$paymentMethod] ?? self::DEFAULT_PAYMENT_METHOD;
            $this->paymentMethod = $this->paymentMapping[strtoupper($methodToLoad)]->create();
        }

        if (!$this->paymentMethod->isValid()) {
            $this->paymentMethod = $this->paymentMapping[self::DEFAULT_PAYMENT_METHOD]->create();
        }

        return $this->paymentMethod;
    }
}
