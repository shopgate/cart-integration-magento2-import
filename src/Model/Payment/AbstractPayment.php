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
use Magento\Store\Model\ScopeInterface;
use Shopgate\Base\Model\Shopgate\Extended\Base as ShopgateOrder;

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

    const PAYMENT_CODE = '';

    /** @var ScopeConfigInterface */
    private $scopeConfig;
    /** @var MagentoOrder */
    private $magentoOrder;
    /** @var ShopgateOrder */
    private $shopgateOrder;
    /** @var Manager */
    private $moduleManager;
    /** @var PaymentHelper */
    private $paymentHelper;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        MagentoOrder $magentoOrder,
        ShopgateOrder $shopgateOrder,
        Manager $moduleManager,
        PaymentHelper $paymentHelper
    ) {
        $this->scopeConfig          = $scopeConfig;
        $this->magentoOrder         = $magentoOrder;
        $this->shopgateOrder        = $shopgateOrder;
        $this->moduleManager        = $moduleManager;
        $this->paymentHelper        = $paymentHelper;
    }

    /**
     * Runs initial setup functions
     */
    public function setUp(): void
    {

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
     * Returns the concrete payment model instance
     *
     * @return MethodInterface|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getPaymentModel(): ?MethodInterface
    {
        return $this->isModuleActive() ? $this->paymentHelper->getMethodInstance(static::PAYMENT_CODE) : null;
    }
}
