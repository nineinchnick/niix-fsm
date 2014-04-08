<?php

class {ClassName} extends CDbMigration
{
	public function safeUp()
	{

		$query = <<<SQL
SQL;
		$this->execute($query);
		$this->execute('');
	}

	public function safeDown()
	{
        $this->dropTable('{{possible_status_changes}}');
        $this->dropTable('{{performed_status_changes}}');
	}
}

