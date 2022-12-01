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

define(
    [
        'jquery',
        'underscore',
        'Magento_Ui/js/lib/validation/validator',
        'jquery/file-uploader',
        'jquery/ui',
        'mage/translate'
    ], function ($, _, validator) {
        'use strict';
        return {
            fileUploaderObject: {
                dataType: 'json',
                url: null,
                autoUpload: true,
                acceptFileTypes: /(\.|\/)(csv)$/i,
                sequentialUploads: true,
                maxFileSize: null,
                formData: {
                    'form_key': window.FORM_KEY
                },
            },
            form: null,
            fileDetails: null,
            urlBack: null,
            urlExportCsv: null,
            urlUpload: null,
            urlDelete: null,
            urlValidate: null,
            urlImport: null,
            urlRedirect: null,
            headers: null,
            importFile: null,
            uploadOutput: null,
            savingArray: [],

            init: function (Object) {
                this.urlBack = Object.urlBack;
                this.urlExportCsv = Object.urlExportCsv;
                this.urlUpload = Object.urlUpload;
                this.urlDelete = Object.urlDelete;
                this.urlValidate = Object.urlValidate;
                this.urlImport = Object.urlImport;
                this.urlRedirect = Object.urlRedirect;
                this.headers = Object.headers.attributes;
                this.required = Object.headers.required;
                this.importFile = $('#import_csv_file');
                this.importFile.attr("accept", '.csv');
                this.uploadOutput = $("#upload_output");
                this.setFileUploaderObject(Object, this);
                this.importFile.fileupload(this.fileUploaderObject);
                this.form = $('#edit_form');
            },

            setFileUploaderObject: function (Object, self) {
                let formBody = $("body");

                self.fileUploaderObject.url = Object.urlUpload;
                self.fileUploaderObject.maxFileSize = Object.maxFileSize;

                self.fileUploaderObject.add = function (e, data) {
                    self.resetData();
                    formBody.loader("hide");

                    if (_.isObject(self.fileDetails)) {
                        self.deleteFile();
                    }

                    formBody.loader("show");
                    $(e.target).fileupload('process', data).done(function () {
                        data.submit();
                    });
                };

                self.fileUploaderObject.done = function (e, data) {
                    formBody.loader("hide");

                    if (data.result && !data.result.hasOwnProperty('errorcode')) {
                        self.fileDetails = data;

                        $("#upload_button").show();
                        self.appendMessage(
                            'success',
                            data.result.message + $.mage.__('Please click Check Data to validate the file.')
                        );
                    } else {
                        $("#upload_button").hide();
                        self.appendMessage(
                            'error',
                            data.result.error
                        );
                    }
                }

            },

            resetData: function () {
                $("#import").hide();
            },

            deleteFile: function () {
                let path = '';
                let object = $.Deferred();
                if (_.isObject(this.fileDetails)) {
                    path = this.fileDetails.result.file_path;
                }

                $.ajax({
                    type: "POST",
                    showLoader: true,
                    url: this.urlDelete,
                    data: { path: path },
                    success: function (resp) {
                        object.resolve(resp);
                        return true;
                    }
                });

                return object.promise();
            },

            appendMessage: function (element_class, msg, clear = true) {
                if (clear) this.uploadOutput.empty();

                this.uploadOutput.append(
                    $('<div>', {
                        class: this.getMessageClass(element_class),
                        html: msg
                    })
                );
            },

            getMessageClass: function (type = 'success') {
                let result = 'message';

                switch (type) {
                    case 'success':
                        result = 'message message-success success';
                        break;

                    case 'error':
                        result = 'message message-error error';
                        break;

                    case 'notice':
                        result = 'message message-warning warning';
                        break;
                }

                return result;
            },

            export: function () {
                location.href = this.urlExportCsv;
            },

            back: function () {
                location.href = this.urlBack;
            },

            validate: function () {
                let self = this;
                $.when(this.readFile()).then(function (data) {
                    let result = $.parseJSON(data);
                    let flag = false;
                    self.savingArray = [];
                    if (result.length > 1) {
                        $.each(result, function (index, row) {
                            let savingData = false;
                            if (index === 0) {
                                flag = self.validateHeaders(row);
                            }
                            else {
                                if (flag) {
                                    savingData = self.validateColumns(row, index);
                                    if (savingData && savingData !== 'undefined') {
                                        self.savingArray.push(savingData);
                                        self.appendToForm(savingData, index);
                                    }
                                }
                            }
                        });

                        let saveLength = self.savingArray.length;
                        if (saveLength !== result.length - 1) {
                            self.appendMessage(
                                'error',
                                $.mage.__("Please fix errors before importing."),
                                false
                            );
                        }
                        else if (saveLength > 0) {
                            $("#import").show();
                            $("#upload_button").hide();

                            self.appendMessage(
                                'success',
                                saveLength + $.mage.__(" record(s) will be imported from total ") + (result.length - 1) + $.mage.__(" records. Please click Import to import data."),
                                false
                            );
                        }
                    } else {
                        self.appendMessage(
                            'error',
                            $.mage.__("File is empty")
                        );
                    }
                });

            },

            appendToForm: function (savingData, rowNumber) {
                let obj = $.extend({}, savingData);
                let self = this;
                $.each(obj, function (index, value) {
                    self.form.append($('<input>', {
                        type: 'hidden',
                        class: 'import_data',
                        name: 'import_data[' + rowNumber + '][' + index + ']',
                        val: value
                    }));
                });
            },

            import: function () {
                let self = this;
                let ajaxData = new FormData();
                ajaxData.append('form_key', window.FORM_KEY);
                $('.import_data').each(function (index, object) {
                    ajaxData.append($(object).attr('name'), $(object).val());
                });

                $.ajax({
                    type: "POST",
                    showLoader: true,
                    url: self.urlImport,
                    data: ajaxData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        //location.reload();
                        location.href = self.urlRedirect;
                    }
                });
            },

            readFile: function () {
                let path = '';
                let object = $.Deferred();
                this.uploadOutput.empty();

                if (_.isObject(this.fileDetails)) {
                    path = this.fileDetails.result.file_path;
                }

                $.ajax({
                    type: "POST",
                    showLoader: true,
                    url: this.urlValidate,
                    data: { path: path },
                    success: function (resp) {
                        object.resolve(resp);
                        return true;
                    }
                });

                return object.promise();
            },

            validateHeaders: function (row) {
                let header_arr = [];

                if (this.headers.length > 0) {
                    if (this.headers.length === row.length) {
                        let array = _.intersection(row, this.headers);
                        if (array.length === this.headers.length) {
                            $.each(this.headers, function (index, value) {
                                let index_of_header = _.indexOf(row, value);
                                header_arr[index_of_header] = value;
                            });
                            this.headers = header_arr.slice();
                            return true;
                        } else {
                            this.appendMessage(
                                'error',
                                $.mage.__('File Format Validation Failed. Wrong column names. Please recheck the column names')
                            );
                        }
                    } else {
                        this.appendMessage(
                            'error',
                            $.mage.__('File Format Validation Failed. Wrong column names. Please recheck the number of columns')
                        );
                    }
                }

                return false;
            },

            validateColumns: function (row, rowNumber) {
                let toSaveRow = [];
                let error_msg = '';
                let result = false;
                let self = this;
                $.each(row, function (index, value) {
                    let attribute_code = self.headers[index];
                    if (value != null && (typeof value === typeof undefined || $.trim(value) === '')) {
                        error_msg += self.addBold(value) + ' value is required.' + self.addNewLine();
                    } else if (attribute_code == 'price' || attribute_code == 'quantity') {
                        result = validator('validate-number', value);
                        if (!result.passed)
                            error_msg += self.addBold(value) + ' should be numeric.' + self.addNewLine();
                        else
                            toSaveRow[attribute_code] = value;
                    }
                    else {
                        toSaveRow[attribute_code] = value;
                    }
                });

                if (error_msg !== '') {
                    rowNumber = rowNumber + 1;
                    this.appendMessage(
                        'notice',
                        self.addPara(self.addBold($.mage.__('Error on line ') + rowNumber + ': ') + self.addNewLine() + error_msg),
                        false
                    );
                    return false;
                }

                return toSaveRow;
            },

            addNewLine: function () {
                return "<br/>";
            },

            addBold: function (text) {
                return "<b>" + text + "</b>";
            },

            addPara: function (text) {
                return "<p>" + text + "</p>";
            },

        };
    });
