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

namespace Shopgate\Import\Model\Payment\Magento;

use Shopgate\Import\Model\Payment\AbstractPayment;

class CashOnDelivery extends AbstractPayment
{
    protected const MODULE_NAME             = 'Magento_OfflinePayments';
    protected const PAYMENT_CODE            = 'cashondelivery';
    protected const XML_CONFIG_ORDER_STATUS = 'payment/cashondelivery/order_status';
    protected const XML_CONFIG_ENABLED      = 'payment/cashondelivery/active';
}
