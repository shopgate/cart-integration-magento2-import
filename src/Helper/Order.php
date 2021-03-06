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

namespace Shopgate\Import\Helper;

use Magento\Framework\Api\SimpleDataObjectConverter;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order as MageOrder;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderNotifier;
use Magento\Sales\Model\OrderRepository;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Base\Api\OrderRepositoryInterface;
use Shopgate\Base\Model\Shopgate;
use Shopgate\Base\Model\Shopgate\Extended\Base;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Import\Helper\Order\Shipping;
use Shopgate\Import\Model\Payment\Factory as PaymentFactory;
use Shopgate\Import\Model\Service\Import as ImportService;
use ShopgateLibraryException;

class Order
{
    /** @var Base */
    protected $sgOrder;
    /** @var MageOrder */
    protected $mageOrder;
    /** @var ManagerInterface */
    protected $eventManager;
    /** @var SgLoggerInterface */
    private $log;
    /** @var Quote */
    private $quote;
    /** @var array */
    private $quoteMethods;
    /** @var CartManagementInterface | QuoteManagement */
    private $quoteManagement;
    /** @var OrderRepository */
    private $orderRepository;
    /** @var OrderRepositoryInterface */
    private $sgOrderRepository;
    /** @var CoreInterface */
    private $config;
    /** @var OrderNotifier */
    private $orderNotifier;
    /** @var Shopgate\Order */
    private $localSgOrder;
    /** @var QuoteRepository */
    private $quoteRepository;
    /** @var Shipping */
    private $shippingHelper;
    /** @var PaymentFactory */
    private $paymentFactory;

    /**
     * @param Base                     $order
     * @param SgLoggerInterface        $log
     * @param Quote                    $quote
     * @param CartManagementInterface  $quoteManagement
     * @param OrderRepository          $orderRepository
     * @param MageOrder                $mageOrder
     * @param OrderRepositoryInterface $sgOrderRepository
     * @param CoreInterface            $config
     * @param OrderNotifier            $orderNotifier
     * @param QuoteRepository          $quoteRepository
     * @param Shopgate\Order           $localSgOrder
     * @param Shipping                 $shippingHelper
     * @param ManagerInterface         $eventManager
     * @param PaymentFactory           $paymentFactory
     * @param array                    $quoteMethods
     */
    public function __construct(
        Base $order,
        SgLoggerInterface $log,
        Quote $quote,
        CartManagementInterface $quoteManagement,
        OrderRepository $orderRepository,
        MageOrder $mageOrder,
        OrderRepositoryInterface $sgOrderRepository,
        CoreInterface $config,
        OrderNotifier $orderNotifier,
        QuoteRepository $quoteRepository,
        Shopgate\Order $localSgOrder,
        Shipping $shippingHelper,
        ManagerInterface $eventManager,
        PaymentFactory $paymentFactory,
        array $quoteMethods = []
    ) {
        $this->sgOrder           = $order;
        $this->log               = $log;
        $this->quote             = $quote;
        $this->quoteMethods      = $quoteMethods;
        $this->quoteManagement   = $quoteManagement;
        $this->orderRepository   = $orderRepository;
        $this->mageOrder         = $mageOrder;
        $this->sgOrderRepository = $sgOrderRepository;
        $this->config            = $config;
        $this->orderNotifier     = $orderNotifier;
        $this->quoteRepository   = $quoteRepository;
        $this->localSgOrder      = $localSgOrder;
        $this->shippingHelper    = $shippingHelper;
        $this->eventManager      = $eventManager;
        $this->paymentFactory    = $paymentFactory;
    }

    /**
     * @param array $methods
     *
     * @return MageOrder
     */
    public function loadMethods(array $methods)
    {
        foreach ($methods as $rawMethod) {
            $method = 'set' . SimpleDataObjectConverter::snakeCaseToUpperCamelCase($rawMethod);
            $this->log->debug('Starting method ' . $method);
            $this->{$method}();
            $this->log->debug('Finished method ' . $method);
        }

        return $this->mageOrder;
    }

