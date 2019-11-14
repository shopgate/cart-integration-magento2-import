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

namespace Shopgate\Import\Model\Payment\Shopgate;

use Magento\Sales\Model\Order as MagentoOrder;
use Shopgate\Base\Model\Shopgate\Extended\Base as ShopgateOrder;
use Shopgate\Import\Model\Payment\AbstractPayment;

class Shopgate extends AbstractPayment
{
    const MODULE_NAME            = 'Shopgate_Base';
    const PAYMENT_CODE           = 'shopgate';
    const XML_CONFIG_STATUS_PAID = 'payment/shopgate/order_status';

    /**
     * Always valid as it is the fallback method
     *
     * @inheritDoc
     */
    public function isValid(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function manipulateOrderWithPaymentData(MagentoOrder $magentoOrder, ShopgateOrder $shopgateOrder): void
    {
        if ($shopgateOrder->getIsPaid()
            && $magentoOrder->getBaseTotalDue()
            && $magentoOrder->getPayment()
        ) {
            $magentoOrder->getPayment()->setShouldCloseParentTransaction(true);
            $magentoOrder->getPayment()->registerCaptureNotification($shopgateOrder->getAmountComplete());
            $magentoOrder->addStatusHistoryComment(__('[SHOPGATE] Payment received.'))
                               ->setIsCustomerNotified(false);
        }
    }
}
