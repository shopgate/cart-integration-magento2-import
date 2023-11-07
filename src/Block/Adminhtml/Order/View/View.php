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

namespace Shopgate\Import\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Shopgate\Base\Api\Data\OrderInterface;
use Shopgate\Base\Helper\Encoder;
use Shopgate\Base\Model\Shopgate\Order;
use Shopgate\Base\Model\Shopgate\OrderRepository;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Import\Block\Adminhtml\Order\DataHydrator;

class View extends Template
{

    /**
     * @var OrderRepository
     */
    private $orderRepository;
    /**
     * @var array
     */
    private $whitelist;
    /**
     * @var Encoder
     */
    private $jsonDecoder;
    /** @var null|OrderInterface|Order */
    private $shopgateOrder = null;
    /** @var SgLoggerInterface */
    private $logger;

    /**
     * @param OrderRepository $orderRepository
     * @param Context $context
     * @param Encoder $jsonDecoder
     * @param SgLoggerInterface $logger
     * @param array $whitelist - our whitelisted data to print, see di.xml
     * @param array $data
     */
    public function __construct(
        OrderRepository $orderRepository,
        Template\Context $context,
        Encoder $jsonDecoder,
        SgLoggerInterface $logger,
        array $whitelist = [],
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->orderRepository = $orderRepository;
        $this->whitelist = $whitelist;
        $this->jsonDecoder = $jsonDecoder;
        $this->logger = $logger;
    }

    /**
     * Checks if it's a Shopgate order
     *
     * @return bool
     */
    public function isShopgateOrder(): bool
    {
        return $this->getShopgateOrder() && !$this->getShopgateOrder()->isEmpty();
    }

    /**
     * Returns clean payment info
     *
     * @return array
     */
    public function getPaymentInfos(): array
    {
        $payment = $this->jsonDecoder->decode($this->getShopgateOrder()->getReceivedData())['payment_infos'] ?? [];

        return (new DataHydrator($payment))
            ->filterWhitelist($this->whitelist['payment_infos'] ?? [])
            ->removeEmpty()
            ->readableKeys()
            ->getData();
    }

    /**
     * Prints all payment info
     *
     * @return string
     */
    public function printPaymentInfo(): string
    {
        $paymentInfo = $this->getPaymentInfos();
        if (!$paymentInfo) {
            return '';
        }

        return $this->printList($paymentInfo);
    }

    /**
     * Prints lists under the main one
     *
     * @param array $list
     * @param ?string $title - meant for sub-lists only, check <li> tags
     * @return string
     */
    private function printList(array $list, ?string $title = null): string
    {
        $html = $title ? "<li><strong>$title</strong>" : ''; // if sublist
        $html .= '<ul>';
        foreach ($list as $key => $value) {
            if (is_array($value)) {
                $html .= $this->printList($value, $key);
            } else {
                $html .= "<li><strong>$key</strong>: $value</li>";
            }
        }
        $html .= '</ul>';
        $html .= $title ? '</li>' : '';

        return $html;
    }

    /**
     * Retrieves SG order from DB
     *
     * @return null|OrderInterface|Order
     */
    private function getShopgateOrder()
    {
        $id = $this->getRequest()->getParam('order_id');

        if ($this->shopgateOrder === null) {
            try {
                $this->shopgateOrder = $this->orderRepository->getByMageOrder($id);
            } catch (LocalizedException $e) {
                $this->logger->error('Mage Order View error: '. $e->getMessage());
            }
        }

        return $this->shopgateOrder;
    }
}
