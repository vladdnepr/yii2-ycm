<?php

namespace janisto\ycm;

use Yii;
use vova07\imperavi\Widget as RedactorWidget;
use vova07\select2\Widget as Select2Widget;
use yii\base\InvalidConfigException;
use yii\bootstrap\Modal;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\web\NotFoundHttpException;

/**
 * @property array $models Registered models. This property is read-only.
 */
class Module extends \yii\base\Module
{
    /**
     * @inheritdoc
     */
    public $controllerNamespace = 'janisto\ycm\controllers';
    /**
     * Asset bundle
     *
     * @var string
     */
    public $assetBundle = 'janisto\ycm\YcmAsset';
    /**
     * URL prefix
     *
     * @var string
     */
    public $urlPrefix = 'admin';
    /*
     * URL rules
     *
     * @var array The rules to be used in URL management.
     */
    public $urlRules = [
        '' => 'default/index',
    ];

    protected $attributesWidgets;
    public $registerModels = [];
    protected $models = [];
    public $maxColumns = 8;

    public $uploadPath;
    public $uploadUrl;
    public $uploadCreate = true;
    public $uploadPermissions = 0775;
    public $uploadDeleteTempFile = true;
    public $redactorImageUpload = true;
    public $redactorImageUploadOptions = [
        'maxWidth' => 1920,
        'maxHeight' => 1920,
        'maxSize' => 1048576, // 1024 * 1024 = 1MB
    ];
    public $redactorFileUpload = true;
    public $redactorFileUploadOptions = [
        'maxSize' => 8388608, // 1024 * 1024 * 8 = 8MB
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->setViewPath(dirname(__FILE__) . '/views');

        if ($this->uploadPath === null) {
            $path = Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . 'uploads';
            $this->uploadPath = realpath($path);
            if ($this->uploadPath === false && $this->uploadCreate === true) {
                if (!FileHelper::createDirectory($path, $this->uploadPermissions)) {
                    throw new InvalidConfigException('Could not create upload folder "$path".');
                }
            }
        }
        if ($this->uploadUrl === null) {
            $this->uploadUrl = Yii::getAlias('@web') . '/uploads';
        }

        foreach ($this->registerModels as $name => $class) {
            $model = Yii::createObject($class);
            if (is_subclass_of($model, 'yii\db\ActiveRecord')) {
                $this->models[$name] = $model;
            }
        }
    }

    /**
     * Get models.
     *
     * @return array Models
     */
    public function getModels ()
    {
        return $this->models;
    }

