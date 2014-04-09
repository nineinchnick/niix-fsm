<?php

Yii::import('system.cli.commands.MigrateCommand');

/**
 * 
 */
class FsmCommand extends CConsoleCommand
{
	/**
	 * @var string the directory that stores the migrations. This must be specified
	 * in terms of a path alias, and the corresponding directory must exist.
	 * Defaults to 'application.migrations' (meaning 'protected/migrations').
	 */
	public $migrationPath='application.migrations';
	/**
	 * @var string the path of the template file for generating new migrations. This
	 * must be specified in terms of a path alias (e.g. application.migrations.template).
	 * If not set, an internal template will be used.
	 */
	public $templateFile='fsm.migrations.template';
	/**
	 * @var boolean whether to execute the migration in an interactive mode. Defaults to true.
	 * Set this to false when performing migration in a cron job or background process.
	 */
	public $interactive=true;

	public function beforeAction($action,$params)
	{
		$path=Yii::getPathOfAlias($this->migrationPath);
		if($path===false || !is_dir($path))
		{
			echo 'Error: The migration directory does not exist: '.$this->migrationPath."\n";
			exit(1);
		}
		$this->migrationPath=$path;

		return parent::beforeAction($action,$params);
	}

    /**
     * Changes:
     * - load an AR model and use constant name
     * @param $modelClass string may be an alias if it needs importing, ex. OrderStatus or module.sales.models.OrderStatus
     * @param $tableSuffix string new tables suffix, most often the model's table name in singular form with _changes appended, ex. order_status_changes
     * @param $relation string name of relation from model pointing to the main model, ex. orders
     * @param $schema
     */
	public function actionCreate($modelClass, $tableSuffix, $relation, $schema='')
	{
        if (($pos=strrpos($modelClass,'.'))!==false) {
            Yii::import($modelClass);
            $modelClass = substr($modelClass,$pos+1);
        }
        $model = CActiveRecord::model($modelClass);
        $relations = $model->relations();
        $mainModel = CActiveRecord::model($relations[$relation][1]);

        Yii::import('niix.helpers.InflectorHelper');

		$name='m'.gmdate('ymd_His').'_install_fsm';
        $content=strtr($this->getTemplate(), array(
            '{ClassName}'   => $name,
            '{TableName}'   => $model->tableName(),
            '{TableSuffix}' => $tableSuffix,
            '{Schema}'      => !empty($schema) ? $schema.'.' : null,
            '{PrimaryKey}'  => $model->tableSchema->primaryKey,
            '{ForeignKey}'  => InflectorHelper::singularize($mainModel->tableSchema->name).'_id',
            '{MainTableName}'  => $mainModel->tableName(),
            '{MainPrimaryKey}'  => $mainModel->tableSchema->primaryKey,
        ));
		$file=$this->migrationPath.DIRECTORY_SEPARATOR.$name.'.php';

		if($this->confirm("Create new migration '$file'?"))
		{
			file_put_contents($file, $content);
			echo "New migration created successfully.\n";
		}
	}

	public function confirm($message,$default=false)
	{
		if(!$this->interactive)
			return true;
		return parent::confirm($message,$default);
	}

	protected function getTemplate()
	{
		return file_get_contents(Yii::getPathOfAlias($this->templateFile).'.php');
	}
}
