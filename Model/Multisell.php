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
 * @category    Ced
 * @package     Ced_CsMultiSeller
 * @author      CedCommerce Core Team <connect@cedcommerce.com>
 * @copyright   Copyright CedCommerce (https://cedcommerce.com/)
 * @license     https://cedcommerce.com/license-agreement.txt
 */

namespace Ced\CsMultiSellerImportExport\Model;

use Magento\Framework\Api\AttributeValueFactory;

/**
 * Class Multisell
 * @package Ced\CsMultiSeller\Model
 */
class Multisell extends \Ced\CsMarketplace\Model\FlatAbstractModel
{
    /**
     * @var array
     */
    protected $_vproducts = [];

    /**
     * @var \Ced\CsMarketplace\Model\Session
     */
    protected $session;

    /**
     * @var \Ced\CsMarketplace\Model\VproductsFactory
     */
    protected $vproductsFactory;

    /**
     * @var \Ced\CsMarketplace\Helper\Data
     */
    protected $csmarketplaceHelper;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \Ced\CsMultiSeller\Helper\Data
     */
    protected $multisellerHelper;

    /**
     * @var \Ced\CsMarketplace\Helper\Mail
     */
    protected $mailHelper;

    /**
     * @var \Magento\CatalogInventory\Model\Stock\Item
     */
    protected $stockitem;

    /**
     * Multisell constructor.
     * @param \Ced\CsMarketplace\Model\Session $session
     * @param \Ced\CsMarketplace\Model\VproductsFactory $vproductsFactory
     * @param \Ced\CsMarketplace\Helper\Data $csmarketplaceHelper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Ced\CsMultiSeller\Helper\Data $multisellerHelper
     * @param \Ced\CsMarketplace\Helper\Mail $mailHelper
     * @param \Magento\CatalogInventory\Model\Stock\Item $stockitem
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Ced\CsMarketplace\Model\Session $session,
        \Ced\CsMarketplace\Model\VproductsFactory $vproductsFactory,
        \Ced\CsMarketplace\Helper\Data $csmarketplaceHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Ced\CsMultiSeller\Helper\Data $multisellerHelper,
        \Ced\CsMarketplace\Helper\Mail $mailHelper,
        \Magento\CatalogInventory\Model\Stock\Item $stockitem,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        $this->session = $session;
        $this->vproductsFactory = $vproductsFactory;
        $this->csmarketplaceHelper = $csmarketplaceHelper;
        $this->request = $request;
        $this->registry = $registry;
        $this->multisellerHelper = $multisellerHelper;
        $this->mailHelper = $mailHelper;
        $this->stockitem = $stockitem;

        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $resource, $resourceCollection, $data);
    }

    /**
     * get Current vendor Product Ids
     *
     * @return array $productIds
     */
    public function getVendorProductIds($vendorId = 0)
    {
        if (!empty($this->_vproducts)) {
            return $this->_vproducts;
        } else {
            $vendorId = $vendorId ? $vendorId : $this->session->getVendorId();
            $vcollection = $this->vproductsFactory->create()->getVendorProducts('', $vendorId, 0, 1);
            $productids = [];
            if (count($vcollection) > 0) {
                foreach ($vcollection as $data) {
                    if ($data->getIsMultiseller() == 1)
                        array_push($productids, $data->getProductId());
                }
                $this->_vproducts = $productids;
            }
        }
        return $this->_vproducts;
    }

    /**
     * Authenticate vendor-products association
     *
     * @param int $vendorId ,int $productId
     * @return boolean
     */
    public function isAssociatedProduct($vendorId = 0, $productId = 0)
    {

        if (!$vendorId || !$productId)
            return false;

        $vproducts = $this->getVendorProductIds($vendorId);
        if (in_array($productId, $vproducts))
            return true;

        return false;

    }

    /**
     * Validate csmarketplace product attribute values.
     * @return array $errors
     */
    public function validate()
    {
        $errors = [];
        if (!\Zend_Validate::is(trim($this->getSku()), 'NotEmpty')) {
            $errors[] = __('The Product SKU cannot be empty');
        }

        $qty = trim($this->getQty());
        if (!\Zend_Validate::is($qty, 'NotEmpty')) {
            $errors[] = __('The Product Stock cannot be empty');
        } else if (!is_numeric($qty))
            $errors[] = __('The Product Stock must be a valid Number');

        $price = trim($this->getPrice());
        if (!\Zend_Validate::is($price, 'NotEmpty')) {
            $errors[] = __('The Product Price cannot be empty');
        } else if (!is_numeric($price) && !($price > 0))
            $errors[] = __('The Product Price must be 0 or Greater');

        return $errors;
    }

    /**
     * Save Product
     * @params $mode
     * @return int product id
     */
    public function saveProduct($mode, $product, $origProduct, $productData = null)
    {
        if($productData == null){
            $productData = $this->request->getPost();
        }

        $parentId = 0;
        if ($origProduct)
            $parentId = $productData['id'];

        $productId = $product->getId();

        if (isset($productData['product']['price']))
            $product->setPrice($productData['product']['price']);
        if (isset($productData['product']['sku']))
            $product->setSku($productData['product']['sku']);
        if (isset($productData['product']['stock_data']))
            $this->saveStockData($productId, $productData['product']['stock_data']);
        $product->setData('media_gallery', []);
        $product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
        if (!$this->csmarketplaceHelper->isSharingEnabled()) {
            $websiteIds = [$this->registry->registry('ced_csmarketplace_current_website')];
            $product->setWebsiteIds($websiteIds);
        }
        if ($mode == \Ced\CsMarketplace\Model\Vproducts::NEW_PRODUCT_MODE) {
            $product->setName($origProduct->getName());
            if ($this->multisellerHelper->isProductApprovalRequired()){
                $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED);

            } else{

                $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);}
        } else if ($mode == \Ced\CsMarketplace\Model\Vproducts::EDIT_PRODUCT_MODE && isset($productData['product']['status'])
            && $this->vproductsFactory->create()->isApproved($product->getId())) {
            $product->setStatus($productData['product']['status']);
        }
        $product->getResource()->save($product);


        /**
         * Relate Product data
         * @params int mode,int $productId,array $productData
         */
        $this->processPostSave($mode, $product, $productData, $parentId);


        /**
         * Send Product Mails
         * @params array productid,int $status
         */
        if (!$this->multisellerHelper->isProductApprovalRequired() && $mode == \Ced\CsMarketplace\Model\Vproducts::NEW_PRODUCT_MODE) {
            $this->mailHelper
                ->sendProductNotificationEmail([$productId], \Ced\CsMarketplace\Model\Vproducts::APPROVED_STATUS);
        }

    }

    /**
     * Relate Product Data
     * @params $mode,int $productId,array $productData
     *
     */
    public function processPostSave($mode, $product, $productData, $parentId = 0)
    {
        $websiteIds = '';
        $productId = $product->getId();
        $storeId = $this->getStoreId();
        if ($this->registry->registry('ced_csmarketplace_current_website') != '')
            $websiteIds = $this->registry->registry('ced_csmarketplace_current_website');
        else
            $websiteIds = implode(",", $product->getWebsiteIds());
        switch ($mode) {
            case \Ced\CsMarketplace\Model\Vproducts::NEW_PRODUCT_MODE:
                $prodata = isset($productData['product']) ? $productData['product'] : [];
                $this->vproductsFactory->create()->setStoreId($storeId)->setData($prodata)
                    ->setQty(isset($productData['product']['stock_data']['qty']) ? $productData['product']['stock_data']['qty'] : 0)
                    ->setIsInStock(isset($productData['product']['stock_data']['is_in_stock']) ? $productData['product']['stock_data']['is_in_stock'] : 1)
                    ->setPrice($product->getPrice())
                    ->setName($product->getName())
                    ->setSpecialPrice($product->getSpecialPrice())
                    ->setCheckStatus($this->multisellerHelper->isProductApprovalRequired() ? \Ced\CsMarketplace\Model\Vproducts::PENDING_STATUS : \Ced\CsMarketplace\Model\Vproducts::APPROVED_STATUS)
                    ->setProductId($productId)
                    ->setVendorId($this->session->getVendorId())
                    ->setType(isset($productData['type']) ? $productData['type'] : $product->getTypeId())
                    ->setWebsiteId($websiteIds)
                    ->setStatus($this->multisellerHelper->isProductApprovalRequired() ? \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED : \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
                    ->setIsMultiseller(1)
                    ->setParentId($parentId)
                    ->save();

            case \Ced\CsMarketplace\Model\Vproducts::EDIT_PRODUCT_MODE:
                $model = $this->vproductsFactory->create()->setStoreId($storeId)->loadByField(['product_id'], [$product->getId()]);
                if ($model && $model->getId()) {
                    if (isset($productData['product']['price']))
                        $model->setPrice($productData['product']['price']);
                    if (isset($productData['product']['sku']))
                        $model->setSku($productData['product']['sku']);
                    $model->setQty(isset($productData['product']['stock_data']['qty']) ? $productData['product']['stock_data']['qty'] : []);
                    $model->setIsInStock(isset($productData['product']['stock_data']['is_in_stock']) ? $productData['product']['stock_data']['is_in_stock'] : []);
                    if ($model->getCheckStatus() == \Ced\CsMarketplace\Model\Vproducts::APPROVED_STATUS)
                        $model->setStatus(isset($productData['product']['status']) ? $productData['product']['status'] : \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED);
                    $model->save();
                }
        }
    }


    /**
     * Save Product Stock data
     * @params int $productId,array $productData
     * @return int product id
     */
    private function saveStockData($productId, $stockData)
    {
        $stockItem = $this->stockitem;
        $stockItem->load($productId, 'product_id');
        if (!$stockItem->getId()) {
            $stockItem->setProductId($productId)->setStockId(1);
        }
        $stockItem->setProductId($productId)->setStockId(1);

        $manage_stock = isset($stockData['manage_stock']) ? $stockData['manage_stock'] : 1;
        $stockItem->setData('manage_stock', $manage_stock);

        $is_in_stock = isset($stockData['is_in_stock']) ? $stockData['is_in_stock'] : 1;
        $stockItem->setData('is_in_stock', $is_in_stock);

        $savedStock = $stockItem->save();

        $qty = isset($stockData['qty']) ? $stockData['qty'] : 0;
        $stockItem->load($savedStock->getId())->setQty($qty)->save();

        $is_in_stock = isset($stockData['is_in_stock']) ? $stockData['is_in_stock'] : 1;
        $stockItem->setData('is_in_stock', $is_in_stock);

        $use_config_manage_stock = isset($stockData['use_config_manage_stock']) ? $stockData['use_config_manage_stock'] : 0;
        $stockItem->setData('use_config_manage_stock', $use_config_manage_stock);

        $manage_stock = isset($stockData['manage_stock']) ? $stockData['manage_stock'] : 1;
        $stockItem->setData('manage_stock', $manage_stock);

        $is_decimal_divided = isset($stockData['is_decimal_divided']) ? $stockData['is_decimal_divided'] : 0;
        $stockItem->setData('is_decimal_divided', $is_decimal_divided);

        $savedStock = $stockItem->save();
    }
}

?>
