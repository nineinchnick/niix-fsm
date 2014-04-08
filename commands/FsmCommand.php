<?php

class FsmCommand extends MigrateCommand
{
	public $migrationPath='fsm.migrations';

    /**
     * Changes:
     * - load an AR model and use constant name
     */
	public function actionCreate($args)
	{
		if(isset($args[0]))
			$model=$args[0];
		else
			$this->usageError('Please provide the name of the AR model for which the state tables will be created.');

        $name = 'install_fsm';
        $model = CActiveRecord::model($model);

		$name='m'.gmdate('ymd_His').'_'.$name;
        $content=strtr($this->getTemplate(), array(
            '{ClassName}'=>$name,
            '{TableName}'=>$model->tableName(),
            '{PrimaryKey}'=>$model->tableSchema->primaryKey,
        ));
		$file=$this->migrationPath.DIRECTORY_SEPARATOR.$name.'.php';

		if($this->confirm("Create new migration '$file'?"))
		{
			file_put_contents($file, $content);
			echo "New migration created successfully.\n";
		}
	}
}
