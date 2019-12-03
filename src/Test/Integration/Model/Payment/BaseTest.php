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

use Exception;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\OrderRepository;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Shopgate\Base\Tests\Integration\SgDataManager;
use Shopgate\Import\Model\Service\Import;
use ShopgateLibraryException;
use ShopgateOrder;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation  enabled
 * @magentoAppArea      frontend
 */
class BaseTest extends TestCase {

    /** @var ObjectManager $objectManager */
    protected $objectManager;
    /** @var Import */
    protected $importClass;
    /** @var OrderRepository */
    protected $orderRepository;
    /** @var SgDataManager */
    protected $dataManager;

    /**
     * Integration test preparation
     */
    public function setUp()
    {
        $this->objectManager   = Bootstrap::getObjectManager();
        $this->importClass     = $this->objectManager->create(Import::class);
        $this->orderRepository = $this->objectManager->create(OrderRepository::class);
        $this->dataManager     = $this->objectManager->create(SgDataManager::class);
    }

    /**
     * @param int   $isPaid
     * @param array $config
     *
     * @return ShopgateOrder
     *
     * @throws Exception
     */
    public function getShopgateOrder(int $isPaid = 0, $config = []): ShopgateOrder
    {
        return new ShopgateOrder(
            array_merge([
                'order_number'               => random_int(1000000000, 9999999999),
                'is_paid'                    => $isPaid,
                'payment_time'               => null,
                'payment_transaction_number' => (string)random_int(1000000000, 9999999999),
                'mail'                       => 'shopgate@shopgate.com',
                'amount_shop_payment'        => '5.00',
                'amount_complete'            => '149.85',
                'shipping_infos'             => ['amount' => '4.90'],
                'invoice_address'            => $this->dataManager->getGermanAddress(),
                'delivery_address'           => $this->dataManager->getGermanAddress(false),
                'external_coupons'           => [],
                'shopgate_coupons'           => [],
                'items'                      => [$this->dataManager->getSimpleProduct()],
                'payment_infos'              => [],
                'payment_method'             => 'SHOPGATE',
                'payment_group'              => 'SHOPGATE',
            ], $config)
        );
    }
}
