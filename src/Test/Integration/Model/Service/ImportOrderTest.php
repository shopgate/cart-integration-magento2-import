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

namespace Shopgate\Import\Test\Integration\Model\Service;

use Shopgate\Base\Tests\Bootstrap;
use Shopgate\Base\Tests\Integration\SgDataManager;

/**
 * @coversDefaultClass Shopgate\Import\Model\Service\Import
 */
class ImportOrderTest extends \PHPUnit\Framework\TestCase
{
    /** @var \Shopgate\Import\Helper\Order */
    protected $orderClass;
    /** @var \Shopgate\Import\Model\Service\Import */
    protected $importClass;
    /** @var array - list of created orders to clean up */
    protected $orderHolder = [];
    /** @var \Magento\Sales\Api\OrderRepositoryInterface */
    protected $orderRepository;
    /** @var SgDataManager */
    protected $dataManager;

    public function setUp()
    {
        $objectManager         = Bootstrap::getObjectManager();
        $this->importClass     = $objectManager->create('Shopgate\Import\Model\Service\Import');
        $this->orderClass      = $objectManager->create('Shopgate\Import\Helper\Order');
        $this->orderRepository = $objectManager->create('Magento\Sales\Api\OrderRepositoryInterface');
    }

    /**
     * Test that all 3 product types get inserted
     * into the order and that order is created
     * correctly
     *
     * @param \ShopgateOrder $order
     *
     * @dataProvider simpleOrderProvider
     * @throws \ShopgateLibraryException
     */
    public function testOrderImport(\ShopgateOrder $order)
    {
        $result = $this->importClass->addOrder($order);
        /** @var \Shopgate\Import\Helper\Order $sgOrder */
        $sgOrder = Bootstrap::getObjectManager()->get('Shopgate\Import\Helper\Order');
        /** @var \Magento\Sales\Model\Order $order */
        $order = $sgOrder->loadMethods([]);

        $this->assertNotEmpty($result);
        $this->assertCount(3, $order->getAllVisibleItems());
        $this->orderHolder[] = $result;
    }

    /**
     * @return array
     */
    public function simpleOrderProvider()
    {
        $dataManager = new SgDataManager();

        return [
            'simple order' => [
                new \ShopgateOrder(
                    [
                        'order_number'        => rand(1000000000, 9999999999),
                        'is_paid'             => 0,
                        'mail'                => 'shopgate@shopgate.com',
                        'amount_shop_payment' => '5.00',
                        'amount_complete'     => '149.85',
                        'shipping_infos'      => [
                            'amount' => '4.90',
                        ],
                        'invoice_address'     => $dataManager->getGermanAddress(),
                        'delivery_address'    => $dataManager->getGermanAddress(false),
                        'external_coupons'    => [],
                        'shopgate_coupons'    => [],
                        'items'               => [
                            $dataManager->getSimpleProduct(),
                            $dataManager->getConfigurableProduct(),
                            $dataManager->getGroupedProduct()
                        ]
                    ]
                )
            ],
        ];
    }

    /**
     * Delete all created orders & quotes
     */
    public function tearDown()
    {
        /** @var \Magento\Framework\Registry $registry */
        $registry = Bootstrap::getObjectManager()->get('\Magento\Framework\Registry');
        $registry->register('isSecureArea', true, true);
        /** @var \Magento\Quote\Model\QuoteRepository $quoteRepo */
        $quoteRepo = Bootstrap::getObjectManager()->create('Magento\Quote\Model\QuoteRepository');

        foreach ($this->orderHolder as $order) {
            if (isset($order['external_order_id'])) {
                $order = $this->orderRepository->get($order['external_order_id']);
                $quoteRepo->delete($quoteRepo->get($order->getQuoteId()));
                $this->orderRepository->delete($order);
            }
        }
    }
}
