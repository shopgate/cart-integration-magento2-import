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
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Base\Model\Shopgate\Extended\Base as ShopgateOrder;
use Shopgate\Import\Helper\Order\Utility;

class PayPal extends Base
{
    protected const  MODULE_NAME        = 'Magento_Braintree';
    protected const  PAYMENT_CODE       = 'braintree_paypal';
    protected const  XML_CONFIG_ENABLED = 'payment/braintree_paypal/active';

    /** @var OrderPaymentRepositoryInterface */
    private $orderPaymentRepository;

    /**
     * @param CoreInterface                   $scopeConfig
     * @param Manager                         $moduleManager
     * @param PaymentHelper                   $paymentHelper
     * @param Utility                         $utility
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     */
    public function __construct(
        CoreInterface $scopeConfig,
        Manager $moduleManager,
        PaymentHelper $paymentHelper,
        Utility $utility,
        OrderPaymentRepositoryInterface $orderPaymentRepository
    ) {
        parent::__construct($scopeConfig, $moduleManager, $paymentHelper, $utility);

        $this->orderPaymentRepository = $orderPaymentRepository;
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
        $payment            = $magentoOrder->getPayment();

        $orderPayment = $this->orderPaymentRepository->get($payment->getEntityId());
        $this->orderPaymentRepository->delete($orderPayment);
        $payment->setData(
            [
                'method'                 => $this->getPaymentModel() ? $this->getPaymentModel()->getCode() : '',
                'additional_information' => $this->getAdditionalPaymentData($shopgateOrder),
                'transaction_id'         => $paymentInformation['transaction_id'],
                'cc_trans_id'            => $paymentInformation['transaction_id'],
                'last_trans_id'          => $paymentInformation['transaction_id']
            ]
        );

        $amount = $payment->formatAmount($shopgateOrder->getAmountComplete(), true);
        $payment->setBaseAmountAuthorized($amount);
        $payment->setAmountAuthorized($amount);
        $payment->setAmountBaseOrdered($amount);
        $payment->setAmountOrdered($amount);
        $payment->setShouldCloseParentTransaction(false);
        $payment->setIsTransactionClosed(0);
        $payment->registerAuthorizationNotification($shopgateOrder->getAmountComplete());
    }

    /**
     * @inheritDoc
     */
    public function getAdditionalPaymentData(ShopgateOrder $shopgateOrder): array
    {
        $paymentInformation    = $shopgateOrder->getPaymentInfos();
        $providerResponse      = $paymentInformation['provider_response'] ?? [];
        $additionalPaymentData = [
            'method_title'          => $paymentInformation['shopgate_payment_name'],
            'processorResponseCode' => $paymentInformation['processor_response_code'],
            'processorResponseText' => $paymentInformation['processor_response_text']
        ];

        if (isset($providerResponse['paymentId'])) {
            $additionalPaymentData['paymentId'] = $providerResponse['paymentId'];
        }
        if (isset($providerResponse['payerEmail'])) {
            $additionalPaymentData['payerEmail'] = $providerResponse['payerEmail'];
        }

        if (!empty($paymentInformation['risk_data'])) {
            $additionalPaymentData['riskDataDecision'] = $paymentInformation['risk_data']['decision'];
            $additionalPaymentData['riskDataId']       = $paymentInformation['risk_data']['id'];
        }

        return $additionalPaymentData;
    }
}
