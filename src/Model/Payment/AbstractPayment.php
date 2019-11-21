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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Base\Model\Payment\Shopgate;
use Shopgate\Base\Model\Shopgate\Extended\Base as ShopgateOrder;
use Shopgate\Import\Helper\Order\Utility;

abstract class AbstractPayment
{
    /**
     * The config path to module enabled
     */
    protected const XML_CONFIG_ENABLED = '';
    /**
     * The config path to module's paid status
     */
    protected const XML_CONFIG_STATUS_PAID = '';
    /**
     * The config path to module's not paid status
     */
    protected const XML_CONFIG_STATUS_NOT_PAID = '';
    /**
     * The name of the module, as defined in etc/module.xml
     */
    protected const MODULE_NAME = '';
    /**
     * The code of the magento payment method
     */
    protected const PAYMENT_CODE = '';

    /** @var CoreInterface */
    private $scopeConfig;
    /** @var Manager */
    private $moduleManager;
    /** @var PaymentHelper */
    private $paymentHelper;
    /** @var Utility */
    private $utility;

    /**
     * @param CoreInterface $scopeConfig
     * @param Manager       $moduleManager
     * @param PaymentHelper $paymentHelper
     * @param Utility       $utility
     */
    public function __construct(
        CoreInterface $scopeConfig,
        Manager $moduleManager,
        PaymentHelper $paymentHelper,
        Utility $utility
    ) {
        $this->scopeConfig   = $scopeConfig;
        $this->moduleManager = $moduleManager;
        $this->paymentHelper = $paymentHelper;
        $this->utility       = $utility;
    }

    /**
     * Runs initial setup functions
     */
    public function setUp(): void
    {
    }

    /**
     * Returns the concrete payment model instance
     *
     * @return MethodInterface|null
     * @throws LocalizedException
     */
    public function getPaymentModel(): ?MethodInterface
    {
        return $this->isValid() ? $this->paymentHelper->getMethodInstance(static::PAYMENT_CODE) : null;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isModuleActive() && $this->isEnabled();
    }

    /**
     * Check if the payment module is active
     *
     * @return bool
     */
    public function isModuleActive(): bool
    {
        return $this->moduleManager->isEnabled(static::MODULE_NAME);
    }

    /**
     * Checks if a payment method is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) $this->scopeConfig->getConfigByPath(static::XML_CONFIG_ENABLED)->getValue();
    }

    /**
     * Allows manipulation of order data
     *
     * @param MagentoOrder  $magentoOrder
     * @param ShopgateOrder $shopgateOrder
     */
    public function manipulateOrderWithPaymentData(MagentoOrder $magentoOrder, ShopgateOrder $shopgateOrder): void
    {
    }

    /**
     * Sets order status based on configuration
     *
     * @param MagentoOrder  $magentoOrder
     * @param ShopgateOrder $shopgateOrder
     *
     * @throws LocalizedException
     */
    public function setOrderStatus(MagentoOrder $magentoOrder, ShopgateOrder $shopgateOrder): void
    {
        $orderStatusConfig = $shopgateOrder->getIsPaid()
            ? static::XML_CONFIG_STATUS_PAID
            : static::XML_CONFIG_STATUS_NOT_PAID;
        $orderStatus       = $this->scopeConfig->getConfigByPath($orderStatusConfig)->getValue();

        if ($orderStatus) {
            $orderState = $this->utility->getStateForStatus($orderStatus);
            if ($orderState === MagentoOrder::STATE_HOLDED) {
                if ($magentoOrder->canHold()) {
                    $magentoOrder->hold();
                }

                return;
            }
            $magentoOrder->setState($orderState)->setStatus($orderStatus);
        }
    }

    /**
     * @param ShopgateOrder $shopgateOrder
     *
     * @return array
     */
    public function getAdditionalPaymentData(ShopgateOrder $shopgateOrder): array
    {
        return [
            Shopgate::SG_DATA_OBJECT_KEY => $shopgateOrder
        ];
    }
}
