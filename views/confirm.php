<?php
/* @var $this NetController */
/* @var $model NetActiveRecord */
/* @var $targetState mixed */

$this->buildNavigation($this->action, $model);
$title = Yii::t('app', 'Status').' '.CHtml::encode($model->label()).' '.CHtml::encode($model);
$this->setPageTitle($title);
?>

<?php $this->widget('niix.components.NetAlert'); ?>

<?php echo Yii::t('app', 'Change status from {source} to {target}', array(
    '{source}' => '<span class="badge badge-default">'.Yii::app()->format->format($sourceState, $format).'</span>',
    '{target}' => '<span class="badge badge-primary">'.Yii::app()->format->format($targetState, $format).'</span>',
)); ?>

<div class="form">
<?php echo CHtml::label(Yii::t('app', 'Reason'), 'reason'); ?>
    <div class="row">
        <div class="span4">
            <?php echo CHtml::textArea('reason', '', array('cols'=>80, 'rows'=>5,'style'=>'width: 25em;')); ?>
        </div>
    </div>
    <a class="btn btn-success" href="<?php echo $this->createUrl($this->id, array('state'=>$targetState, 'confirmed'=>1)); ?>"><i class="fa fa-save"></i> <?php echo Yii::t('app', 'Confirm'); ?></a>
    
    <?php echo CHtml::link(Yii::t('app', 'Cancel'), $this->createUrl((isset($_GET['return'])?$_GET['return']:'view'), array('id'=>$model->primaryKey))); ?>
</div>


