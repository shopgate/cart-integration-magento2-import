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

namespace Shopgate\Import\Api;

interface ImportInterface extends \Shopgate\Base\Api\ImportInterface
{
    const SECTION_IMPORT                  = 'shopgate_import';
    const PATH_ORDER                      = self::SECTION_IMPORT . '/order';
    const PATH_SEND_NEW_ORDER_MAIL        = self::PATH_ORDER . '/send_new_order_mail';
    const PATH_SG_ORDER_STATUS            = 'payment/shopgate/order_status';
    const PATH_FIX_ORDER_TOTALS           = self::PATH_ORDER . '/fix_totals_active';
    const PATH_FIX_ORDER_TOTALS_THRESHOLD = self::PATH_ORDER . '/fix_totals_threshold';
}
