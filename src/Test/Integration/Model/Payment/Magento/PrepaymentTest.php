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

namespace Shopgate\Import\Test\Integration\Model\Payment\Shopgate;

use Exception;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order as MagentoOrder;
use Shopgate\Import\Test\Integration\Model\Payment\BaseTest;
use ShopgateLibraryException;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation  enabled
 * @magentoAppArea      frontend
 */
class PrepaymentTest extends BaseTest
{
    /** @var array */
    protected const ORDER_CONFIG = [
        'payment_method' => 'PREPAY',
        'payment_group'  => 'PREPAY',
    ];

    /**
     * @magentoConfigFixture current_store payment/banktransfer/active 1
     *
     * @param int $isPaid
     *
     * @throws Exception
     * @throws ShopgateLibraryException
     * @throws InputException
     * @throws NoSuchEntityException
     *
     * @dataProvider         paidFlagProvider
     */
    public function testPaymentMappingOnImport($isPaid): void
    {
        $result = $this->importClass->addOrder($this->getShopgateOrder($isPaid, static::ORDER_CONFIG));
        /** @var MagentoOrder $magentoOrder */
        $magentoOrder = $this->orderRepository->get($result['external_order_id']);

        $this->assertEquals('banktransfer', $magentoOrder->getPayment()->getMethod());
        $this->assertEquals('pending', $magentoOrder->getStatus());
    }

    /**
     * @magentoConfigFixture current_store payment/banktransfer/active 0
     *
     * @throws Exception
     * @throws ShopgateLibraryException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function testInactivePaymentMappingOnImport(): void
    {
        $result = $this->importClass->addOrder($this->getShopgateOrder(0, static::ORDER_CONFIG));
        /** @var MagentoOrder $magentoOrder */
        $magentoOrder = $this->orderRepository->get($result['external_order_id']);

        $this->assertEquals('shopgate', $magentoOrder->getPayment()->getMethod());
        $this->assertEquals('pending', $magentoOrder->getStatus());
    }

    /**
     * @return array
     */
    public function paidFlagProvider(): array
    {
        return [
            'Paid order'   => [1],
            'Unpaid order' => [0],
        ];
    }
}
