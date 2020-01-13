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

namespace Shopgate\Import\Test\Integration\Model\Service;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order as MageOrder;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Shopgate\Base\Tests\Integration\SgDataManager;
use Shopgate\Import\Helper\Order as ShopgateOrder;
use Shopgate\Import\Model\Service\Import;
use Shopgate\Import\Test\Integration\Data\Payment\Braintree\CreditCard;
use ShopgateLibraryException;

/**
 * @coversDefaultClass Import
 *
 * @magentoAppIsolation enabled
 * @magentoDbIsolation  enabled
 * @magentoAppArea      frontend
 */
class ImportOrderTest extends TestCase
{
    /** @var ShopgateOrder */
    private $orderClass;
    /** @var Import */
    private $importClass;
    /** @var array - list of created orders to clean up */
    private $orderHolder = [];
    /** @var OrderRepositoryInterface */
    private $orderRepository;
    /** @var SgDataManager */
    private $dataManager;

    public function setUp(): void
    {
        $objectManager         = Bootstrap::getObjectManager();
        $this->importClass     = $objectManager->create(Import::class);
        $this->orderClass      = $objectManager->create(ShopgateOrder::class);
        $this->orderRepository = $objectManager->create(OrderRepositoryInterface::class);
        $this->dataManager     = $objectManager->create(SgDataManager::class);
    }

    /**
     * Test that all 3 product types get inserted
     * into the order and that order is created
     * correctly
     *
     * @param \ShopgateOrder $order
     *
     * @dataProvider simpleOrderProvider
     * @throws ShopgateLibraryException
     */
    public function testOrderImport(\ShopgateOrder $order): void
    {
        $result = $this->importClass->addOrder($order);
        /** @var ShopgateOrder $sgOrder */
        $sgOrder = Bootstrap::getObjectManager()->get(ShopgateOrder::class);
        /** @var MageOrder $order */
        $order = $sgOrder->loadMethods([]);

        $this->assertNotEmpty($result);
        $this->assertCount(3, $order->getAllVisibleItems());
        $this->orderHolder[] = $result;
    }

    /**
     * @param string      $expectedPaymentCode
     * @param array       $paymentInformation
     * @param string      $paymentMethod
     * @param string      $paymentGroup
     * @param string|null $cartType
     *
     * @throws Exception
     * @throws ShopgateLibraryException
     * @throws Exception
     *
     * @dataProvider         paymentDataProvider
     * @magentoConfigFixture default/payment/shopgate/order_status processing
     * @magentoConfigFixture current_store payment/braintree/active 1
     */
    public function testPaymentMapping(
        string $expectedPaymentCode,
        array $paymentInformation,
        string $paymentMethod,
        string $paymentGroup,
        $cartType
    ): void {
        $shopgateOrder = new \ShopgateOrder(
            [
                'order_number'               => random_int(1000000000, 9999999999),
                'is_paid'                    => 1,
                'payment_time'               => null,
                'payment_transaction_number' => '8654415',
                'mail'                       => 'shopgate@shopgate.com',
                'amount_shop_payment'        => '5.00',
                'amount_complete'            => '149.85',
                'shipping_infos'             => ['amount' => '4.90'],
                'invoice_address'            => $this->dataManager->getGermanAddress(),
                'delivery_address'           => $this->dataManager->getGermanAddress(false),
                'external_coupons'           => [],
                'shopgate_coupons'           => [],
                'items'                      => [$this->dataManager->getSimpleProduct()],
                'payment_infos'              => $paymentInformation,
                'payment_method'             => $paymentMethod,
                'payment_group'              => $paymentGroup
            ]
        );

        $this->orderHolder[] = $this->importClass->addOrder($shopgateOrder);
        /** @var ShopgateOrder $sgOrder */
        $sgOrder = Bootstrap::getObjectManager()->get(ShopgateOrder::class);
        /** @var MageOrder $order */
        $order = $sgOrder->loadMethods([]);

        $this->assertEquals($expectedPaymentCode, $order->getPayment()->getMethod());
        $this->assertEquals($cartType, $order->getPayment()->getCcType());
    }

    /**
     * @return array
     */
    public function paymentDataProvider(): array
    {
        return [
            'Braintree Credit Card - visa'     => [
                'braintree',
                CreditCard::getAdditionalPayment('visa'),
                'BRAINTR_CC',
                'CC',
                'VI'
            ],
            'Braintree Credit Card - maestro'     => [
                'braintree',
                CreditCard::getAdditionalPayment('maestro'),
                'BRAINTR_CC',
                'CC',
                'MI'
            ],
            'Braintree Credit Card - mastercard'     => [
                'braintree',
                CreditCard::getAdditionalPayment('mastercard'),
                'BRAINTR_CC',
                'CC',
                'MC'
            ],
            'Braintree Credit Card - discover'     => [
                'braintree',
                CreditCard::getAdditionalPayment('discover'),
                'BRAINTR_CC',
                'CC',
                'DI'
            ],
            'Braintree Credit Card - amex'     => [
                'braintree',
                CreditCard::getAdditionalPayment('amex'),
                'BRAINTR_CC',
                'CC',
                'AE'
            ],
            'Braintree Credit Card - jcb'     => [
                'braintree',
                CreditCard::getAdditionalPayment('jcb'),
                'BRAINTR_CC',
                'CC',
                'JCB'
            ],
            'Braintree Credit Card - unionpay'     => [
                'braintree',
                CreditCard::getAdditionalPayment('unionpay'),
                'BRAINTR_CC',
                'CC',
                'CUP'
            ],
            'Braintree Credit Card - american_express'     => [
                'braintree',
                CreditCard::getAdditionalPayment('american_express'),
                'BRAINTR_CC',
                'CC',
                'AE'
            ],
            'Not mapped payment method' => [
                'shopgate',
                [
                    'shopgate_payment_name' => 'Unknown',
                    'status'                => 'authorized'
                ],
                'SOMETHING_UNKNOWN',
                'NEW',
                null
            ]
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function simpleOrderProvider(): array
    {
        return [
            'simple order' => [
                new \ShopgateOrder(
                    [
                        'order_number'        => random_int(1000000000, 9999999999),
                        'is_paid'             => 0,
                        'mail'                => 'shopgate@shopgate.com',
                        'amount_shop_payment' => '5.00',
                        'amount_complete'     => '149.85',
                        'shipping_infos'      => [
                            'amount' => '4.90',
                        ],
                        'invoice_address'     => $this->dataManager->getGermanAddress(),
                        'delivery_address'    => $this->dataManager->getGermanAddress(false),
                        'external_coupons'    => [],
                        'shopgate_coupons'    => [],
                        'items'               => [
                            $this->dataManager->getSimpleProduct(),
                            $this->dataManager->getConfigurableProduct(),
                            $this->dataManager->getGroupedProduct()
                        ]
                    ]
                )
            ],
        ];
    }

    /**
     * Delete all created orders & quotes
     *
     * @throws NoSuchEntityException
     */
    public function tearDown(): void
    {
        /** @var Registry $registry */
        $registry = Bootstrap::getObjectManager()->get(Registry::class);
        $registry->register('isSecureArea', true, true);
        /** @var QuoteRepository $quoteRepo */
        $quoteRepo = Bootstrap::getObjectManager()->create(QuoteRepository::class);

        foreach ($this->orderHolder as $order) {
            if (isset($order['external_order_id'])) {
                $order = $this->orderRepository->get($order['external_order_id']);
                $quoteRepo->delete($quoteRepo->get($order->getQuoteId()));
                $this->orderRepository->delete($order);
            }
        }
    }
}
