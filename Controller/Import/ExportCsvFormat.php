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

use Ced\CsMultiSellerImportExport\Helper\Csv;
use Magento\Backend\App\Action;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\UrlFactory;

/**
 * Class ExportCsvFormat
 * @package Ced\CsMultiSellerImportExport\Controller\Import
 */
class ExportCsvFormat extends \Ced\CsMarketplace\Controller\Vendor
{
    /**
     * @var Csv
     */
    protected $csv;

    /**
     * @var FileFactory
     */
    protected $_fileFactory;

    /**
     * ExportCsvFormat constructor.
     * @param Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param Session $customerSession
     * @param UrlFactory $urlFactory
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Ced\CsMarketplace\Helper\Data $csmarketplaceHelper
     * @param \Ced\CsMarketplace\Helper\Acl $aclHelper
     * @param \Ced\CsMarketplace\Model\VendorFactory $vendor
     * @param Csv $csv
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        Session $customerSession,
        UrlFactory $urlFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Ced\CsMarketplace\Helper\Data $csmarketplaceHelper,
        \Ced\CsMarketplace\Helper\Acl $aclHelper,
        \Ced\CsMarketplace\Model\VendorFactory $vendor,
        Csv $csv,
        FileFactory $fileFactory
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

        $this->csv = $csv;
        $this->_fileFactory = $fileFactory;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws \Exception
     */
    public function execute()
    {
        $fileName = 'Import';

        $dataRows = $this->getDataRows();
        $createFile = $this->csv->createCsv($fileName, $dataRows);
        $content = [];

        if (!empty($createFile['success'])) {
            $content['type'] = 'filename'; // must keep filename
            $content['value'] = $createFile['path'];
            $content['rm'] = '1'; //remove csv from var folder
        }
        $csv_file_name = ucfirst($fileName) . 'Format.csv';
        return $this->_fileFactory->create($csv_file_name, $content, DirectoryList::VAR_DIR);
    }

    /**
     * @return array
     */
    protected function getDataRows()
    {
        return [['sku','price','quantity'],['abc',100,10]];
    }
}
