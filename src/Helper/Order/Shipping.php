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

namespace Shopgate\Import\Helper\Order;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Convert\Order as OrderConverter;
use Magento\Sales\Model\Order;

class Shipping
{

    /** @var OrderConverter */
    private $orderConverter;
    /** @var OrderRepositoryInterface */
    private $orderRepository;
    /** @var ShipmentRepositoryInterface */
    private $shipmentRepository;

    /**
     * @param OrderConverter              $orderConverter
     * @param OrderRepositoryInterface    $orderRepository
     * @param ShipmentRepositoryInterface $shipmentRepository
     */
    public function __construct(
        OrderConverter $orderConverter,
        OrderRepositoryInterface $orderRepository,
        ShipmentRepositoryInterface $shipmentRepository
    ) {
        $this->orderConverter     = $orderConverter;
        $this->orderRepository    = $orderRepository;
        $this->shipmentRepository = $shipmentRepository;
    }

    /**
     * Updates the shipping info of the order
     * using loaded shipping info from the database
     *
     * @param Order $magentoOrder
     *
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function update(Order $magentoOrder)
    {
        $shipment = $this->orderConverter->toShipment($magentoOrder);

        foreach ($magentoOrder->getAllItems() as $orderItem) {
            if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }

            $qtyShipped   = $orderItem->getQtyToShip();
            $shipmentItem = $this->orderConverter->itemToShipmentItem($orderItem)->setQty($qtyShipped);

            $shipment->addItem($shipmentItem);
        }

        $shipment->register();
        $shipment->getOrder()->setIsInProcess(true);
        $this->shipmentRepository->save($shipment);
        $this->orderRepository->save($shipment->getOrder());
    }
}
