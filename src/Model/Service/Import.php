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

namespace Shopgate\Import\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Shopgate\Base\Api\Config\SgCoreInterface;
use Shopgate\Base\Api\OrderRepositoryInterface;
use Shopgate\Base\Model\Shopgate\Extended\Base;
use Shopgate\Import\Api\ImportInterface;
use Shopgate\Import\Helper\Customer\Setter as CustomerSetter;
use Shopgate\Import\Helper\Order as OrderSetter;
use ShopgateCustomer;

class Import implements ImportInterface
{

    /** @var CustomerSetter */
    private $customerSetter;
    /** @var SgCoreInterface */
    private $config;
    /** @var StoreManagerInterface */
    private $storeManager;
    /** @var Base */
    private $order;
    /** @var OrderSetter */
    private $orderSetter;
    /** @var ResourceConnection */
    private $resourceConnection;
    /** @var array */
    private $addOrderMethods;
    /** @var array */
    private $updateOrderMethods;
    /** @var OrderRepositoryInterface */
    private $sgOrderRepository;

    /**
     * @param CustomerSetter           $customerSetter
     * @param OrderSetter              $orderSetter
     * @param SgCoreInterface          $config
     * @param StoreManagerInterface    $storeManager
     * @param Base                     $order
     * @param ResourceConnection       $resourceConnection
     * @param OrderRepositoryInterface $sgOrderRepository
     * @param array                    $addOrderMethods    - methods loaded via DI.xml
     * @param array                    $updateOrderMethods - methods loaded via DI.xml
     */
    public function __construct(
        CustomerSetter $customerSetter,
        OrderSetter $orderSetter,
        SgCoreInterface $config,
        StoreManagerInterface $storeManager,
        Base $order,
        ResourceConnection $resourceConnection,
        OrderRepositoryInterface $sgOrderRepository,
        $addOrderMethods = [],
        $updateOrderMethods = []
    ) {
        $this->customerSetter     = $customerSetter;
        $this->config             = $config;
        $this->storeManager       = $storeManager;
        $this->order              = $order;
        $this->orderSetter        = $orderSetter;
        $this->addOrderMethods    = $addOrderMethods;
        $this->resourceConnection = $resourceConnection;
        $this->updateOrderMethods = $updateOrderMethods;
        $this->sgOrderRepository  = $sgOrderRepository;
    }

    /**
     * @inheritdoc
     */
    public function registerCustomer($action, $shopNumber, $user, $pass, $traceId, $userData)
    {
        $this->storeManager->setCurrentStore($this->config->getStoreId($shopNumber));
        $sgCustomer = new ShopgateCustomer($userData);

        if (isset($userData['addresses']) && is_array($userData['addresses'])) {
            $addresses = [];
            foreach ($userData['addresses'] as $address) {
                $addresses[] = new \ShopgateAddress($address);
            }
            $sgCustomer->setAddresses($addresses);
        }

        $this->registerCustomerRaw($user, $pass, $sgCustomer);
    }

    /**
     * @inheritdoc
     */
    public function registerCustomerRaw($user, $pass, ShopgateCustomer $customer)
    {
        $this->customerSetter->registerCustomer($user, $pass, $customer);
    }

    /**
     * @inheritdoc
     */
    public function addOrder($order)
    {
        $this->order->loadArray($order->toArray());

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $mageOrder = $this->orderSetter->loadMethods($this->addOrderMethods);
            $this->sgOrderRepository->createAndSave($mageOrder->getId());
            $connection->commit();
        } catch (\ShopgateLibraryException $e) {
            $connection->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new \ShopgateLibraryException(
                \ShopgateLibraryException::UNKNOWN_ERROR_CODE,
                "{$e->getMessage()}\n{$e->getTraceAsString()}",
                true
            );
        }

        return [
            'external_order_id'     => $mageOrder->getId(),
            'external_order_number' => $mageOrder->getIncrementId()
        ];
    }

    /**
     * @inheritdoc
     */
    public function updateOrder($order)
    {
        $this->order->loadArray($order->toArray());

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $mageOrder = $this->orderSetter->loadMethods($this->updateOrderMethods);
            $connection->commit();
        } catch (\ShopgateLibraryException $e) {
            $connection->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new \ShopgateLibraryException(
                \ShopgateLibraryException::UNKNOWN_ERROR_CODE,
                "{$e->getMessage()}\n{$e->getTraceAsString()}",
                true
            );
        }

        return [
            'external_order_id'     => $mageOrder->getId(),
            'external_order_number' => $mageOrder->getIncrementId()
        ];
    }
}
