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

namespace Shopgate\Import\Test\Integration\Model\Payment;

use Exception;
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
use Shopgate\Import\Test\Integration\Data\Payment\Braintree\CreditCard;
use ShopgateLibraryException;
use ShopgateOrder;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation  enabled
 * @magentoAppArea      frontend
 */
class CreditCardTest extends TestCase
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
     * @magentoConfigFixture current_store payment/braintree/active 1
     * @magentoConfigFixture current_store payment/braintree/payment_action authorize
     *
     * @throws ShopgateLibraryException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function testAuthorizeOnlyOrder(): void
    {
        $result = $this->importClass->addOrder($this->getShopgateOrder());
        /** @var MagentoOrder $magentoOrder */
        $magentoOrder = $this->orderRepository->get($result['external_order_id']);

        $authorisationTransaction = $this->transactionRepository
            ->getByTransactionType(Transaction::TYPE_AUTH, $magentoOrder->getPayment()->getEntityId());
        $captureTransaction       = $this->transactionRepository
            ->getByTransactionType(Transaction::TYPE_CAPTURE, $magentoOrder->getPayment()->getEntityId());

        $this->assertEquals('braintree', $magentoOrder->getPayment()->getMethod());
        $this->assertEquals(CreditCard::TRANSACTION_ID, $magentoOrder->getPayment()->getCcTransId());
        $this->assertEquals(CreditCard::TRANSACTION_ID, $magentoOrder->getPayment()->getLastTransId());
        $this->assertTrue($magentoOrder->getPayment()->canCapture());

        // auth only
        $this->assertFalse($captureTransaction);

        $this->assertEquals(0, $authorisationTransaction->getIsClosed());
        $this->assertEquals(CreditCard::TRANSACTION_ID, $authorisationTransaction->getData('txn_id'));
    }

    /**
     * @magentoConfigFixture current_store payment/braintree/active 1
     * @magentoConfigFixture current_store payment/braintree/payment_action authorize
     *
     * @throws ShopgateLibraryException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function testAuthorizeOnlyForCapturedOrder(): void
    {
        $result = $this->importClass->addOrder($this->getShopgateOrder(1));
        /** @var MagentoOrder $magentoOrder */
        $magentoOrder = $this->orderRepository->get($result['external_order_id']);

        $authorisationTransaction = $this->transactionRepository
            ->getByTransactionType(Transaction::TYPE_AUTH, $magentoOrder->getPayment()->getEntityId());
        $captureTransaction       = $this->transactionRepository
            ->getByTransactionType(Transaction::TYPE_CAPTURE, $magentoOrder->getPayment()->getEntityId());

        $this->assertEquals('braintree', $magentoOrder->getPayment()->getMethod());
        $this->assertEquals(CreditCard::TRANSACTION_ID, $magentoOrder->getPayment()->getCcTransId());
        $this->assertEquals(CreditCard::TRANSACTION_ID, $magentoOrder->getPayment()->getLastTransId());

        // auth only
        $this->assertFalse($authorisationTransaction);
        $this->assertEquals(0, $captureTransaction->getIsClosed());
        $this->assertEquals(CreditCard::TRANSACTION_ID, $captureTransaction->getData('txn_id'));
    }

    /**
     * @magentoConfigFixture current_store payment/braintree/active 1
     * @magentoConfigFixture current_store payment/braintree/payment_action capture
     *
     * @throws ShopgateLibraryException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function testCaptureOrderDuringImport(): void
    {
        $result = $this->importClass->addOrder($this->getShopgateOrder(1));
        /** @var MagentoOrder $magentoOrder */
        $magentoOrder = $this->orderRepository->get($result['external_order_id']);

        $authorisationTransaction = $this->transactionRepository
            ->getByTransactionType(Transaction::TYPE_AUTH, $magentoOrder->getPayment()->getEntityId());
        $captureTransaction       = $this->transactionRepository
            ->getByTransactionType(Transaction::TYPE_CAPTURE, $magentoOrder->getPayment()->getEntityId());

        $this->assertEquals('braintree', $magentoOrder->getPayment()->getMethod());
        $this->assertEquals(CreditCard::TRANSACTION_ID, $magentoOrder->getPayment()->getCcTransId());
        $this->assertEquals(CreditCard::TRANSACTION_ID, $magentoOrder->getPayment()->getLastTransId());

        $this->assertFalse($authorisationTransaction);

        $this->assertEquals(0, $captureTransaction->getIsClosed());
        $this->assertEquals(CreditCard::TRANSACTION_ID, $captureTransaction->getData('txn_id'));
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
                'payment_transaction_number' => (string) rand(1000000000, 9999999999),
                'mail'                       => 'shopgate@shopgate.com',
                'amount_shop_payment'        => '5.00',
                'amount_complete'            => '149.85',
                'shipping_infos'             => ['amount' => '4.90'],
                'invoice_address'            => $this->dataManager->getGermanAddress(),
                'delivery_address'           => $this->dataManager->getGermanAddress(false),
                'external_coupons'           => [],
                'shopgate_coupons'           => [],
                'items'                      => [$this->dataManager->getSimpleProduct()],
                'payment_infos'              => CreditCard::getAdditionalPayment(),
                'payment_method'             => 'BRAINTR_CC',
                'payment_group'              => 'CC'
            ]
        );
    }
}
