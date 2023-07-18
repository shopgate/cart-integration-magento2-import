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

use Exception;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Data\Customer as DataCustomer;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Directory\Model\CountryFactory;
use Magento\Tax\Model\ResourceModel\TaxClass\Collection as TaxClassCollection;
use Shopgate\Base\Helper\Gender;
use Shopgate\Base\Helper\Regions;
use Shopgate\Base\Helper\Shopgate\Customer as CustomerHelper;
use ShopgateCustomer;

class Utility extends \Shopgate\Base\Helper\Customer\Utility
{
    /** @var AddressFactory */
    private $addressFactory;
    /** @var CustomerHelper */
    private $customerHelper;

    /**
     * @param GroupCollection    $customerGroupCollection
     * @param TaxClassCollection $taxCollection
     * @param CountryFactory     $countryFactory
     * @param AddressFactory     $addressFactory
     * @param CustomerHelper     $customer
     * @param Regions            $regions
     * @param Gender             $genderHelper
     */
    public function __construct(
        GroupCollection $customerGroupCollection,
        TaxClassCollection $taxCollection,
        CountryFactory $countryFactory,
        AddressFactory $addressFactory,
        CustomerHelper $customer,
        Regions $regions,
        Gender $genderHelper
    ) {
        $this->addressFactory = $addressFactory;
        $this->customerHelper = $customer;
        parent::__construct($customerGroupCollection, $taxCollection, $countryFactory, $regions, $genderHelper);
    }

    /**
     * @param Customer | DataCustomer $magentoCustomer
     * @param ShopgateCustomer        $customer
     *
     * @throws Exception
     */
    public function setBasicData($magentoCustomer, $customer)
    {
        $customFields = $this->customerHelper->getCustomFields($customer);
        $magentoCustomer->setConfirmation(null);
        $magentoCustomer->setFirstname($customer->getFirstName());
        $magentoCustomer->setLastname($customer->getLastName());
        $magentoCustomer->setGender($this->getMagentoGender($customer->getGender()));
        $magentoCustomer->setDob($customer->getBirthday());
        $magentoCustomer->addData($customFields);

        $prefix = $this->customerHelper->getMagentoPrefix($customer->getGender());
        if ($prefix !== null) {
            $magentoCustomer->setPrefix($prefix);
        }

        $magentoCustomer->save();
    }

    /**
     * @param Customer | DataCustomer $magentoCustomer
     * @param ShopgateCustomer        $customer
     *
     * @throws Exception
     */
    public function setAddressData($magentoCustomer, $customer)
    {
        foreach ($customer->getAddresses() as $shopgateAddress) {
            /** @var Address $magentoAddress */
            $magentoAddress = $this->addressFactory->create();
            $magentoAddress->setCustomerId($magentoCustomer->getId());
            $data = $this->customerHelper->createAddressData($customer, $shopgateAddress, $magentoCustomer->getId());
            $magentoAddress->addData($data);
            $magentoAddress->save();

            if ($shopgateAddress->getIsDeliveryAddress() && !$magentoCustomer->getDefaultShipping()) {
                $magentoCustomer->setDefaultShipping($magentoAddress->getId());
                $magentoCustomer->save();
            }

            if ($shopgateAddress->getIsInvoiceAddress() && !$magentoCustomer->getDefaultBilling()) {
                $magentoCustomer->setDefaultBilling($magentoAddress->getId());
                $magentoCustomer->save();
            }
        }
    }
}
