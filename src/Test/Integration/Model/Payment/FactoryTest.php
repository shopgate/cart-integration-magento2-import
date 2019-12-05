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

namespace Shopgate\Import\Test\Integration\Model\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Shopgate\Import\Model\Payment\Factory as PaymentFactory;
use ShopgateLibraryException;

/**
 * @magentoDbIsolation enabled
 */
class FactoryTest extends TestCase
{
    /** @var ObjectManager $objectManager */
    private $objectManager;
    /** @var PaymentFactory */
    private $paymentFactory;

    /**
     * Integration test preparation
     */
    public function setUp()
    {
        $this->objectManager  = Bootstrap::getObjectManager();
        $this->paymentFactory = $this->objectManager->create(PaymentFactory::class);
    }

    /**
     * @param string $methodCode
     * @param string $expectedPaymentMethod
     *
     * @dataProvider         paymentMethodProvider
     *
     * @throws LocalizedException
     * @throws ShopgateLibraryException
     *
     * @magentoConfigFixture current_store payment/braintree/active 1
     * @magentoConfigFixture current_store payment/braintree_paypal/active 1
     */
    public function testPaymentMethodMapping($methodCode, $expectedPaymentMethod): void
    {
        $paymentMethod = $this->paymentFactory->getPayment($methodCode);

        $this->assertEquals($expectedPaymentMethod, $paymentMethod->getPaymentModel()->getCode());
    }

    /**
     * Data provider for payment mapping integration test
     *
     * @return string[]
     */
    public function paymentMethodProvider(): array
    {
        return [
            'return shopgate as default payment method' => ['SOMETHING', 'shopgate'],
            'Bank Transfer'                             => ['PREPAY', 'banktransfer'],
            'Cash on delivery'                          => ['COD', 'cashondelivery'],
            'Invoice'                                   => ['INVOICE', 'checkmo'],
            'Braintree Credit Card'                     => ['BRAINTR_CC', 'braintree'],
            'Braintree PayPal' => ['BRAINTR_PP', 'braintree_paypal']
        ];
    }
}
