<?php

namespace vladdnepr\ycm\controllers;

use kartik\grid\GridView;
use vladdnepr\ycm\Module;
use vladdnepr\ycm\query\SearchQuery;
use vladdnepr\ycm\helpers\ModelHelper;
use Yii;
use vova07\imperavi\helpers\FileHelper as RedactorFileHelper;
use yii\base\DynamicModel;
use yii\base\InvalidConfigException;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;
use yii\web\UploadedFile;

/**
 * Class ModelController
 * @property Module $module
 * @package vladdnepr\ycm\controllers
 */
class ModelController extends Controller
{
    /** @inheritdoc */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => [
                            'index', 'list', 'create', 'update', 'delete',
                            'redactor-upload', 'redactor-list', 'choices', 'editable'
                        ],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return in_array(Yii::$app->user->identity->username, $this->module->admins);
                        }
                    ],

                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'redactor-upload' => ['post'],
                    'redactor-list' => ['get'],
                    'delete' => ['get', 'post'],
                ],
            ],
        ];
    }

    /**
     * Default action.
     *
     * @return string the rendering result.
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * Redactor upload action.
     *
     * @param string $name Model name
     * @param string $attr Model attribute
     * @param string $type Format type
     * @return array List of files
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     */
    public function actionRedactorUpload($name, $attr, $type = 'image')
    {
        /** @var $module \vladdnepr\ycm\Module */
        $module = $this->module;
        $name = (string) $name;
        $attribute = (string) $attr;
        $uploadType = 'image';
        $validatorOptions = $module->redactorImageUploadOptions;
        if ((string) $type == 'file') {
            $uploadType = 'file';
            $validatorOptions = $module->redactorFileUploadOptions;
        }
        $attributePath = $module->getAttributePath($name, $attribute);
        if (!is_dir($attributePath)) {
            if (!FileHelper::createDirectory($attributePath, $module->uploadPermissions)) {
                throw new InvalidConfigException('Could not create folder "' . $attributePath . '". Make sure "uploads" folder is writable.');
            }
        }
        $file = UploadedFile::getInstanceByName('file');
        $model = new DynamicModel(compact('file'));
        $model->addRule('file', $uploadType, $validatorOptions)->validate();
        if ($model->hasErrors()) {
            $result = [
                'error' => $model->getFirstError('file')
            ];
        } else {
            if ($model->file->extension) {
                $model->file->name = md5($attribute . time() . uniqid(rand(), true)) . '.' . $model->file->extension;
            }
            $path = $attributePath . DIRECTORY_SEPARATOR . $model->file->name;
            if ($model->file->saveAs($path)) {
                $result = ['filelink' => $module->getAttributeUrl($name, $attribute, $model->file->name)];
                if ($uploadType == 'file') {
                    $result['filename'] = $model->file->name;
                }
            } else {
                $result = [
                    'error' => Yii::t('ycm', 'Could not upload file.'),
                ];
            }
        }
        Yii::$app->response->format = Response::FORMAT_JSON;
        return $result;
    }

    /**
     * Redactor file list.
     *
     * @param string $name Model name
     * @param string $attr Model attribute
     * @param string $type Format type
     * @return array List of files
     */
    public function actionRedactorList($name, $attr, $type = 'image')
    {
        /** @var $module \vladdnepr\ycm\Module */
        $module = $this->module;
        $name = (string) $name;
        $attribute = (string) $attr;
        $attributePath = $module->getAttributePath($name, $attribute);
        $attributeUrl = $module->getAttributeUrl($name, $attribute, '');
        $format = 0;
        $options = [
            'url' => $attributeUrl,
            'only' => ['*.png', '*.gif', '*.jpg', '*.jpeg'],
            'caseSensitive' => false
        ];
        if ((string) $type == 'file') {
            $format = 1;
            $options = [
                'url' => $attributeUrl,
            ];
        }
        Yii::$app->response->format = Response::FORMAT_JSON;
        return RedactorFileHelper::findFiles($attributePath, $options, $format);
    }

    /**
     * List models.
     *
     * @param string $name Model name
     * @return string the rendering result.
     */
    public function actionList($name)
    {
        /** @var $module \vladdnepr\ycm\Module */
        $module = $this->module;
        /** @var $model \yii\db\ActiveRecord */
        $model = $module->loadModel($name);

        $columns = [];
        if (method_exists($model, 'gridViewColumns')) {
            foreach ($model->gridViewColumns() as $options) {
                $columns[] = $module->createListWidget($model, $options);
            }
        } else {
            //$columns = $model->getTableSchema()->getColumnNames();
            $i = 0;
            foreach ($model->getTableSchema()->columns as $column) {
                $columns[] = $column->name;
                $i++;
                if ($i === $module->maxColumns) {
                    break;
                }
            }
        }
        //array_unshift($columns, ['class' => 'kartik\grid\SerialColumn']);
        array_push($columns, [
            'class' => 'kartik\grid\ActionColumn',
            'template' => '{update} {delete}',
            'buttons' => [
                'update' => function ($url) {
                    return Html::a('<span class="glyphicon glyphicon-pencil"></span>', $url, [
                        'title' => Yii::t('ycm', 'Update'),
                        'data-pjax' => '0',
                    ]);
                },
                'delete' => function ($url, $model) {
                    /** @var $module \vladdnepr\ycm\Module */
                    $module = $this->module;
                    if ($module->getHideDelete($model) === false) {
                        return Html::a('<span class="glyphicon glyphicon-trash"></span>', $url, [
                            'title' => Yii::t('ycm', 'Delete'),
                            'data-confirm' => Yii::t('ycm', 'Are you sure you want to delete this item?'),
                            'data-method' => 'post',
                            'data-pjax' => '0',
                        ]);
                    }
                    return null;
                },
            ],
            'urlCreator' => function ($action, $model, $key) {
                /** @var $module \vladdnepr\ycm\Module */
                $module = $this->module;
                $name = $module->getModelName($model);
                return Url::to([$action, 'name' => $name, 'pk' => $key]);
            }
        ]);

        $config = [
            'pjax' => true,
            'panel' => [
                'type' => GridView::TYPE_DEFAULT
            ],
            'toolbar'=> $this->getListToolbar($name, $model),
            'columns' => $columns,
            'showOnEmpty' => false,
            'dataProvider' => SearchQuery::getDataProvider(
                $model,
                Yii::$app->request->queryParams
            )
        ];

        if (method_exists($model, 'gridViewConfig')) {
            $config = array_merge($config, $model->gridViewConfig());
        }

        if (method_exists($model, 'search')) {
            $config = array_merge($config, [
                'filterModel' => $model,
            ]);
        }

        return $this->render('list', [
            'config' => $config,
            'model' => $model,
            'name' => $name,
        ]);
    }

    protected function getListToolbar($name, $model)
    {
        $buttons = [];

        /** @var $module \vladdnepr\ycm\Module */
        $module = $this->module;

        if ($module->getHideCreate($model) === false) {
            $buttons[] = Html::a(
                '<i class="glyphicon glyphicon-plus"></i> ' .
                Yii::t('ycm', 'Create {name}', ['name' => $module->getSingularName($name)]),
                ['create', 'name' => $name],
                ['class' => 'btn btn-success']
            );
        }

        return [
            ['content' => implode(' ', $buttons)],
            '{export}',
            '{toggleData}',
        ];
    }

    /**
     * Create model.
     *
     * @param string $name Model name
     * @return mixed
     * @throws InvalidConfigException
     * @throws ServerErrorHttpException
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionCreate($name)
    {
        /** @var $module \vladdnepr\ycm\Module */
        $module = $this->module;
        /** @var $model \yii\db\ActiveRecord */
        $model = $module->loadModel($name);

        if ($model->load(Yii::$app->request->post())) {
            $filePaths = [];
            foreach ($model->tableSchema->columns as $column) {
                $attribute = $column->name;
                $widget = $module->getAttributeWidget($model, $attribute);
                if ($widget == 'file' || $widget == 'image') {
                    $attributePath = $module->getAttributePath($name, $attribute);
                    $file = UploadedFile::getInstance($model, $attribute);
                    if ($file) {
                        $model->$attribute = $file;
                        if ($model->validate()) {
                            $fileName = md5($attribute . time() . uniqid(rand(), true)) . '.' . $file->extension;
                            if (!is_dir($attributePath)) {
                                if (!FileHelper::createDirectory($attributePath, $module->uploadPermissions)) {
                                    throw new InvalidConfigException('Could not create folder "' . $attributePath . '". Make sure "uploads" folder is writable.');
                                }
                            }
                            $path = $attributePath . DIRECTORY_SEPARATOR . $fileName;
                            if (file_exists($path) || !$file->saveAs($path, $module->uploadDeleteTempFile)) {
                                throw new ServerErrorHttpException('Could not save file or file exists: ' . $path);
                            }
                            array_push($filePaths, $path);
                            $model->$attribute = $fileName;
                        }
                    }
                }
            }
            if ($model->save()) {
                Yii::$app->session->setFlash('success', Yii::t('ycm', '{name} has been created.', ['name' => $module->getSingularName($name)]));
                if (Yii::$app->request->post('_addanother')) {
                    return $this->redirect(['create', 'name' => $name]);
                } elseif (Yii::$app->request->post('_continue')) {
                    return $this->redirect(['update', 'name' => $name, 'pk' => $model->primaryKey]);
                } else {
                    return $this->redirect(['list', 'name' => $name]);
                }
            } elseif (count($filePaths) > 0) {
                foreach ($filePaths as $path) {
                    if (file_exists($path)) {
                        // Save failed - delete files.
                        if (@unlink($path) === false) {
                            throw new ServerErrorHttpException('Could not delete file: ' . $path);
                        }
                    }
                }
            }
        }

        return $this->render('create', [
            'model' => $model,
            'name' => $name,
        ]);
    }

    /**
     * Update model.
     *
     * @param string $name Model name
     * @param integer $pk Primary key
     * @return mixed
     * @throws InvalidConfigException
     * @throws ServerErrorHttpException
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionUpdate($name, $pk)
    {
        /** @var $module \vladdnepr\ycm\Module */
        $module = $this->module;
        /** @var $model \yii\db\ActiveRecord */
        $model = $module->loadModel($name, $pk);

        if ($model->load(Yii::$app->request->post())) {
            $filePaths = [];
            foreach ($model->tableSchema->columns as $column) {
                $attribute = $column->name;
                $widget = $module->getAttributeWidget($model, $attribute);
                if ($widget == 'file' || $widget == 'image') {
                    $attributePath = $module->getAttributePath($name, $attribute);
                    $className = StringHelper::basename($model->className());
                    $postData = Yii::$app->request->post();
                    $delete = (isset($postData[$className][$attribute . '_delete']));
                    if ($delete) {
                        $path = $attributePath . DIRECTORY_SEPARATOR . $model->getOldAttribute($attribute);
                        if (file_exists($path)) {
                            if (@unlink($path) === false) {
                                throw new ServerErrorHttpException('Could not delete file: ' . $path);
                            }
                        }
                        $model->$attribute = '';
                    } else {
                        $file = UploadedFile::getInstance($model, $attribute);
                        if ($file) {
                            $model->$attribute = $file;
                            if ($model->validate()) {
                                $fileName = md5($attribute . time() . uniqid(rand(), true)) . '.' . $file->extension;
                                if (!is_dir($attributePath)) {
                                    if (!FileHelper::createDirectory($attributePath, $module->uploadPermissions)) {
                                        throw new InvalidConfigException('Could not create folder "' . $attributePath . '". Make sure "uploads" folder is writable.');
                                    }
                                }
                                $path = $attributePath . DIRECTORY_SEPARATOR . $fileName;
                                if (file_exists($path) || !$file->saveAs($path, $module->uploadDeleteTempFile)) {
                                    throw new ServerErrorHttpException('Could not save file or file exists: ' . $path);
                                }
                                array_push($filePaths, $path);
                                $model->$attribute = $fileName;
                            }
                        } else {
                            $model->$attribute = $model->getOldAttribute($attribute);
                        }
                    }
                }
            }
            if ($model->save()) {
                Yii::$app->session->setFlash('success', Yii::t('ycm', '{name} has been updated.', ['name' => $module->getSingularName($name)]));
                if (Yii::$app->request->post('_addanother')) {
                    return $this->redirect(['create', 'name' => $name]);
                } elseif (Yii::$app->request->post('_continue')) {
                    return $this->redirect(['update', 'name' => $name, 'pk' => $model->primaryKey]);
                } else {
                    return $this->redirect(['list', 'name' => $name]);
                }
            } elseif (count($filePaths) > 0) {
                foreach ($filePaths as $path) {
                    if (file_exists($path)) {
                        // Save failed - delete files.
                        if (@unlink($path) === false) {
                            throw new ServerErrorHttpException('Could not delete file: ' . $path);
                        }
                    }
                }
            }
        }

        return $this->render('update', [
            'model' => $model,
            'name' => $name,
        ]);
    }

    /**
     * Delete model.
     *
     * @param string $name Model name
     * @param integer $pk Primary key
     * @return Response
     */
    public function actionDelete($name, $pk)
    {
        /** @var $module \vladdnepr\ycm\Module */
        $module = $this->module;
        /** @var $model \yii\db\ActiveRecord */
        $model = $module->loadModel($name, $pk);

        if ($model->delete() !== false) {
            Yii::$app->session->setFlash('success', Yii::t('ycm', '{name} has been deleted.', ['name' => $module->getSingularName($name)]));
        } else {
            Yii::$app->session->setFlash('error', Yii::t('ycm', 'Could not delete {name}.', ['name' => $module->getSingularName($name)]));
        }

        return $this->redirect(['list', 'name' => $name]);
    }

    public function actionChoices($name, $q = null, $id = null)
    {
        $out = ['results' => ['id' => '', 'text' => '']];

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        /* @var ActiveRecord $model */
        $model = $this->module->loadModel($name);

        if (!is_null($q)) {
            $out['results'] = ModelHelper::findChoicesByLabel($model, $q);
        } elseif ($id > 0) {
            $out['results'] = [
                ModelHelper::getPkColumnName($model) => $id,
                'text' => ModelHelper::getLabelColumnName($model->findOne($id))
            ];
        }
        return $out;
    }

    public function actionEditable($name)
    {
        if (!\Yii::$app->request->post('hasEditable')) {
            throw new BadRequestHttpException;
        }

        /** @var $model \yii\db\ActiveRecord */
        $model = $this->module->loadModel($name, \Yii::$app->request->post('editableKey'));

        $output = '';
        $message = '';

        $modelShortName = $model->formName();
        $post = \Yii::$app->request->post($modelShortName);
        $modelAttributes = current($post);

        if ($model->load([$modelShortName => $modelAttributes])) {
            if (!$model->save()) {
                $message = Html::errorSummary($model);
            }
        }

        return Json::encode(['output' => $output, 'message' => $message]);
    }
}
