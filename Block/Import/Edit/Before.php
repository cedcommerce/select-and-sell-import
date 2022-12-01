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

namespace Ced\CsMultiSellerImportExport\Block\Import\Edit;

/**
 * Class Before
 * @package Ced\CsMultiSellerImportExport\Block\Import\Edit
 */
class Before extends \Magento\Backend\Block\Template
{

    const URL_PATH_UPLOAD_IMPORT_FILE = 'csmultisellerimport/import/upload';
    const URL_PATH_VALIDATE_FILE = 'csmultisellerimport/import/validate';
    const URL_PATH_EXPORT_VENDORS_CSV = 'csmultisellerimport/import/exportcsvformat';
    const URL_PATH_IMPORT_VENDORS_CSV = 'csmultisellerimport/import/save';
    const URL_PATH_REDIRECT = 'csmultiseller/product/index/';
    const URL_PATH_BACK = 'csmultiseller/product/new';

    /**
     * @var \Ced\CsMultiSellerImportExport\Helper\File
     */
    protected $fileHelper;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $jsonEncoder;

    /**
     * Before constructor.
     * @param \Ced\CsMultiSellerImportExport\Helper\File $fileHelper
     * @param \Magento\Framework\Serialize\Serializer\Json $jsonEncoder
     * @param \Magento\Backend\Block\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Ced\CsMultiSellerImportExport\Helper\File $fileHelper,
        \Magento\Framework\Serialize\Serializer\Json $jsonEncoder,
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        $this->fileHelper = $fileHelper;
        $this->jsonEncoder = $jsonEncoder;
        parent::__construct($context, $data);
    }

    protected function _construct()
    {
        parent::_construct();
        $this->setData('area', 'adminhtml');
    }

    /**
     * @return float
     */
    public function getMaxSize()
    {
        return $this->fileHelper->getMaxFileSize();
    }

    /**
     * @return string
     */
    public function getFileUploadUrl()
    {
        return $this->getUrl(self:: URL_PATH_UPLOAD_IMPORT_FILE, ['action' => 'upload']);
    }

    /**
     * @return string
     */
    public function getFileDeleteUrl()
    {
        return $this->getUrl(self:: URL_PATH_UPLOAD_IMPORT_FILE, ['action' => 'delete']);
    }

    /**
     * @return string
     */
    public function getExportCsvUrl()
    {
        return $this->getUrl(self:: URL_PATH_EXPORT_VENDORS_CSV);
    }

    /**
     * @return string
     */
    public function getValidateUrl()
    {
        return $this->getUrl(self:: URL_PATH_VALIDATE_FILE);
    }

    /**
     * @return string
     */
    public function getImportUrl()
    {
        return $this->getUrl(self:: URL_PATH_IMPORT_VENDORS_CSV);
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->getUrl(self:: URL_PATH_REDIRECT);
    }

    /**
     * @return string
     */
    public function getBackUrl()
    {
        return $this->getUrl(self:: URL_PATH_BACK);
    }

    /**
     * @return \string[][]
     */
    public function getHeaders(){
        return ['attributes' => ['sku','price','quantity'],
            'required' => ['sku','price','quantity']];
    }

    /**
     * @param array $data
     * @return bool|false|string
     */
    public function jsonEncodeData($data = [])
    {
        return $this->jsonEncoder->serialize($data);
    }
}
