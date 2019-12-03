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

namespace Shopgate\Import\Test\Integration\Model\Payment\Braintree;

use Exception;
use Magento\Braintree\Model\Ui\PayPal\ConfigProvider;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;
use Magento\Sales\Model\OrderRepository;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Shopgate\Base\Tests\Integration\SgDataManager;
use Shopgate\Import\Model\Service\Import;
use Shopgate\Import\Test\Integration\Data\Payment\Braintree\PayPal;
use ShopgateLibraryException;
use ShopgateOrder;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation  enabled
 * @magentoAppArea      frontend
 */
class PayPalTest extends TestCase
{
    /** @var ObjectManager $objectManager */
    private $objectManager;
    /** @var Import */
    private $importClass;
    /** @var SgDataManager */
    private $dataManager;
    /** @var OrderRepository */
    private $orderRepository;
    /** @var TransactionRepository */
    private $transactionRepository;

    /**
     * Integration test preparation
     */
    public function setUp()
    {
        $this->objectManager         = Bootstrap::getObjectManager();
        $this->importClass           = $this->objectManager->create(Import::class);
        $this->orderRepository       = $this->objectManager->create(OrderRepository::class);
        $this->transactionRepository = $this->objectManager->create(TransactionRepository::class);
        $this->dataManager           = $this->objectManager->create(SgDataManager::class);
    }

    /**
     * @magentoConfigFixture current_store payment/braintree_paypal/active 1
     * @magentoConfigFixture current_store payment/braintree_paypal/payment_action authorize
     *
     * @throws Exception
     * @throws ShopgateLibraryException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function testAuthorizeOnlyOrder(): void
    {
        $result = $this->importClass->addOrder($this->getShopgateOrder());
        /** @var MagentoOrder $magentoOrder */
        $magentoOrder = $this->orderRepository->get($result['external_order_id']);

        $this->assertEquals(ConfigProvider::PAYPAL_CODE, $magentoOrder->getPayment()->getMethod());
        $this->assertEquals(PayPal::TRANSACTION_ID, $magentoOrder->getPayment()->getCcTransId());
        $this->assertEquals(PayPal::TRANSACTION_ID, $magentoOrder->getPayment()->getLastTransId());
        $this->assertTrue($magentoOrder->getPayment()->canCapture());

        $this->validateTransaction((string) $magentoOrder->getPayment()->getEntityId(), Transaction::TYPE_AUTH);
    }

    /**
     * @param int $isPaid
     *
     * @return ShopgateOrder
     *
     * @throws Exception
     */
    private function getShopgateOrder(int $isPaid = 0): ShopgateOrder
    {
        return new ShopgateOrder(
            [
                'order_number'               => random_int(1000000000, 9999999999),
                'is_paid'                    => $isPaid,
                'payment_time'               => null,
                'payment_transaction_number' => (string) random_int(1000000000, 9999999999),
                'mail'                       => 'shopgate@shopgate.com',
                'amount_shop_payment'        => '5.00',
                'amount_complete'            => '149.85',
                'shipping_infos'             => ['amount' => '4.90'],
                'invoice_address'            => $this->dataManager->getGermanAddress(),
                'delivery_address'           => $this->dataManager->getGermanAddress(false),
                'external_coupons'           => [],
                'shopgate_coupons'           => [],
                'items'                      => [$this->dataManager->getSimpleProduct()],
                'payment_infos'              => PayPal::getAdditionalPayment(),
                'payment_method'             => 'BRAINTR_PP',
                'payment_group'              => 'PAYPAL'
            ]
        );
    }

    /**
     * @magentoConfigFixture current_store payment/braintree_paypal/active 1
     * @magentoConfigFixture current_store payment/braintree_paypal/payment_action authorize
     *
     * @throws Exception
     * @throws ShopgateLibraryException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function testAuthorizeOnlyForCapturedOrder(): void
    {
        $result = $this->importClass->addOrder($this->getShopgateOrder(1));
        /** @var MagentoOrder $magentoOrder */
        $magentoOrder = $this->orderRepository->get($result['external_order_id']);

        $this->assertEquals(ConfigProvider::PAYPAL_CODE, $magentoOrder->getPayment()->getMethod());
        $this->assertEquals(PayPal::TRANSACTION_ID, $magentoOrder->getPayment()->getCcTransId());
        $this->assertEquals(PayPal::TRANSACTION_ID, $magentoOrder->getPayment()->getLastTransId());

        $this->validateTransaction((string) $magentoOrder->getPayment()->getEntityId(), Transaction::TYPE_CAPTURE);
    }

    /**
     * @magentoConfigFixture current_store payment/braintree_paypal/active 1
     * @magentoConfigFixture current_store payment/braintree_paypal/payment_action authorize_capture
     *
     * @throws Exception
     * @throws ShopgateLibraryException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function testCaptureOrderDuringImport(): void
    {
        $result = $this->importClass->addOrder($this->getShopgateOrder(1));
        /** @var MagentoOrder $magentoOrder */
        $magentoOrder = $this->orderRepository->get($result['external_order_id']);

        $this->assertEquals(ConfigProvider::PAYPAL_CODE, $magentoOrder->getPayment()->getMethod());
        $this->assertEquals(PayPal::TRANSACTION_ID, $magentoOrder->getPayment()->getCcTransId());
        $this->assertEquals(PayPal::TRANSACTION_ID, $magentoOrder->getPayment()->getLastTransId());

        $this->validateTransaction((string) $magentoOrder->getPayment()->getEntityId(), Transaction::TYPE_CAPTURE);
    }

    /**
     * @param string $paymentId
     * @param string $transactionType
     *
     * @throws InputException
     */
    private function validateTransaction(string $paymentId, string $transactionType): void
    {
        $authorisationTransaction = $this->transactionRepository
            ->getByTransactionType(Transaction::TYPE_AUTH, $paymentId);
        $captureTransaction       = $this->transactionRepository
            ->getByTransactionType(Transaction::TYPE_CAPTURE, $paymentId);

        $transactionToTest = $transactionType === Transaction::TYPE_AUTH
            ? $authorisationTransaction
            : $captureTransaction;

        $transactionToMiss = $transactionType === Transaction::TYPE_AUTH
            ? $captureTransaction
            : $authorisationTransaction;

        $this->assertFalse($transactionToMiss);
        $this->assertEquals(0, $transactionToTest->getIsClosed());
        $this->assertEquals(PayPal::TRANSACTION_ID, $transactionToTest->getData('txn_id'));
    }
}
