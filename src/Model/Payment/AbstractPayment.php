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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order as MageOrder;
use Magento\Store\Model\ScopeInterface;
use Shopgate\Base\Model\Shopgate\Extended\Base as ShopgateOrder;
use Shopgate\Import\Helper\Order\Utility;

abstract class AbstractPayment
{
    /**
     * The config path to module enabled
     */
    const XML_CONFIG_ENABLED = '';
    /**
     * The config path to module's paid status
     */
    const XML_CONFIG_STATUS_PAID = '';
    /**
     * The config path to module's not paid status
     */
    const XML_CONFIG_STATUS_NOT_PAID = '';
    /**
     * The name of the module, as defined in etc/module.xml
     */
    const MODULE_CONFIG = '';
    /**
     * The code of the magento payment method
     */
    const PAYMENT_CODE = '';

    /** @var MagentoOrder */
    protected $magentoOrder;
    /** @var ShopgateOrder */
    protected $shopgateOrder;
    /** @var ScopeConfigInterface */
    private $scopeConfig;
    /** @var Manager */
    private $moduleManager;
    /** @var PaymentHelper */
    private $paymentHelper;
    /** @var Utility */
    private $utility;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param MageOrder            $magentoOrder
     * @param ShopgateOrder        $shopgateOrder
     * @param Manager              $moduleManager
     * @param PaymentHelper        $paymentHelper
     * @param Utility              $utility
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        MagentoOrder $magentoOrder,
        ShopgateOrder $shopgateOrder,
        Manager $moduleManager,
        PaymentHelper $paymentHelper,
        Utility $utility
    ) {
        $this->scopeConfig   = $scopeConfig;
        $this->magentoOrder  = $magentoOrder;
        $this->shopgateOrder = $shopgateOrder;
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
     * @throws \Magento\Framework\Exception\LocalizedException
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
     * Check if the payment module is enabled
     *
     * @return bool
     */
    public function isModuleActive(): bool
    {
        return $this->moduleManager->isEnabled(static::MODULE_CONFIG);
    }

    /**
     * Checks if a payment method is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        $this->scopeConfig->isSetFlag(static::XML_CONFIG_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Allows manipulation of order data
     */
    public function manipulateOrderWithPaymentData(): void
    {
    }

    /**
     * Sets order status based on configuration
     */
    public function setOrderStatus(): void
    {
        $orderStatus = $this->shopgateOrder->getIsPaid()
            ? static::XML_CONFIG_STATUS_PAID
            : static::XML_CONFIG_STATUS_NOT_PAID;

        $orderState  = $this->utility->getStateForStatus($orderStatus);
        if ($orderState === MagentoOrder::STATE_HOLDED) {
            if ($this->magentoOrder->canHold()) {
                $this->magentoOrder->hold();
            }

            return;
        }
        $this->magentoOrder->setState($orderState)->setStatus($orderStatus);
    }
}
