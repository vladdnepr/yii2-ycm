<?php

use yii\helpers\Html;
use kartik\grid\GridView;
use vladdnepr\ycm\widgets\Alert;

/* @var $this \yii\web\View */
/* @var $config array */
/* @var $model \yii\db\ActiveRecord */
/* @var $name string */

/** @var $module \vladdnepr\ycm\Module */
$module = Yii::$app->controller->module;

$this->title = $module->getAdminName($model);
$this->params['breadcrumbs'][] = ['label' => Yii::t('ycm', 'Content'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="ycm-model-list">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= Alert::widget() ?>

    <?= GridView::widget($config); ?>

</div>