    /**
     * Executes after order is fully loaded and updated
     */
    public function setEndUpdate()
    {
        $this->mageOrder->addStatusHistoryComment(__('[SHOPGATE] Order updated by Shopgate.'))
                        ->setIsCustomerNotified(false);

        $this->orderRepository->save($this->mageOrder);
        $this->sgOrderRepository->update($this->localSgOrder);
    }

    /**
     * Creates the order then we can continue loading on $this->mageOrder
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws ShopgateLibraryException
     * @throws LocalizedException
     */
    protected function setStartAdd()
    {
        $orderNumber = $this->sgOrder->getOrderNumber();
        $this->log->debug('## Order-Number: ' . $orderNumber);

        $this->sgOrderRepository->checkOrderExists($orderNumber, true);

        $quote = $this->quote->load($this->quoteMethods);
        $quote->setData('totals_collected_flag', false);
        $quote->collectTotals();
        $quote->getShippingAddress()->setData('should_ignore_validation', true);
        $quote->getBillingAddress()->setData('should_ignore_validation', true);

        // save quote before submitting to validate item qty before the order is placed.
        // when a non-backorderable product is saved in an order, it will become "out of stock" if the current order
        // depletes its inventory. any quote item validation done after the order placement will thus fail.
        // $this->quoteManagement->submit() saves the quote *after* the order is placed to update its data. it is thus
        // important that the quoteItems are already saved before this point to avoid a too late stock validation.
        $this->quoteRepository->save($quote);

        $this->eventManager->dispatch('checkout_submit_before', ['quote' => $quote]);
        $order = $this->quoteManagement->submit($quote);
        if (null === $order) {
            throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_ORDER_ITEM_NOT_FOUND);
        }
        $this->mageOrder = $order;
        $this->eventManager->dispatch('checkout_submit_all_after', ['order' => $this->mageOrder, 'quote' => $quote]);
    }

    /**
     * Executes after order is fully loaded
     */
    protected function setEndAdd()
    {
        $this->orderRepository->save($this->mageOrder);
    }

    /**
     * Updates the order
     *
     * @throws NoSuchEntityException
     * @throws ShopgateLibraryException
     * @throws InputException
     */
    protected function setStartUpdate()
    {
        $orderNumber = $this->sgOrder->getOrderNumber();
        $this->log->debug('## Order-Number: ' . $orderNumber);
        $this->localSgOrder = $this->sgOrderRepository->checkOrderExists($orderNumber);
        if (!$this->localSgOrder->getShopgateOrderId()) {
            throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_ORDER_NOT_FOUND);
        }

        $this->mageOrder = $this->orderRepository->get($this->localSgOrder->getOrderId());
    }

    /**
     * Checks if payment should be updated for order
     */
    protected function setUpdatePayment()
    {
        if ((bool) $this->sgOrder->getUpdatePayment()) {
            $this->log->debug('# Payment requires an update');
            $this->setOrderPayment();
        }
    }

    /**
     * Manipulate payments according to payment method
     */
    protected function setOrderPayment()
    {
        // check and fix total issues before payment processing
        if ($this->shouldFixOrderTotals()) {
            $this->mageOrder->setBaseTotalDue($this->sgOrder->getAmountComplete());
            $this->mageOrder->setTotalDue($this->sgOrder->getAmountComplete());
            $this->mageOrder->setBaseGrandTotal($this->sgOrder->getAmountComplete());
            $this->mageOrder->setGrandTotal($this->sgOrder->getAmountComplete());
        }

        $payment = $this->paymentFactory->getPayment($this->sgOrder->getPaymentMethod());
        $payment->manipulateOrderWithPaymentDataBeforeSave($this->mageOrder, $this->sgOrder);

        $this->mageOrder = $this->orderRepository->save($this->mageOrder);

        $payment->manipulateOrderWithPaymentDataAfterSave($this->mageOrder, $this->sgOrder);

        // check and fix total issues after payment processing
        if ($this->shouldFixOrderTotals()) {
            $this->mageOrder->getPayment()->setAmountOrdered($this->sgOrder->getAmountComplete());
            $this->mageOrder->getPayment()->setBaseAmountOrdered($this->sgOrder->getAmountComplete());

            if ($createdInvoice = $this->mageOrder->getPayment()->getCreatedInvoice()) {
                /** @var Invoice $createdInvoice */
                $createdInvoice->setGrandTotal($this->mageOrder->getGrandTotal());
                $createdInvoice->setBaseGrandTotal($this->mageOrder->getBaseGrandTotal());

                $this->mageOrder->setBaseTotalInvoiced($createdInvoice->getBaseGrandTotal());
                $this->mageOrder->setTotalInvoiced($createdInvoice->getGrandTotal());
                $this->mageOrder->setTotalPaid($createdInvoice->getGrandTotal());
                $this->mageOrder->setBaseTotalPaid($this->mageOrder->getBaseTotalInvoiced());
            }
        }
    }

    /**
     * Checks if shipments should be updated for an existing order
     *
     * @throws LocalizedException
     */
    protected function setUpdateShipping()
    {
        if ((bool) $this->sgOrder->getUpdateShipping()
            && !$this->sgOrder->getIsShippingCompleted()
            && $this->mageOrder->canShip()
            && !$this->localSgOrder->getIsShippingBlocked()
        ) {
            $this->log->debug('# Shipping requires an update');
            $this->shippingHelper->update($this->mageOrder);
        }
    }

    /**
     * Set correct order status by payment
     *
     * @throws LocalizedException
     */
    protected function setOrderState()
    {
        $this->paymentFactory->getPayment($this->sgOrder->getPaymentMethod())
                             ->setOrderStatus($this->mageOrder, $this->sgOrder);
    }

    /**
     * Set order status history entries
     */
    protected function setOrderStatusHistory()
    {
        $this->mageOrder->addStatusHistoryComment(
            __('[SHOPGATE] Order added by Shopgate # %1', $this->sgOrder->getOrderNumber())
        )->setIsCustomerNotified(false);
    }

    /**
     * Set order shipping details
     */
    protected function setOrderShipping()
    {
        $shippingTitle = $this->sgOrder->getShippingInfos()->getDisplayName();
        $this->mageOrder->setShippingDescription($shippingTitle);
    }

    /**
     * Send order notification if activated in config
     *
     * @throws MailException
     */
    protected function setOrderNotification()
    {
        $this->mageOrder->setEmailSent(null);
        if ($this->config->getConfigByPath(ImportService::PATH_SEND_NEW_ORDER_MAIL)->getValue()) {
            $this->log->debug('# Notified customer about new order');
            $this->orderNotifier->notify($this->mageOrder);
        }
    }

    /**
     * Returns whether order totals should be fixed or not
     *
     * @return bool
     */
    protected function shouldFixOrderTotals(): bool
    {
        if ($this->config->getConfigByPath(ImportService::PATH_FIX_ORDER_TOTALS)->getValue() === 0) {
            return false;
        }

        $totalDifferenceInCent = (int) (abs(
            $this->sgOrder->getAmountComplete() - $this->mageOrder->getBaseGrandTotal()
        ) * 100);

        return (bool) $totalDifferenceInCent <= (int) $this->config
                ->getConfigByPath(ImportService::PATH_FIX_ORDER_TOTALS_THRESHOLD)->getValue();
    }

    /**
     * Adds custom fields to magento order & its address fields
     */
    protected function setCustomFields()
    {
        $this->mageOrder->addData($this->sgOrder->customFieldsToArray());
        $billing = $this->mageOrder->getBillingAddress();
        if ($billing) {
            $billing->addData($this->sgOrder->getInvoiceAddress()->customFieldsToArray());
        }
        $shipping = $this->mageOrder->getShippingAddress();
        if ($shipping) {
            $shipping->addData($this->sgOrder->getDeliveryAddress()->customFieldsToArray());
        }
    }
}
