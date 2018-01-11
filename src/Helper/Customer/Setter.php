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

namespace Shopgate\Import\Helper\Customer;

use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Data\Customer as DataCustomer;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use ShopgateCustomer;
use ShopgateLibraryException;

class Setter
{
    /** @var StoreManagerInterface */
    protected $storeManager;
    /** @var CustomerFactory */
    protected $customerFactory;
    /** @var Utility */
    protected $utility;
    /** @var AddressFactory */
    protected $addressFactory;

    /**
     * @param StoreManagerInterface $storeManager
     * @param CustomerFactory       $customerFactory
     * @param AddressFactory        $addressFactory
     * @param Utility               $utility
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CustomerFactory $customerFactory,
        AddressFactory $addressFactory,
        Utility $utility
    ) {
        $this->storeManager    = $storeManager;
        $this->customerFactory = $customerFactory;
        $this->addressFactory  = $addressFactory;
        $this->utility         = $utility;
    }

    /**
     * @param string           $user
     * @param string           $pass
     * @param ShopgateCustomer $customer
     *
     * @throws ShopgateLibraryException
     * @throws \Exception
     */
    public function registerCustomer($user, $pass, ShopgateCustomer $customer)
    {
        try {
            $websiteId = $this->storeManager->getStore()->getWebsiteId();
            /** @var Customer | DataCustomer $magentoCustomer */
            $magentoCustomer = $this->customerFactory->create();
            $magentoCustomer->setWebsiteId($websiteId);
            $magentoCustomer->setEmail($user);
            $magentoCustomer->setPassword($pass);

            $this->utility->setBasicData($magentoCustomer, $customer);
            $this->utility->setAddressData($magentoCustomer, $customer);
        } catch (AlreadyExistsException $e) {
            throw new ShopgateLibraryException(ShopgateLibraryException::REGISTER_USER_ALREADY_EXISTS);
        } catch (LocalizedException $e) {
            throw new ShopgateLibraryException(ShopgateLibraryException::UNKNOWN_ERROR_CODE, $e->getMessage(), true);
        }
    }
}
