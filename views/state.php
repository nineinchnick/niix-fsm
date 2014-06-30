<?php
/* @var $this NetController */
/* @var $model NetActiveRecord */
/* @var $targetState mixed */
/* @var $states array */

$this->buildNavigation($this->action, $model);
$title = Yii::t('app', 'Status') . ' ' . CHtml::encode($model->label()) . ' ' . CHtml::encode($model);
$this->setPageTitle($title);
?>

<?php $this->widget('niix.components.NetAlert'); ?>

<div>
    <?php foreach($statuses as $status): ?>
        <?php if(!$status['enabled']) continue; ?>
        <a class="btn <?php echo $status['class']; ?>" href="<?php echo $status['url']; ?>" style="margin-left: 2em;">
            <i class="fa fa-<?php echo $status['icon']; ?>"></i> <?php echo $status['label']; ?>
        </a>
    <?php endforeach; ?>
</div>
