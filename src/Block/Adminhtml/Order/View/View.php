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
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Shopgate\Base\Api\Data\OrderInterface;
use Shopgate\Base\Helper\Encoder;
use Shopgate\Base\Model\Shopgate\Order;
use Shopgate\Base\Model\Shopgate\OrderRepository;
use Shopgate\Import\Block\Adminhtml\Order\DataHydrator;

class View extends Template
{

    private $orderRepository;
    private $whitelist;

    private $jsonDecoder;

    public function __construct(
        OrderRepository $orderRepository,
        Template\Context $context,
        Encoder $jsonDecoder,
        array $whitelist = [],
        array $data = [],
        ?JsonHelper $jsonHelper = null,
        ?DirectoryHelper $directoryHelper = null
    ) {
        parent::__construct($context, $data, $jsonHelper, $directoryHelper);
        $this->orderRepository = $orderRepository;
        $this->whitelist = $whitelist;
        $this->jsonDecoder = $jsonDecoder;
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
     * Prints all teh data
     *
     * @return string
     */
    public function printData(): string
    {
        $paymentInfo = $this->getPaymentInfos();
        if (!$paymentInfo) {
            return '';
        }

        return $this->printSectionItem('Payment Information', $paymentInfo);
    }

    /**
     * Print section
     *
     * @param string $title
     * @param array $list
     * @return string
     */
    private function printSectionItem(string $title, array $list): string
    {
        $html = "
            <div class='admin__page-section-item'>
                    <div class='admin__page-section-item-title'>
                        <span class='title'>$title</span>
                    </div>
                    <div class='admin__page-section-item-content'>
                        <ul>";
        foreach ($list as $key => $value) {
            if (is_array($value)) {
                $html .= $this->printSubList($key, $value);
            } else {
                $html .= "<li><strong>$key</strong>: $value</li>";
            }
        }
        $html .= '</ul></div></div>';

        return $html;
    }

    /**
     * Prints lists under the main one
     *
     * @param string $title
     * @param array $list
     * @return string
     */
    private function printSubList(string $title, array $list): string
    {
        $html = "<li><strong>$title</strong><ul>";
        foreach ($list as $key => $value) {
            if (is_array($value)) {
                $html .= $this->printSubList($key, $value);
            } else {
                $html .= "<li><strong>$key</strong>: $value</li>";
            }
        }
        $html .= '</ul></li>';

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

        try {
            return $this->orderRepository->getByMageOrder($id);
        } catch (LocalizedException $e) {
            return null;
        }
    }
}
