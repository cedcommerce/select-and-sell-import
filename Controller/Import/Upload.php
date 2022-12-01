<?php
/**
 * CedCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the End User License Agreement (EULA)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://cedcommerce.com/license-agreement.txt
 *
 * @category  Ced
 * @package   Ced_CsMultiSellerImportExport
 * @author    CedCommerce Core Team <connect@cedcommerce.com >
 * @copyright Copyright CedCommerce (https://cedcommerce.com/)
 * @license      https://cedcommerce.com/license-agreement.txt
 */

namespace Ced\CsMultiSellerImportExport\Controller\Import;

use Magento\Backend\App\Action;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\UrlFactory;

/**
 * Class Upload
 * @package Ced\CsMultiSellerImportExport\Controller\Import
 */
class Upload extends \Ced\CsMarketplace\Controller\Vendor
{

    /**
     * @var \Ced\CsMultiSellerImportExport\Helper\Uploader
     */
    protected $uploaderHelper;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $jsonEncoder;

    public function __construct(
        \Ced\CsMultiSellerImportExport\Helper\Uploader $uploaderHelper,
        \Magento\Framework\Serialize\Serializer\Json $jsonEncoder,
        Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        Session $customerSession,
        UrlFactory $urlFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Ced\CsMarketplace\Helper\Data $csmarketplaceHelper,
        \Ced\CsMarketplace\Helper\Acl $aclHelper,
        \Ced\CsMarketplace\Model\VendorFactory $vendor
    )
    {
        parent::__construct(
            $context,
            $resultPageFactory,
            $customerSession,
            $urlFactory,
            $registry,
            $jsonFactory,
            $csmarketplaceHelper,
            $aclHelper,
            $vendor
        );
        $this->uploaderHelper = $uploaderHelper;
        $this->jsonEncoder = $jsonEncoder;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        $action = $this->getRequest()->getParam('action');
        $return = ['success' => false];

        if (!empty($action)) {
            switch ($action) {
                case 'upload':
                    $return = $this->uploadCsv();
                    break;

                case 'delete':
                    $return = $this->deleteFile();
                    break;
            }
        }

        $this->getResponse()->setBody($this->jsonEncoder->serialize($return));
    }

    /**
     * @return array|bool
     */
    public function uploadCsv()
    {
        $uploadResult = false;
        $file = $this->getRequest()->getFiles();
        foreach ($file as $fileId) {
            $uploadResult = $this->uploaderHelper->csvUploader($fileId);
        }
        return $uploadResult;
    }

    /**
     * @return bool
     */
    protected function deleteFile()
    {
        $path = $this->getRequest()->getParam('path');
        $result = $this->uploaderHelper->deleteFile($path);
        return $result;
    }
}
