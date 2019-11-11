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

namespace Shopgate\Import\Tests\Integration\Model\Payment;

use Magento\Sales\Model\Order as MagentoOrder;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Shopgate\Base\Model\Shopgate\Extended\Base as ShopgateOrder;
use Shopgate\Import\Model\Payment\Factory as PaymentFactory;

class FactoryTest extends TestCase
{
    /** @var ObjectManager $objectManager */
    private $objectManager;
    /** @var PaymentFactory */
    private $paymentFactory;
    /** @var MagentoOrder */
    private $magentoOrder;
    /** @var ShopgateOrder */
    private $shopgateOrder;

    /**
     * Integration test preparation, creating mocks for Shopgate and Magento orders
     */
    public function setUp()
    {
        $this->objectManager  = Bootstrap::getObjectManager();
        $this->paymentFactory = $this->objectManager->create(PaymentFactory::class);
        $this->shopgateOrder  = $this->getMockBuilder(ShopgateOrder::class)->getMock();
        $this->magentoOrder   = $this->getMockBuilder(MagentoOrder::class)->getMock();
    }

    /**
     * @param $methodCode
     * @param $expectedPaymentMethod
     *
     * @dataProvider paymentMethodProvider
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testPaymentMethodMapping($methodCode, $expectedPaymentMethod): void
    {
        $paymentMethod = $this->paymentFactory->getPayment($methodCode, $this->magentoOrder, $this->shopgateOrder);

        $this->assertEquals($expectedPaymentMethod, $paymentMethod->getPaymentModel()->getCode());
    }

    /**
     * Data provider for payment mapping integration test
     *
     * @return array
     */
    public function paymentMethodProvider(): array
    {
        return [
            'return shopgate as default payment method' => ['PREPAY', 'shopgate']
        ];
    }
}
