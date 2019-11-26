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

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote as MageQuote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteRepository;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Helper\Data as Tax;
use Shopgate\Base\Helper\Product\Type;
use Shopgate\Base\Helper\Product\Utility;
use Shopgate\Base\Helper\Quote\Coupon;
use Shopgate\Base\Helper\Quote\Customer;
use Shopgate\Base\Model\Payment\Shopgate;
use Shopgate\Base\Model\Rule\Condition\ShopgateOrder as OrderCondition;
use Shopgate\Base\Model\Shopgate\Extended;
use Shopgate\Base\Model\Utility\Registry;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Import\Model\Payment\Factory as PaymentFactory;

class Quote extends \Shopgate\Base\Helper\Quote
{
    /** @var PaymentFactory */
    private $paymentFactory;

    /**
     * @param MageQuote             $quote
     * @param Extended\Base         $cart
     * @param SgLoggerInterface     $logger
     * @param Utility               $productHelper
     * @param Tax                   $taxData
     * @param Customer              $quoteCustomer
     * @param Registry              $coreRegistry
     * @param StoreManagerInterface $storeManager
     * @param Coupon                $quoteCoupon
     * @param QuoteRepository       $quoteRepository
     * @param Type                  $typeHelper
     * @param PaymentFactory        $paymentFactory
     */
    public function __construct(
        MageQuote $quote,
        Extended\Base $cart,
        SgLoggerInterface $logger,
        Utility $productHelper,
        Tax $taxData,
        Customer $quoteCustomer,
        Registry $coreRegistry,
        StoreManagerInterface $storeManager,
        Coupon $quoteCoupon,
        QuoteRepository $quoteRepository,
        Type $typeHelper,
        PaymentFactory $paymentFactory
    ) {
        parent::__construct($quote, $cart, $logger, $productHelper, $taxData, $quoteCustomer, $coreRegistry,
            $storeManager, $quoteCoupon, $quoteRepository, $typeHelper);

        $this->paymentFactory = $paymentFactory;
    }

    /**
     * Assigns Shopgate cart customer to quote
     */
    protected function setCustomer()
    {
        parent::setCustomer();

        if ($this->sgBase->isGuest()) {
            $this->quote->setCheckoutMethod(QuoteManagement::METHOD_GUEST);
        }

        $this->coreRegistry->register(
            'rule_data',
            new DataObject(
                [
                    'store_id'          => $this->storeManager->getStore()->getId(),
                    'website_id'        => $this->storeManager->getWebsite()->getId(),
                    'customer_group_id' => $this->quote->getCustomerGroupId()
                ]
            ),
            true
        );
    }

    /**
     * Assigns shipping method to the quote
     */
    protected function setShipping()
    {
        $this->quoteRepository->save($this->quote);

        $this->setItemQty();
        $client     = is_null($this->sgBase->getClient()) ? '' : $this->sgBase->getClient()->getType();
        $methodName = $this->sgBase->getShippingInfos()->getName();
        $rate       = $this->quote->getShippingAddress()
                                  ->setCollectShippingRates(true)
                                  ->collectShippingRates()
                                  ->getShippingRateByCode($methodName);

        $this->quote->getShippingAddress()
                    ->setShippingMethod($rate ? $methodName : 'shopgate_fix')
                    ->setData(OrderCondition::CLIENT_ATTRIBUTE, $client);
    }

    /**
     * Same logic as in \Magento\Quote\Model\Quote\Address\Total\Shipping::collect()
     */
    protected function setItemQty()
    {
        $addressQty = 0;
        foreach ($this->quote->getItems() as $item) {
            /**
             * Skip if this item is virtual
             */
            if ($item->getProduct()->isVirtual()) {
                continue;
            }

            /**
             * Children weight we calculate for parent
             */
            if ($item->getParentItem()) {
                continue;
            }

            $addressQty += $item->getQty();
        }

        $this->quote->getShippingAddress()->setItemQty($addressQty);
    }

    /**
     * Assigns shipping method to the quote
     *
     * @throws LocalizedException
     */
    protected function setPayment()
    {
        $defaultPayment = $this->paymentFactory->getPayment(strtolower(PaymentFactory::DEFAULT_PAYMENT_METHOD));
        $this->quote->getPayment()->importData(
            [
                'method'                              => $defaultPayment->getPaymentModel()->getCode(),
                PaymentInterface::KEY_ADDITIONAL_DATA => $defaultPayment->getAdditionalPaymentData($this->sgBase)
            ]
        );
        $this->quote->getPayment()->setParentTransactionId($this->sgBase->getPaymentTransactionNumber());
    }
}
