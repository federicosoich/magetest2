<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_BetterPopup
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\BetterPopup\Plugin\Controller\Subscriber;

use Magento\Customer\Api\AccountManagementInterface as CustomerAccountManagement;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\BetterPopup\Helper\Data;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\AddressFactory as CustomerAddressFactory;
use Mageplaza\GeoIP\Helper\Address;

/**
 * Class NewAction
 * @package Mageplaza\BetterPopup\Plugin\Controller\Subscriber
 */
class NewAction extends \Magento\Newsletter\Controller\Subscriber\NewAction
{
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Mageplaza\BetterPopup\Helper\Data
     */
    protected $_helperData;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Magento\Customer\Model\CustomerAddress
     */
    protected $customerAddressFactory;

    /**
     * @var \Mageplaza\GeoIP\Helper\Address
     */
    protected $_helperAddress;



    /**
     * NewAction constructor.
     * @param Context $context
     * @param SubscriberFactory $subscriberFactory
     * @param Session $customerSession
     * @param StoreManagerInterface $storeManager
     * @param CustomerUrl $customerUrl
     * @param CustomerAccountManagement $customerAccountManagement
     * @param JsonFactory $resultJsonFactory
     * @param Data $helperData
     */
    public function __construct(
        Context $context,
        SubscriberFactory $subscriberFactory,
        Session $customerSession,
        StoreManagerInterface $storeManager,
        CustomerUrl $customerUrl,
        CustomerAccountManagement $customerAccountManagement,
        JsonFactory $resultJsonFactory,
        Data $helperData,
        CustomerFactory $customerFactory,
        CustomerAddressFactory $customerAddressFactory,
        Address $helperAddress
        
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_helperData       = $helperData;
        $this->customerFactory = $customerFactory;
        $this->_helperAddress       = $helperAddress;
        parent::__construct($context, $subscriberFactory, $customerSession, $storeManager, $customerUrl, $customerAccountManagement);
    }

    /**
     * @param $subject
     * @param $proceed
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function aroundExecute($subject, $proceed)
    {
        if (!$this->_helperData->isEnabled() || !$this->getRequest()->isAjax()) {
            return $proceed();
        }

        $response = [];
        if ($this->getRequest()->isPost() && $this->getRequest()->getPost('email')) {
            $email = (string)$this->getRequest()->getPost('email');
            $url = (string)$this->getRequest()->getPost('url');
            $aux=explode('?', $email);
            $email=$aux[0];
            $aux2=explode('#', $aux[1]);
            $name=$aux2[0];
            $phone=$aux2[1];
            //using Mageplaza GeoIP
            $arrayaddress=$this->_helperAddress->getGeoIpData();

            try {
                $this->validateEmailFormat($email);
                $this->validateGuestSubscription();
                $this->validateEmailAvailable($email);

                $this->_subscriberFactory->create()->subscribe($email);
                $customer=$this->customerFactory->create();
                $customer->setWebsiteId($websiteId);
                $customer->setEmail($email);
                $customer->setFirstname($name);
                $customer->setLastname($name);
                $customer->setPassword("123456789");
                $customer->save();

                //Create Customer address
                $customerAddress=$this->customerAddressFactory->create();
                $customerAddress->setCustomerId($customer->getId())
                ->setFirstname($name)
                ->setLastname($name)
                ->setCountryId($arrayaddress['country_id'])  
                ->setPostcode($arrayaddress['postcode'])
                ->setCity($arrayaddress['city'])
                ->setTelephone($phone)  
                ->setIsDefaultBilling('1')
                ->setIsDefaultShipping('1')
                ->setSaveInAddressBook('1');
                $customerAddress->save();

                if (!$this->_helperData->versionCompare('2.2.0')) {
                    $this->_subscriberFactory->create()->loadByEmail($email)->setChangeStatusAt(date("Y-m-d h:i:s"))->save();
                }
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $response = [
                    'success' => true,
                    'msg'     => __('There was a problem with the subscription: %1', $e->getMessage()),
                ];
            } catch (\Exception $e) {
                $response = [
                    'status' => 'ERROR',
                    'msg'    => __('Something went wrong with the subscription: %1', $e->getMessage()),
                ];
            }
        }

        return $this->resultJsonFactory->create()->setData($response);
    }
}