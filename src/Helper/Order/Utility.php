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

namespace Shopgate\Import\Helper\Order;

use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as StatusCollectionFactory;

class Utility
{
    /** @var StatusCollectionFactory */
    private $statusCollectionFactory;

    /**
     * @param StatusCollectionFactory $statusCollectionFactory
     */
    public function __construct(
        StatusCollectionFactory $statusCollectionFactory
    ) {
        $this->statusCollectionFactory = $statusCollectionFactory;
    }

    /**
     * Returns the state for the given status
     *
     * @param string $status
     *
     * @return string
     */
    public function getStateForStatus($status)
    {
        $statusCollection = $this->statusCollectionFactory->create();
        $statusCollection->joinStates();
        $statusCollection->getSelect()->where('state_table.status=?', $status);
        $statusCollection->getSelect()->where('state_table.is_default=?', 1);
        $state = $statusCollection->getFirstItem();

        return $state->getData('state');
    }
}
