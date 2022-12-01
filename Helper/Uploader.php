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

namespace Ced\CsMultiSellerImportExport\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Uploader
 * @package Ced\CsMultiSellerImportExport\Helper
 */
class Uploader extends \Magento\Framework\App\Helper\AbstractHelper
{
    const PATH = 'csmultisellerimport/import/index';

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $fileSystem;
    /**
     * @var \Magento\MediaStorage\Model\File\UploaderFactory
     */
    protected $uploaderFactory;
    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    protected $ioFilesystem;

    /**
     * Uploader constructor.
     * @param StoreManagerInterface $storeManager
     * @param \Magento\Framework\Filesystem $fileSystem
     * @param \Magento\Framework\Filesystem\Io\File $ioFilesystem
     * @param \Magento\MediaStorage\Model\File\UploaderFactory $uploaderFactory
     * @param Context $context
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        \Magento\Framework\Filesystem $fileSystem,
        \Magento\Framework\Filesystem\Io\File $ioFilesystem,
        \Magento\MediaStorage\Model\File\UploaderFactory $uploaderFactory,
        Context $context
    ) {
        parent::__construct($context);
        $this->fileSystem = $fileSystem;
        $this->uploaderFactory = $uploaderFactory;
        $this->storeManager = $storeManager;
        $this->ioFilesystem = $ioFilesystem;
    }

    /**
     * @param $path
     * @return bool
     */
    public function deleteFile($path)
    {
        $return = false;
        if ($path) {
            try {
                if ($this->ioFilesystem->fileExists($path)) {
                    $this->ioFilesystem->rm($path);
                    $return['success'] = true;
                }
            } catch (\Exception $e) {
                $return = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
            }
        }

        return $return;
    }

    /**
     * @param $fileId
     * @return array
     */
    public function csvUploader($fileId)
    {
        try {
            $mediaDirectory = $this->fileSystem->getDirectoryRead(DirectoryList::MEDIA);

            /** @var \Magento\MediaStorage\Model\File\Uploader $uploader */
            $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
            $uploader->setAllowedExtensions(['csv', 'xml']);
            $uploader->setAllowRenameFiles(true);
            $result = $uploader->save($mediaDirectory->getAbsolutePath(self::PATH));

            if ($result['file']) {
                $result['url'] = $this->getTmpMediaUrl($result['file']);
                $result['file_path'] = $result['path'] . '/' . $result['file'];
                $result['message'] = __('The csv file has been uploaded successfully. ');
                $result['success'] = true;
            }
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode(), 'success' => false];
        }
        return $result;
    }

    /**
     * @param $file
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getTmpMediaUrl($file)
    {
        return $this->getBaseTmpMediaUrl() . $this->prepareFile($file);
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getBaseTmpMediaUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
    }

    /**
     * @param $file
     * @return string
     */
    private function prepareFile($file)
    {
        return self::PATH . '/' . ltrim(str_replace('\\', '/', $file), '/');
    }
}
