<?php

use yii\db\Schema;
use yii\db\Migration;

class m170421_104835_init extends Migration
{
	public function up()
	{
		$this->execute(file_get_contents(__DIR__ . '/altarix.sql'));
	}

	public function down()
	{
		echo "m170421_104835_init cannot be reverted.\n";

		return false;
	}

}
