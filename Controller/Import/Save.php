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

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\UrlFactory;

/**
 * Class Save
 * @package Ced\CsMultiSellerImportExport\Controller\Import
 */
class Save extends \Ced\CsMarketplace\Controller\Vendor
{
    public $mode = '';

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Ced\CsMultiSeller\Helper\Data $csmultisellerHelper,
        \Ced\CsMultiSeller\Model\MultisellFactory $multisellFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Store\Model\StoreFactory $storeFactory,
        \Ced\CsMarketplace\Model\VproductsFactory $vproductsFactory,
        \Ced\CsMarketplace\Model\ResourceModel\Vproducts\CollectionFactory $vProductCollection,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        Session $customerSession,
        UrlFactory $urlFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Ced\CsMarketplace\Helper\Data $csmarketplaceHelper,
        \Ced\CsMarketplace\Helper\Acl $aclHelper,
        \Ced\CsMarketplace\Model\VendorFactory $vendor,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Product\Copier $productCopier,
        \Magento\InventoryApi\Api\SourceItemsSaveInterface $sourceItemsSave,
        \Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory $sourceItemFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\App\ResourceConnection $connection,
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $configurable
    )
    {
        $this->storeManager = $storeManager;
        $this->csmultisellerHelper = $csmultisellerHelper;
        $this->multisellFactory = $multisellFactory;
        $this->productFactory = $productFactory;
        $this->storeFactory = $storeFactory;
        $this->vproductsFactory = $vproductsFactory;
        $this->stockRegistry = $stockRegistry;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->productCopier = $productCopier;
        $this->sourceItemsSave = $sourceItemsSave;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->configurable = $configurable;
        $this->connection = $connection;
        $this->productRepository = $productRepository;
        $this->vProductCollection = $vProductCollection;

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
    }

    public function dispatch(\Magento\Framework\App\RequestInterface $request)
    {

        if ($this->registry->registry('ced_csmarketplace_current_store'))
            $this->registry->unRegister('ced_csmarketplace_current_store');

        if ($this->registry->registry('ced_csmarketplace_current_website'))
            $this->registry->unRegister('ced_csmarketplace_current_website');

        $this->registry->register('ced_csmarketplace_current_store', $this->storeManager->getStore()->getId());
        $this->registry->register('ced_csmarketplace_current_website', $this->storeManager->getStore()->getWebsiteId());
        return parent::dispatch($request);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $csvData = $this->getRequest()->getParam('import_data', []);

        if (!empty($csvData) && is_array($csvData)) {
            $result = $this->import(
                $csvData,
            );
        } else {
            $result['errors'][] = __('Invalid Data Supplied');
            $this->messageManager->addErrorMessage(__('Invalid Data Supplied'));
        }

        $this->getResponse()->setBody(json_encode($result));
    }

    /**
     * @param $csvData
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function import($csvData)
    {
        if (!$vendorId = $this->_getSession()->getVendorId()) {
            $result['errors'][] = __("Something went wrong");
            return $result;
        }

        $result = ['errors' => [], 'success' => []];
        $successCount = 0;
        foreach ($csvData as $row => $rowData) {
            $error = 0;

            if (!$error) {
                $customData = $rowData;
                if(isset($customData['sku'])){
                    try {
                        $customData['product_id'] = $this->productRepository->get($customData['sku'])->getEntityId();
                    }catch (\Exception $e){
                        $result['errors'][] = __("Error Occurred in row %1 : ", $row) . $e->getMessage();
                        $this->messageManager->addErrorMessage(__("Error Occurred in row : %1", $row) . $e->getMessage());
                        continue;
                    }
                }else{
                    $result['errors'][] = __("NO such product exist with this SKU in row %1 : ", $row);
                    $this->messageManager->addErrorMessage(__("NO such product exist with this SKU in row %1 : ", $row));
                    continue;
                }
                $vProduct = $this->vProductCollection->create()
                    ->addFieldToFilter('parent_id',$customData['product_id'])
                    ->addFieldToFilter('is_multiseller',1)
                    ->addFieldToFilter('vendor_id',$vendorId)
                    ->getFirstItem();

                if(!empty($vProduct->getData())) {
                    $customData['product_id'] = $vProduct->getData('product_id');
                    $customData['sku'] = $vProduct->getData('sku');
                    $this->mode = \Ced\CsMarketplace\Model\Vproducts::EDIT_PRODUCT_MODE;
                }else{
                    $vProduct = $this->vproductsFactory->create()->load($customData['sku'], 'sku');
                    if(!empty($vProduct->getVendorId()) && $vProduct->getVendorId() == $vendorId){
                        $result['errors'][] = __("Error Occurred in row %1 : ", $row) . 'Cannot create select and sell of your own products';
                        $this->messageManager->addErrorMessage(__("Error Occurred in row : %1", $row) . 'Cannot create select and sell of your own products');
                        continue;
                    }else {
                        $this->mode = \Ced\CsMarketplace\Model\Vproducts::NEW_PRODUCT_MODE;
                        $customData['sku'] = $customData['sku'] . '-' . $this->_getSession()->getVendorId();
                    }
                }

                $productErrors = [];
                $csproduct = [];
                $csproduct['product'] = ['sku' => isset($customData['sku']) ? $customData['sku'] : '',
                    'price' => isset($customData['price']) ? $customData['price'] : ''];
                if(isset($customData['quantity'])) {
                    $csproduct['product']['stock_data'] = ['is_in_stock' => $customData['quantity'] > 0 ? 1 : 0,
                        'qty' => $customData['quantity']];
                }
                if(isset($customData['product_id'])) {
                    $csproduct['id'] = $customData['product_id'];
                }

                $vproductModel = $this->multisellFactory->create();
                $vproductModel->addData($csproduct['product']);
                $vproductModel->addData($csproduct['product']['stock_data']);
                $productErrors = $vproductModel->validate();

                if (!empty($productErrors)) {
                    foreach ($productErrors as $message) {
                        $result['errors'][] = __("Error Occurred in row %1 : ", $row) . $message;
                        $this->messageManager->addErrorMessage(__("Error Occurred in row : %1", $row) . $message);
                    }
                    continue;
                }
                $product = $this->_initProduct($customData);
                    try {

                        if($this->mode == \Ced\CsMarketplace\Model\Vproducts::EDIT_PRODUCT_MODE) {
                            $store = $this->storeManager->setCurrentStore(\Magento\Store\Model\Store::DEFAULT_STORE_ID);
                            $this->multisellFactory->create()->setStoreId($product->getStoreId())->saveProduct($this->mode, $product,0, $csproduct);

                            $this->setCurrentStore($store);

                            $resource = $this->connection;
                            $connection = $resource->getConnection();
                            $isInStock = 1;
                            if ( $customData['quantity'] == 0 ) { $isInStock = 0; }
                            $tableName = 'cataloginventory_stock_item'; //gives table name with prefix
                            $sql = "Update " . $tableName . " Set is_in_stock = ".$isInStock.", qty = ".$customData['quantity']." where product_id = ".$product->getId();
                            $connection->query($sql);

                            $productStock = $this->stockRegistry->getStockItem($this->productRepository->get($customData['sku'])->getEntityId());
                            $productStock->save();

                        }else{
                            $this->mode = \Ced\CsMarketplace\Model\Vproducts::NEW_PRODUCT_MODE;
                            $customData['sku'] = $customData['sku'].'-'.$this->_getSession()->getVendorId();

                            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE;

                            $currentStore = $this->storeManager->getStore()->getId();
                            $this->storeManager->setCurrentStore((int)$currentStore);
                            $product->setUrlKey($customData['sku']);
                            $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
                            $newProduct = $this->productCopier->copy($product);
                            $newProduct->setStoreId($product->getStoreId());

                            $this->multisellFactory->create()->setStoreId($product->getStoreId())->saveProduct($this->mode, $newProduct, $product,$csproduct);
                            $this->setCurrentStore();

                            $productStock = $this->stockRegistry->getStockItem($newProduct->getId());

                            $productStock->setData('is_in_stock', $customData['quantity'] > 0 ? 1 : 0);
                            $productStock->setData('qty', $customData['quantity']); //set updated quantity
                            $productStock->setData('manage_stock', 1);
                            $productStock->setData('use_config_notify_stock_qty', 1);

                            $visibility = $this->scopeConfig->getValue('ced_csmarketplace/ced_csmultiseller/catalogsearchindividually', $storeScope);
                            if($visibility == '1'){
                                $newProduct->setData('visibility', 4);
                            }
                            $sourceItem = $this->sourceItemFactory->create();
                            $sourceItem->setSourceCode('default');
                            $sourceItem->setSku($customData['sku']);
                            $sourceItem->setQuantity($customData['quantity']);
                            $sourceItem->setStatus(1);
                            $this->sourceItemsSave->execute([$sourceItem]);
                            $newProduct->save();

                            $productStock->save();

                            $productId = $customData['product_id'];

                            $productConfig = $this->configurable->getParentIdsByChild($productId);
                            if(isset($productConfig[0])){
                                //this is parent product id..
                                $configurable_product_id = $productConfig[0];

                                if(!empty($configurable_product_id)){
                                    $resource = $this->connection;
                                    $connection = $resource->getConnection();
                                    $tableName = $resource->getTableName('ced_csmarketplace_vendor_products'); //gives table name with prefix

                                    $repository = $this->productRepository;
                                    $product = $repository->getById($configurable_product_id);

                                    $data = $product->getTypeInstance()->getConfigurableOptions($product);

                                    $options = [];

                                    foreach($data as $attr){
                                        foreach($attr as $p){

                                            $options[$p['sku']][$p['attribute_code']] = $p['option_title'];
                                        }
                                    }
                                    $attribute_information = "SELECT parent_id FROM ". $tableName;

                                    $parent_id = $connection->fetchOne($attribute_information);

                                    foreach($options as $sku =>$d){
                                        $pr = $repository->get($sku);
                                        foreach($d as $k => $v){
                                            if($pr->getId() == $productId){
                                                $sql = "Update " . $tableName . " Set is_configurable_child = 1, option_tittle ='$v' ,configurable_product_id=".$configurable_product_id. " where product_id =".$newProduct->getId();
                                                //UPDATE `ced_csmarketplace_vendor_products` SET `is_configurable_child` = '1', `configurable_product_id` = '1', `option_title` = 'res' WHERE `ced_csmarketplace_vendor_products`.`id` = 103;
                                                $connection->query($sql);
                                            }
                                        }
                                    }
                                }
                            }


                            $newProduct->save();
                            $productStock->save();


                            $resource = $this->connection;
                            $connection = $resource->getConnection();
                            $isInStock = 1;
                            if ( $customData['quantity'] == 0 ) { $isInStock = 0; }
                            $tableName = 'cataloginventory_stock_item'; //gives table name with prefix
                            $sql = "Update " . $tableName . " Set is_in_stock = ".$isInStock.", qty = ".$customData['quantity']." where product_id = ".$newProduct->getId();
                            $connection->query($sql);

                            $newProduct->save();



                        }
                        $successCount++;
                    } catch (AlreadyExistsException $e) {
                        $result['errors'][] = __("Error Occurred in row %1 : ", $row) . $e->getMessage();
                        $this->messageManager->addErrorMessage(__("Error Occurred in row : %1", $row) . $e->getMessage());
                    } catch (\Exception $e) {
                        $result['errors'][] = __("Error Occurred in row %1 : ", $row) . $e->getMessage();
                        $this->messageManager->addErrorMessage(__("Error Occurred in row : %1", $row) . $e->getMessage());
                    }
            }
        }
        if ($successCount > 0) {
            $result['success'][] = __('A total of %1 record(s) has been successfully imported', $successCount);
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) has been successfully imported', $successCount));
        }

        return $result;
    }

    /**
     * Initialize product from request parameters
     * @return Magento\Catalog\Model\Product
     */
    protected function _initProduct($customData)
    {

        if (!$this->_getSession()->getVendorId())
            return;

        $this->registry->unregister('product');
        $this->registry->unregister('current_product');

        $productId = $customData['product_id'];
        $currentStore = $this->storeManager->getStore()->getId();
        $this->storeManager->setCurrentStore((int)$currentStore);
        $product = $this->productFactory->create();

        if (!$productId) {
            $product->setStoreId(0);
            if ($setId = (int)$this->getRequest()->getParam('set')) {
                $product->setAttributeSetId($setId);
            }

            if ($typeId = $this->getRequest()->getParam('type')) {
                $product->setTypeId($typeId);
            }
        }
        $product->setData('_edit_mode', true);
        if ($productId) {
            $storeId = 0;
            if ($this->mode == \Ced\CsMarketplace\Model\Vproducts::EDIT_PRODUCT_MODE && $this->getRequest()->getParam('store')) {
                $websiteId = $this->storeFactory->create()->load($this->getRequest()->getParam('store'))->getWebsiteId();

                if ($websiteId) {
                    if (in_array($websiteId, $this->vproductsFactory->create()->getAllowedWebsiteIds())) {
                        $storeId = $this->getRequest()->getParam('store');
                    }
                }
            }

            try {
                $product->setStoreId($storeId)->load($productId);
            } catch (Exception $e) {
                $product->setTypeId(\Magento\Catalog\Model\Product\Type::DEFAULT_TYPE);
            }

        }
        $attributes = $this->getRequest()->getParam('attributes');
        if ($attributes && $product->isConfigurable() &&
            (!$productId || !$product->getTypeInstance()->getUsedProductAttributeIds())) {
            $product->getTypeInstance()->setUsedProductAttributeIds(
                explode(",", base64_decode(urldecode($attributes)))
            );
        }

        // Required attributes of simple product for configurable creation
        if ($this->getRequest()->getParam('popup')
            && $requiredAttributes = $this->getRequest()->getParam('required')) {
            $requiredAttributes = explode(",", $requiredAttributes);
            foreach ($product->getAttributes() as $attribute) {
                if (in_array($attribute->getId(), $requiredAttributes)) {
                    $attribute->setIsRequired(1);
                }
            }
        }
        if ($this->getRequest()->getParam('popup')
            && $this->getRequest()->getParam('product')
            && !is_array($this->getRequest()->getParam('product'))
            && $this->getRequest()->getParam('id', false) === false) {
            $configProduct = $this->productCollectionFactory->create()
                ->setStoreId(0)
                ->load($this->getRequest()->getParam('product'))
                ->setTypeId($this->getRequest()->getParam('type'));

            $data = [];
            foreach ($configProduct->getTypeInstance()->getEditableAttributes() as $attribute) {

                if (!$attribute->getIsUnique()
                    && $attribute->getFrontend()->getInputType() != 'gallery'
                    && $attribute->getAttributeCode() != 'required_options'
                    && $attribute->getAttributeCode() != 'has_options'
                    && $attribute->getAttributeCode() != $configProduct->getIdFieldName()) {
                    $data[$attribute->getAttributeCode()] = $configProduct->getData($attribute->getAttributeCode());
                }
            }
            $product->addData($data)
                ->setWebsiteIds($configProduct->getWebsiteIds());
        }

        $this->registry->register('product', $product);
        $this->registry->register('current_product', $product);
        return $product;
    }

    /**
     * Set current store
     */
    public function setCurrentStore()
    {
        if ($this->registry->registry('ced_csmarketplace_current_store')) {
            $currentStoreId = $this->registry->registry('ced_csmarketplace_current_store');
            $this->storeManager->setCurrentStore($currentStoreId);
        }
    }
}
