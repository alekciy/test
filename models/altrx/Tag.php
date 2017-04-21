<?php

namespace app\models\altrx;

use Yii;

/**
 * This is the model class for table "Tag".
 *
 * @property integer $id
 * @property integer $idUser
 * @property string $name
 *
 */
class Tag extends \yii\db\ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return 'Tag';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['name'], 'required'],
			[['name'], 'string', 'max' => 100],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id'     => 'ID',
			'name'   => 'Name',
		];
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getContactList()
	{
		return $this->hasMany(Contact::className(), ['id' => 'idContact'])->viaTable('TagContact', ['idTag' => 'id']);
	}
}
