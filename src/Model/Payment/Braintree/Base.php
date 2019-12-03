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

namespace Shopgate\Import\Model\Payment\Braintree;

use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Shopgate\Base\Model\Shopgate\Extended\Base as ShopgateOrder;
use Shopgate\Import\Model\Payment\AbstractPayment;

class Base extends AbstractPayment
{
    /**
     * The config path for the selected action method
     */
    private const XML_CONFIG_PAYMENT_ACTION = 'payment/%s/payment_action';

    /**
     * Will be handled automatically, therefor empty
     *
     * @inheritDoc
     */
    public function setOrderStatus(MagentoOrder $magentoOrder, ShopgateOrder $shopgateOrder): void
    {
    }

    /**
     * @inheritDoc
     */
    public function manipulateOrderWithPaymentDataAfterSave(
        MagentoOrder $magentoOrder,
        ShopgateOrder $shopgateOrder
    ): void {
        $payment = $magentoOrder->getPayment();
        if ($shopgateOrder->getIsPaid()) {
            // presumably Shopgate captured, so we should not
            $payment->registerCaptureNotification($shopgateOrder->getAmountComplete(), true);
        } elseif ($this->shouldCaptureOnline()) {
            $payment->capture();
        }
    }

    /**
     * Checks if method is configured to authorize and capture
     *
     * @return bool
     */
    private function shouldCaptureOnline(): bool
    {
        $configPath              = sprintf(self::XML_CONFIG_PAYMENT_ACTION, static::PAYMENT_CODE);
        $configuredPaymentAction = $this->scopeConfig->getConfigByPath($configPath)->getValue();

        return $configuredPaymentAction === MethodInterface::ACTION_AUTHORIZE_CAPTURE;
    }
}
