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
 * Class Form
 * @package Ced\CsMultiSellerImportExport\Block\Adminhtml\Vendor\Import\Edit
 */
class Form extends \Magento\Backend\Block\Widget\Form\Generic
{

    protected function _construct()
    {
        parent::_construct();
        $this->setData('area', 'adminhtml');
    }

    /**
     * @return \Magento\Backend\Block\Widget\Form\Generic
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareForm()
    {
        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create(
            [
                'data' => [
                    'id' => 'edit_form',
                    'method' => 'post',
                    'enctype' => 'multipart/form-data',
                ],
            ]
        );

        // base fieldset
        $fieldsets['base'] = $form->addFieldset('base_fieldset', ['legend' => __('Import Settings')]);

        $fieldsets['base']->addField(
            'file_type',
            'label',
            [
                'name' => 'file_type',
                'title' => __('File Type'),
                'label' => __('File Type'),
                'value' => __('CSV')
            ]
        );

        $fieldsets['base']->addField(
            'required_attribute',
            'hidden',
            [
                'name' => 'required_attribute',
                'class' => 'import_data',
            ]
        );

        $fieldsets['base']->addField(
            'unique_attribute',
            'hidden',
            [
                'name' => 'unique_attribute',
                'class' => 'import_data',
            ]
        );

        $fieldsets['upload'] = $form->addFieldset('upload_file_fieldset', ['legend' => __('File to Import')]);

        $fieldsets['upload']->addField(
            'import_csv_file',
            'file',
            [
                'name' => 'import_csv_file',
                'label' => __('Select File to Import'),
                'title' => __('Select File to Import'),
                'required' => true,
                'after_element_html' => '<div id="upload_output"></div>'
            ]
        );
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