    /**
     * Get Model name from models array.
     *
     * @param \yii\db\ActiveRecord $model Model object
     * @return string Model name
     * @throws NotFoundHttpException
     */
    public function getModelName($model)
    {
        foreach ($this->models as $name => $class) {
            if (get_class($class) === $model->className()) {
                return $name;
            }
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Load model.
     *
     * @param string $name Model name
     * @param null|int $pk Primary key
     * @return \yii\db\ActiveRecord
     * @throws NotFoundHttpException
     */
    public function loadModel($name, $pk = null)
    {
        $name = (string)$name;
        if (!ArrayHelper::keyExists($name, $this->models)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var $model \yii\db\ActiveRecord */
        $model = $this->models[$name];

        if ($pk !== null) {
            if (($model = $model->findOne((int)$pk)) !== null) {
                return $model;
            } else {
                throw new NotFoundHttpException('The requested page does not exist.');
            }
        }

        return $model;
    }

    /**
     * Create ActiveForm widget.
     *
     * @param \yii\widgets\ActiveForm $form
     * @param object $model Model
     * @param string $attribute Model attribute
     */
    public function createWidget($form, $model, $attribute)
    {
        $widget = $this->getAttributeWidget($model, $attribute);

        switch ($widget) {
            case 'wysiwyg':
                $options = [
                    'settings' => [
                        'minHeight' => 200,
                        'plugins' => [
                            'video',
                            'fullscreen',
                        ],
                    ]
                ];
                if ($this->redactorImageUpload === true) {
                    $imageOptions =  [
                        'settings' => [
                            'imageManagerJson' => Url::to(['model/redactor-list', 'name' => $this->getModelName($model), 'attr' => $attribute]),
                            'imageUpload' => Url::to(['model/redactor-upload', 'name' => $this->getModelName($model), 'attr' => $attribute]),
                            'imageUploadErrorCallback' => new JsExpression('function(json) { alert(json.error); }'),
                            'plugins' => [
                                'imagemanager',
                            ],
                        ]
                    ];
                    $options = array_merge_recursive($options, $imageOptions);
                }
                if ($this->redactorFileUpload === true) {
                    $fileOptions =  [
                        'settings' => [
                            'fileManagerJson' => Url::to(['model/redactor-list', 'name' => $this->getModelName($model), 'attr' => $attribute, 'type' => 'file']),
                            'fileUpload' => Url::to(['model/redactor-upload', 'name' => $this->getModelName($model), 'attr' => $attribute, 'type' => 'file']),
                            'fileUploadErrorCallback' => new JsExpression('function(json) { alert(json.error); }'),
                            'plugins' => [
                                'filemanager',
                            ],
                        ]
                    ];
                    $options = array_merge_recursive($options, $fileOptions);
                }
                echo $this->createField($form, $model, $attribute, $options, 'wysiwyg');
                break;

            case 'select':
                $options = [
                    'options' => [
                        'placeholder' => 'Choose item',
                    ],
                    'settings' => [
                        'allowClear' => true,
                        'width' => '100%',
                    ],
                    'items' => [
                        '' => '', // Add empty item for placeholder
                    ],
                ];
                echo $this->createField($form, $model, $attribute, $options, 'select');
                break;

            case 'selectMultiple':
                $options = [
                    'options' => [
                        'multiple' => true,
                        'placeholder' => 'Choose item',
                    ],
                    'settings' => [
                        'width' => '100%',
                    ],
                ];
                echo $this->createField($form, $model, $attribute, $options, 'select');
                break;

            case 'image':
                $options = [];
                if (!$model->isNewRecord && !empty($model->$attribute)) {
                    $className = StringHelper::basename($model->className());
                    $inputName = $className . '[' . $attribute . '_delete]';
                    $inputId = strtolower($className . '-' . $attribute . '_delete');
                    $url = $this->getAttributeUrl($this->getModelName($model), $attribute, $model->$attribute);
                    ob_start();
                    echo '<div class="checkbox"><label for="'. $inputId .'">
                        <input type="checkbox" name="' . $inputName . '" id="' . $inputId . '" value="delete"> Delete image
                    </label></div>';
                    Modal::begin([
                        'size' => Modal::SIZE_LARGE,
                        'header' => '<h4>Preview image</h4>',
                        'toggleButton' => ['label' => 'Preview image', 'class' => 'btn btn-info btn-sm'],
                    ]);
                    echo Html::img($url, ['class'=>'modal-image']);
                    Modal::end();
                    $html = ob_get_clean();
                    $options['hint'] = $html;
                }
                echo $this->createField($form, $model, $attribute, $options, 'fileInput');
                break;

            case 'file':
                $options = [];
                if (!$model->isNewRecord && !empty($model->$attribute)) {
                    $className = StringHelper::basename($model->className());
                    $inputName = $className . '[' . $attribute . '_delete]';
                    $inputId = strtolower($className . '-' . $attribute . '_delete');
                    $url = $this->getAttributeUrl($this->getModelName($model), $attribute, $model->$attribute);
                    $html = '<div class="checkbox"><label for="'. $inputId .'">
                        <input type="checkbox" name="' . $inputName . '" id="' . $inputId . '" value="delete"> Delete file
                    </label></div>';
                    $html .= Html::a('Download file', $url, ['class'=>'btn btn-info btn-sm']);
                    $options['hint'] = $html;
                }
                echo $this->createField($form, $model, $attribute, $options, 'fileInput');
                break;

            case 'text':
                $options = [
                    'maxlength' => $model->tableSchema->columns[$attribute]->size,
                ];
                echo $this->createField($form, $model, $attribute, $options, 'textInput');
                break;

            case 'hidden':
                $options = [
                    'maxlength' => $model->tableSchema->columns[$attribute]->size,
                ];
                $options = $this->getAttributeOptions($attribute, $options);
                echo Html::activeHiddenInput($model, $attribute, $options);
                break;

            case 'password':
                $options = [
                    'maxlength' => $model->tableSchema->columns[$attribute]->size,
                ];
                echo $this->createField($form, $model, $attribute, $options, 'passwordInput');
                break;

            case 'textarea':
                $options = [
                    'rows' => 6,
                ];
                echo $this->createField($form, $model, $attribute, $options, 'textarea');
                break;

            case 'radio':
                echo $this->createField($form, $model, $attribute, [], 'radio');
                break;

            case 'boolean':
            case 'checkbox':
                echo $this->createField($form, $model, $attribute, [], 'checkbox');
                break;

            case 'dropdown':
                $options = [
                    'prompt' => 'Choose ' . $model->getAttributeLabel($attribute),
                ];
                echo $this->createField($form, $model, $attribute, $options, 'dropDownList');
                break;

            case 'listbox':
                $options = [
                    'prompt' => '',
                ];
                echo $this->createField($form, $model, $attribute, $options, 'listBox');
                break;

            case 'checkboxList':
                echo $this->createField($form, $model, $attribute, [], 'checkboxList');
                break;

            case 'radioList':
                echo $this->createField($form, $model, $attribute, [], 'radioList');
                break;

            case 'disabled':
                $options = [
                    'maxlength' => $model->tableSchema->columns[$attribute]->size,
                    'readonly' => true,
                ];
                echo $this->createField($form, $model, $attribute, $options, 'textInput');
                break;

            case 'hide':
                break;

            default:
                $options = $this->getAttributeOptions($attribute);
                echo $form->field($model, $attribute)->$widget($options);
                break;
        }
    }

    /**
     * Create ActiveField object.
     *
     * @param \yii\widgets\ActiveForm $form
     * @param object $model Model
     * @param string $attribute Model attribute
     * @param array $options Attribute options
     * @param string $type ActiveField type
     * @return \yii\widgets\ActiveField ActiveField object
     */
    protected function createField($form, $model, $attribute, $options, $type = 'textInput')
    {
        $options = $this->getAttributeOptions($attribute, $options);
        $field = $form->field($model, $attribute);
        if (isset($options['hint'])) {
            $hintOptions = [];
            if (isset($options['hintOptions'])) {
                $hintOptions = $options['hintOptions'];
                unset($options['hintOptions']);
            }
            $field->hint($options['hint'], $hintOptions);
            unset($options['hint']);
        }
        if (isset($options['label'])) {
            $labelOptions = [];
            if (isset($options['labelOptions'])) {
                $labelOptions = $options['labelOptions'];
                unset($options['labelOptions']);
            }
            $field->label($options['label'], $labelOptions);
            unset($options['label']);
        }
        if (isset($options['input'])) {
            $input = $options['input'];
            unset($options['input']);
            $field = $field->input($input, $options);
        } else {
            if ($type == 'dropDownList' || $type == 'listBox' || $type == 'checkboxList' || $type == 'radioList') {
                $items = $this->getAttributeChoices($model, $attribute);
                $field->$type($items, $options);
            } elseif ($type == 'wysiwyg') {
                $field->widget(RedactorWidget::className(), $options);
            } elseif ($type == 'select') {
                if (isset($options['items'])) {
                    $options['items'] = $options['items'] + $this->getAttributeChoices($model, $attribute);;
                } else {
                    $options['items'] = $this->getAttributeChoices($model, $attribute);
                }
                $field->widget(Select2Widget::className(), $options);
            } else {
                $field->$type($options);
            }
        }
        return $field;
    }

    /**
     * Get attribute file path.
     *
     * @param string $name Model name
     * @param string $attribute Model attribute
     * @return string Model attribute file path
     */
    public function getAttributePath($name, $attribute)
    {
        return $this->uploadPath . DIRECTORY_SEPARATOR . strtolower($name) . DIRECTORY_SEPARATOR . strtolower($attribute);
    }

    /**
     * Get attribute file URL.
     *
     * @param string $name Model name
     * @param string $attribute Model attribute
     * @param string $file Filename
     * @return string Model attribute file URL
     */
    public function getAttributeUrl($name, $attribute, $file)
    {
        return $this->uploadUrl . '/' . strtolower($name) . '/' . strtolower($attribute) . '/' . $file;
    }

    /**
     * Get attributes widget.
     *
     * @param object $model Model
     * @param string $attribute Model attribute
     * @return null|object
     */
    public function getAttributeWidget($model, $attribute)
    {
        if ($this->attributesWidgets !== null) {
            if (isset($this->attributesWidgets->$attribute)) {
                return $this->attributesWidgets->$attribute;
            } else {
                $column = $model->tableSchema->columns[$attribute];
                if ($column->phpType === 'boolean') {
                    return 'checkbox';
                } elseif ($column->type === 'text') {
                    return 'textarea';
                } elseif (preg_match('/^(password|pass|passwd|passcode)$/i', $column->name)) {
                    return 'password';
                } else {
                    return 'text';
                }
            }
        }

        $attributeWidgets = [];
        if (method_exists($model, 'attributeWidgets')) {
            $attributeWidgets = $model->attributeWidgets();
        }

        $data = [];
        if (!empty($attributeWidgets)) {
            foreach ($attributeWidgets as $item) {
                if (isset($item[0]) && isset($item[1])) {
                    $data[$item[0]] = $item[1];
                    $data[$item[0].'Options'] = $item;
                }
            }
        }

        $this->attributesWidgets = (object)$data;

        return $this->getAttributeWidget($model, $attribute);
    }

    /**
     * Get an array of attribute choice values.
     * The variable or method name needs ​​to be: attributeChoices.
     *
     * @param object $model Model
     * @param string $attribute Model attribute
     * @return array
     */
    private function getAttributeChoices($model, $attribute)
    {
        $data = [];
        $choicesName = (string)$attribute . 'Choices';
        if (method_exists($model, $choicesName) && is_array($model->$choicesName())) {
            $data = $model->$choicesName();
        } elseif (isset($model->$choicesName) && is_array($model->$choicesName)) {
            $data = $model->$choicesName;
        }
        return $data;
    }

    /**
     * Get attribute options.
     *
     * @param string $attribute Model attribute
     * @param array $options Model attribute form options
     * @param bool $recursive Merge option arrays recursively
     * @return array
     */
    protected function getAttributeOptions($attribute, $options = [], $recursive = false)
    {
        $optionsName = (string)$attribute . 'Options';
        if (isset($this->attributesWidgets->$optionsName)) {
            $attributeOptions = array_slice($this->attributesWidgets->$optionsName, 2);
            if (empty($options)) {
                return $attributeOptions;
            } else {
                if (empty($attributeOptions)) {
                    return $options;
                } else {
                    if ($recursive === true) {
                        return array_merge_recursive($options, $attributeOptions);
                    } else {
                        return array_merge($options, $attributeOptions);
                    }
                }
            }
        } else {
            if (empty($options)) {
                return [];
            } else {
                return $options;
            }
        }
    }

    /**
     * Get model's administrative name.
     *
     * @param mixed $model
     * @return string
     */
    public function getAdminName($model)
    {
        if (is_string($model)) {
            $model = $this->loadModel($model);
        }
        if (!isset($model->adminNames)) {
            return Inflector::pluralize(Inflector::camel2words(StringHelper::basename($model->className())));
        } else {
            return $model->adminNames[0];
        }
    }

    /**
     * Get model's singular name.
     *
     * @param mixed $model
     * @return string
     */
    public function getSingularName($model)
    {
        if (is_string($model)) {
            $model = $this->loadModel($model);
        }
        if (!isset($model->adminNames)) {
            return Inflector::singularize(Inflector::camel2words(StringHelper::basename($model->className())));
        } else {
            return $model->adminNames[1];
        }
    }

    /**
     * Get model's plural name.
     *
     * @param mixed $model
     * @return string
     */
    public function getPluralName($model)
    {
        if (is_string($model)) {
            $model = $this->loadModel($model);
        }
        if (!isset($model->adminNames)) {
            return Inflector::pluralize(Inflector::camel2words(StringHelper::basename($model->className())));
        } else {
            return $model->adminNames[2];
        }
    }
}