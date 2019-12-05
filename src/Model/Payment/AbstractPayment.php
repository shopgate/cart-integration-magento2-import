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
use ShopgateLibraryException;

abstract class AbstractPayment
{
    /**
     * The config path to module enabled
     */
    protected const XML_CONFIG_ENABLED = '';
    /**
     * The config path to module's order status
     */
    protected const XML_CONFIG_ORDER_STATUS = 'processing';
    /**
     * The name of the module, as defined in etc/module.xml
     */
    protected const MODULE_NAME = '';
    /**
     * The code of the magento payment method
     */
    protected const PAYMENT_CODE = '';
    /**
     * Map payment method on quote
     */
    protected const IS_OFFLINE = true;

    /** @var CoreInterface */
    protected $scopeConfig;
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
     * @throws ShopgateLibraryException
     */
    public function getPaymentModel(): ?MethodInterface
    {
        return $this->isValid()
            ? $this->paymentHelper->getMethodInstance($this->validate('PAYMENT_CODE', static::PAYMENT_CODE))
            : null;
    }

    /**
     * @return bool
     * @throws ShopgateLibraryException
     */
    public function isValid(): bool
    {
        return $this->isModuleActive() && $this->isEnabled();
    }

    /**
     * Check if the payment module is active
     *
     * @return bool
     * @throws ShopgateLibraryException
     */
    public function isModuleActive(): bool
    {
        return $this->moduleManager->isEnabled($this->validate('MODULE_NAME', static::MODULE_NAME));
    }

    /**
     * @param string $const
     * @param string $value
     *
     * @return string
     * @throws ShopgateLibraryException
     */
    private function validate($const, $value): string
    {
        if (empty($value)) {
            $this->throwValidateException($const);
        }

        return $value;
    }

    /**
     * Throw a validate exception
     *
     * @param string $const
     *
     * @throws ShopgateLibraryException
     */
    private function throwValidateException(string $const): void
    {
        throw new ShopgateLibraryException(
            ShopgateLibraryException::UNKNOWN_ERROR_CODE,
            sprintf('Const not set \'%s\' in %s', $const, static::class),
            true
        );
    }

    /**
     * Checks if a payment method is enabled
     *
     * @return bool
     * @throws ShopgateLibraryException
     */
    public function isEnabled(): bool
    {
        return (bool)$this->scopeConfig->getConfigByPath(
            $this->validate('XML_CONFIG_ENABLED', static::XML_CONFIG_ENABLED)
        )->getValue();
    }

    /**
     * Allows manipulation of order data BEFORE it is saved
     *
     * @param MagentoOrder  $magentoOrder
     * @param ShopgateOrder $shopgateOrder
     */
    public function manipulateOrderWithPaymentDataBeforeSave(
        MagentoOrder $magentoOrder,
        ShopgateOrder $shopgateOrder
    ): void {
    }

    /**
     * Allows manipulation of order data After it is saved
     *
     * @param MagentoOrder  $magentoOrder
     * @param ShopgateOrder $shopgateOrder
     */
    public function manipulateOrderWithPaymentDataAfterSave(
        MagentoOrder $magentoOrder,
        ShopgateOrder $shopgateOrder
    ): void {
    }

    /**
     * Sets order status based on configuration
     *
     * @param MagentoOrder  $magentoOrder
     * @param ShopgateOrder $shopgateOrder
     *
     * @throws LocalizedException
     * @throws ShopgateLibraryException
     */
    public function setOrderStatus(MagentoOrder $magentoOrder, ShopgateOrder $shopgateOrder): void
    {
        $orderStatus = $this->scopeConfig->getConfigByPath(
            $this->validate('XML_CONFIG_ORDER_STATUS', static::XML_CONFIG_ORDER_STATUS)
        )->getValue();

        $orderState = $this->utility->getStateForStatus($orderStatus);
        if ($orderState === MagentoOrder::STATE_HOLDED) {
            if ($magentoOrder->canHold()) {
                $magentoOrder->hold();
            }

            return;
        }
        $magentoOrder->setState($orderState)->setStatus($orderStatus);
    }

    /**
     * @param ShopgateOrder $shopgateOrder
     *
     * @return ShopgateOrder[]
     */
    public function getAdditionalPaymentData(ShopgateOrder $shopgateOrder): array
    {
        return [
            Shopgate::SG_DATA_OBJECT_KEY => $shopgateOrder,
        ];
    }

    /**
     * An offline payment means that the payment code will be set to the quote before it is saved. The reason being is
     * that unlike online payments, offline will not trigger capture when the quote is turned into an order.
     *
     * @return bool
     */
    public function isOffline(): bool
    {
        return static::IS_OFFLINE;
    }
}
