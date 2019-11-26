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

namespace Shopgate\Import\Test\Integration\Model\Service;

use Magento\Customer\Model\CustomerFactory;
use Magento\Store\Model\ScopeInterface;
use Shopgate\Base\Api\Config\SgCoreInterface;
use ShopgateAddress;
use ShopgateCustomer;
use ShopgateOrderCustomField;

/**
 * @coversDefaultClass \Shopgate\Import\Model\Service\Import
 */
class ImportTest extends \PHPUnit\Framework\TestCase
{
    const CUSTOMER_EMAIL = 'example@me.com';
    const SHOP_NUMBER    = '12345';
    const WEBSITE_ID     = '1';

    /** @var CustomerFactory */
    protected $customerFactory;
    /** @var \Shopgate\Base\Tests\Integration\Db\ConfigManager */
    protected $cfgManager;
    /** @var \Magento\Customer\Model\Customer[] */
    protected $customers;
    /** @var \Shopgate\Import\Model\Service\Import */
    private $importClass;

    /**
     * Load object manager for initialization
     */
    public function setUp()
    {
        $objectManager         = \Shopgate\Base\Tests\Bootstrap::getObjectManager();
        $this->cfgManager      = new \Shopgate\Base\Tests\Integration\Db\ConfigManager;
        $this->importClass     = $objectManager->create('Shopgate\Import\Model\Service\Import');
        $this->customerFactory = $objectManager->create('Magento\Customer\Model\CustomerFactory');
    }

    /**
     * Test that we can create a customer with addresses
     *
     * @covers ::registerCustomer
     */
    public function testRegisterCustomer()
    {
        $shopNumber = '12345';
        $this->cfgManager->setConfigValue(
            SgCoreInterface::PATH_SHOP_NUMBER,
            $shopNumber,
            ScopeInterface::SCOPE_WEBSITES,
            self::WEBSITE_ID
        );

        /** @var ShopgateCustomer $shopgateInputCustomer */
        $shopgateInputCustomer = $this->createShopgateCustomer();
        $this->importClass->registerCustomer(
            'register_customer',
            self::SHOP_NUMBER,
            self::CUSTOMER_EMAIL,
            '123456kill',
            false,
            $shopgateInputCustomer->toArray()
        );

        $customer = $this->customerFactory->create()->setWebsiteId(self::WEBSITE_ID)->loadByEmail(self::CUSTOMER_EMAIL);

        $this->assertEquals(self::CUSTOMER_EMAIL, $customer->getEmail());
    }

    /**
     * @return ShopgateCustomer
     */
    private function createShopgateCustomer()
    {
        $customer = new ShopgateCustomer();

        /**
         * global data
         */
        $customer->setFirstName('Max');
        $customer->setLastName('Mustermann');
        $customer->setGender('Male');
        $customer->setBirthday('2000-02-02');

        /**
         * custom field
         */
        $customField = new ShopgateOrderCustomField();
        $customField->setLabel('Custom one');
        $customField->setInternalFieldName('custom_one');
        $customField->setValue('custom value');

        $customer->setCustomFields(
            [$customField]
        );

        /**
         * address
         */
        $address = new ShopgateAddress();
        $address->setFirstName('Sam');
        $address->setLastName('Mustermann');
        $address->setCity('Hackpfüffel');
        $address->setCountry('DE');
        $address->setState('SA');
        $address->setZipcode('123456');
        $address->setStreet1('Am Stadion 4');
        $address->setMobile('123456789');

        $customer->setAddresses(
            [$address]
        );

        /**
         * custom address field
         */
        $customAddressField = new ShopgateOrderCustomField();
        $customAddressField->setLabel('Custom address');
        $customAddressField->setInternalFieldName('custom_address');
        $customAddressField->setValue('custom address value');

        $address->setCustomFields(
            [$customAddressField]
        );

        return $customer;
    }

    /**
     * Remove created object in test
     *
     * @throws \Exception
     */
    public function tearDown()
    {
        /** @var \Magento\Framework\Registry $registry */
        $registry = \Shopgate\Base\Tests\Bootstrap::getObjectManager()->get('\Magento\Framework\Registry');
        $registry->register('isSecureArea', true, true);

        $customer = $this->customerFactory->create()->setWebsiteId(self::WEBSITE_ID)->loadByEmail(self::CUSTOMER_EMAIL);
        if ($customer->getId()) {
            $customer->delete();
        }
    }
}
