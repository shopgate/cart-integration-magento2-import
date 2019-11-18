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

use Exception;
use Magento\Framework\Module\Manager;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Base\Model\Shopgate\Extended\Base as ShopgateOrder;
use Shopgate\Import\Helper\Order\Utility;
use Shopgate\Import\Helper\Payment\Braintree as Helper;
use Shopgate\Import\Model\Payment\AbstractPayment;

class CreditCard extends AbstractPayment
{
    const MODULE_NAME        = 'Magento_Braintree';
    const PAYMENT_CODE       = 'braintree';
    const XML_CONFIG_ENABLED = 'payment/braintree/active';
    const STATUS_AUTHORIZED  = 'authorized';
    const CREDIT_CARD_TYPE_MAP = [
        'visa'       => 'VI',
        'maestro'    => 'MI',
        'mastercard' => 'MC',
        'discover'   => 'DI',
        'amex'       => 'AE',
        'jcb'        => 'JCB',
        'unionpay'   => 'CUP',
    ];

    /** @var PaymentTokenFactoryInterface */
    private $paymentTokenFactory;
    /** @var OrderPaymentRepositoryInterface */
    private $orderPaymentRepository;
    /** @var SerializerInterface */
    private $serializer;
    /** @var Helper */
    private $helper;

    /**
     * @param CoreInterface                   $scopeConfig
     * @param Manager                         $moduleManager
     * @param PaymentHelper                   $paymentHelper
     * @param Utility                         $utility
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param PaymentTokenFactoryInterface    $paymentTokenFactory
     * @param SerializerInterface             $serializer
     * @param Helper                          $helper
     */
    public function __construct(
        CoreInterface $scopeConfig,
        Manager $moduleManager,
        PaymentHelper $paymentHelper,
        Utility $utility,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        SerializerInterface $serializer,
        Helper $helper
    ) {
        parent::__construct($scopeConfig, $moduleManager, $paymentHelper, $utility);

        $this->paymentTokenFactory    = $paymentTokenFactory;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->serializer             = $serializer;
        $this->helper                 = $helper;
    }

    /**
     * @inheritDoc
     *
     * @throws Exception
     */
    public function manipulateOrderWithPaymentData(MagentoOrder $magentoOrder, ShopgateOrder $shopgateOrder): void
    {
        $paymentInformation  = $shopgateOrder->getPaymentInfos();
        $usedCreditCard      = $paymentInformation['credit_card'];
        $paymentToken        = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
        $paymentTokenDetails = [
            'type'           => $this->getMappedCCType($paymentInformation['credit_card']['type']),
            'maskedCC'       => $this->helper->getLastCCNumbers($paymentInformation['credit_card']['masked_number']),
            'expirationData' => $this->helper->formatExpirationDate(
                $paymentInformation['credit_card']['expiry_month'],
                $paymentInformation['credit_card']['expiry_year']
            )
        ];
        $paymentToken->setGatewayToken($paymentInformation['processor_auth_code'])
                     ->setExpiresAt(
                         $this->helper->calculateExpirationDate(
                             $usedCreditCard['expiry_year'],
                             $usedCreditCard['expiry_month']
                         )
                     )->setTokenDetails($this->serializer->serialize($paymentTokenDetails));

        $orderPayment = $this->orderPaymentRepository->get($magentoOrder->getPayment()->getEntityId());
        $this->orderPaymentRepository->delete($orderPayment);

        $magentoOrder->getPayment()->setData([
            'method'                 => $this->getPaymentModel()->getCode(),
            'additional_information' => $this->getAdditionalPaymentData($shopgateOrder),
            'transaction_id'         => $paymentInformation['transaction_id'],
            'cc_trans_id'            => $paymentInformation['transaction_id'],
            'last_trans_id'          => $paymentInformation['transaction_id'],
            'cc_owner'               => $usedCreditCard['holder'],
            'cc_type'                => $this->getMappedCCType($usedCreditCard['type']),
            'cc_number_enc'          => $usedCreditCard['masked_number'],
            'cc_last_4'              => $this->helper->getLastCCNumbers($usedCreditCard['masked_number']),
            'cc_exp_month'           => $this->helper->formatExpirationMonth($usedCreditCard['expiry_month']),
            'cc_exp_year'            => $paymentInformation['credit_card']['expiry_year']
        ]);

        $extensionAttributes = $magentoOrder->getPayment()->getExtensionAttributes();
        $extensionAttributes->setVaultPaymentToken($paymentToken);
        $magentoOrder->getPayment()->setExtensionAttributes($extensionAttributes);
        $magentoOrder->getPayment()->save();

        $amount = $magentoOrder->getPayment()->formatAmount($shopgateOrder->getAmountComplete(), true);
        $magentoOrder->getPayment()->setBaseAmountAuthorized($amount);
        $magentoOrder->getPayment()->setShouldCloseParentTransaction(false);
        $magentoOrder->getPayment()->setIsTransactionClosed(0);

        if ($shopgateOrder->getIsPaid()) {
            $magentoOrder->getPayment()->registerCaptureNotification($shopgateOrder->getAmountComplete(), true);
        } else {
            $magentoOrder->getPayment()->registerAuthorizationNotification($shopgateOrder->getAmountComplete());
        }
    }

    /**
     * @param string $ccType
     *
     * @return string
     */
    private function getMappedCCType(string $ccType): string
    {
        return static::CREDIT_CARD_TYPE_MAP[$ccType];
    }

    /**
     * @inheritDoc
     */
    public function getAdditionalPaymentData(ShopgateOrder $shopgateOrder): array
    {
        $paymentInformation = $shopgateOrder->getPaymentInfos();

        return [
            'method_title'               => $paymentInformation['shopgate_payment_name'],
            'processorAuthorizationCode' => $paymentInformation['processor_auth_code'],
            'processorResponseCode'      => $paymentInformation['processor_response_code'],
            'processorResponseText'      => $paymentInformation['processor_response_text'],
            'cc_number'                  => $this->helper->formatVisibleCCNumber(
                $paymentInformation['credit_card']['masked_number']
            ),
            'cc_type'                    => $this->helper->formatVisibleCCType(
                $this->getMappedCCType($paymentInformation['credit_card']['type'])
            )
        ];
    }

    /**
     * @inheritDoc
     */
    public function setOrderStatus(MagentoOrder $magentoOrder, ShopgateOrder $shopgateOrder): void
    {
        // todo-sg: checkout how status will be set as there is no configuration for it
    }
}
