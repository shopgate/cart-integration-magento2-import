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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Base\Model\Shopgate\Extended\Base as ShopgateOrder;
use Shopgate\Import\Helper\Order\Utility;
use Shopgate\Import\Helper\Payment\Braintree as Helper;
use ShopgateLibraryException;

class CreditCard extends Base
{
    protected const  MODULE_NAME          = 'Magento_Braintree';
    protected const  PAYMENT_CODE         = 'braintree';
    protected const  XML_CONFIG_ENABLED   = 'payment/braintree/active';
    private const    CREDIT_CARD_TYPE_MAP = [
        'visa'             => 'VI',
        'maestro'          => 'MI',
        'mastercard'       => 'MC',
        'discover'         => 'DI',
        'amex'             => 'AE',
        'jcb'              => 'JCB',
        'unionpay'         => 'CUP',
        'american_express' => 'AE',
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
     * @throws LocalizedException
     * @throws Exception
     */
    public function manipulateOrderWithPaymentDataBeforeSave(
        MagentoOrder $magentoOrder,
        ShopgateOrder $shopgateOrder
    ): void {
        $paymentInformation = $shopgateOrder->getPaymentInfos();
        $usedCreditCard     = $paymentInformation['credit_card'];
        $payment            = $magentoOrder->getPayment();

        $orderPayment = $this->orderPaymentRepository->get($payment->getEntityId());
        $this->orderPaymentRepository->delete($orderPayment);
        $payment->setData(
            [
                'method'                 => $this->getPaymentModel() ? $this->getPaymentModel()->getCode() : '',
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
            ]
        );

        $extensionAttributes = $payment->getExtensionAttributes();
        if ($extensionAttributes) {
            $paymentToken        =
                $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
            $paymentTokenDetails = [
                'type'           => $this->getMappedCCType($usedCreditCard['type']),
                'maskedCC'       => $this->helper->getLastCCNumbers($usedCreditCard['masked_number']),
                'expirationData' => $this->helper->formatExpirationDate(
                    $usedCreditCard['expiry_month'],
                    $usedCreditCard['expiry_year']
                )
            ];
            $paymentToken->setGatewayToken($paymentInformation['processor_auth_code'])
                         ->setExpiresAt(
                             $this->helper->calculateExpirationDate(
                                 $usedCreditCard['expiry_year'],
                                 $usedCreditCard['expiry_month']
                             )
                         )->setTokenDetails($this->serializer->serialize($paymentTokenDetails));

            /** @noinspection PhpUndefinedMethodInspection */
            $extensionAttributes->setVaultPaymentToken($paymentToken);
            $payment->setExtensionAttributes($extensionAttributes);
        }
        $payment->save();

        $amount = $payment->formatAmount($shopgateOrder->getAmountComplete(), true);
        $payment->setBaseAmountAuthorized($amount);
        $payment->setShouldCloseParentTransaction(false);
        $payment->setIsTransactionClosed(0);
        $payment->registerAuthorizationNotification($shopgateOrder->getAmountComplete());
    }

    /**
     * @inheritDoc
     */
    public function getAdditionalPaymentData(ShopgateOrder $shopgateOrder): array
    {
        $paymentInformation = $shopgateOrder->getPaymentInfos();

        $additionalPaymentData = [
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

        if (!empty($paymentInformation['risk_data'])) {
            $additionalPaymentData['riskDataDecision'] = $paymentInformation['risk_data']['decision'];
            $additionalPaymentData['riskDataId']       = $paymentInformation['risk_data']['id'];
        }

        return $additionalPaymentData;
    }

    /**
     * @param string $ccType
     *
     * @return string
     *
     * @throws ShopgateLibraryException
     */
    private function getMappedCCType(string $ccType): string
    {
        if (isset(static::CREDIT_CARD_TYPE_MAP[$ccType])) {
            return static::CREDIT_CARD_TYPE_MAP[$ccType];
        }

        throw new ShopgateLibraryException(
            ShopgateLibraryException::UNKNOWN_ERROR_CODE,
            sprintf('Unknown Braintree CC Type: ' . $ccType),
            true
        );
    }
}
