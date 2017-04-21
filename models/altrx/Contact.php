<?php

namespace app\models\altrx;

use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "Contact".
 *
 * @property integer $id
 * @property integer $idUser
 * @property string $name
 * @property string $email
 *
 */
class Contact extends \yii\db\ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return 'Contact';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['idUser', 'name', 'email'], 'required'],
			[['idUser'], 'integer'],
			[['name', 'email'], 'string', 'max' => 100],
			[['email'], 'email', 'enableIDN' => false],
			[['idUser', 'name'], 'unique', 'targetAttribute' => ['idUser', 'name'], 'message' => 'The combination of Id User and Name has already been taken.']
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id'     => 'ID',
			'idUser' => 'Id User',
			'name'   => 'Name',
			'email'  => 'Email',
		];
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getUser()
	{
		return $this->hasOne(User::className(), ['id' => 'idUser']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getTagList()
	{
		return $this->hasMany(Tag::className(), ['id' => 'idTag'])->viaTable('TagContact', ['idContact' => 'id']);
	}

	/**
	 *
	 */
	public function extraFields()
	{
		return ['user', 'tagList'];
	}

	/**
	 * Метод сохранения данных по текущему контакту и его тегам (добавляя связи контакт-тег). Может добавлять в систему
	 * отсутсвующие теги.
	 * @param array $data Массив атрибутов для сохранения (включая массив тегов в атрибуте tagList).
	 * @param array $errorList Массив сообщений об ошибках.
	 */
	public function complexSave($data, & $errorList)
	{
		$errorList = [];

		$this->setAttributes($data);
		if (!$this->save()) {
			$errorList = $this->getFirstErrors();
			return false;
		}

		// 1) Удалили все теги
		if (empty($data['tagList'])
			&& !empty($this->tagList)
		) {
			Yii::$app->db
				->createCommand()
				->delete('TagContact', ['idContact' => $this->id])
				->execute();
		// 2) Добавили новый набор тегов
		} elseif (!empty($data['tagList'])
			&& empty($this->tagList)
		) {
			$insertCommand = Yii::$app->db
				->createCommand('INSERT INTO TagContact(idContact, idTag) VALUES(:idContact, :idTag)');

			foreach ($data['tagList'] as $dataTag) {
				if (!empty($dataTag['name'])) {
					$tag = Tag::find()->where(['name' => $dataTag['name']])->one();
					if (empty($tag)) {
						$tag = new Tag();
						$tag->name = $dataTag['name'];
						if (!$tag->save()) {
							$errorList = $tag->getFirstErrors();
							return false;
						}
					}
					try {
						$insertCommand->bindValues([':idContact' => $this->id, ':idTag' => $tag->id])->execute();
					} catch (\Exception $e) {
						$errorList[] = $e->getMessage();
						return false;
					}
				}
			}
		// 3) Что-то нужно удалить, что-то добавить
		} elseif (!empty($data['tagList'])
			&& !empty($this->tagList)
		) {
			// Добавляем теги которых нет
			foreach ($data['tagList'] as $key => $dataTag) {
				if (empty($dataTag['id'])) {
					$tag = new Tag();
					$tag->name = $dataTag['name'];
					if (!$tag->save()) {
						$errorList = $tag->getFirstErrors();
						return false;
					}
					$data['tagList'][$key] = $tag->toArray(['id', 'name']);
				}
			}
			$data['tagList'] = ArrayHelper::index($data['tagList'], 'name');
			$tagList = ArrayHelper::index($this->tagList, 'name');

			// Связи с тегами которые нужно удалить
			$delCondiction = ['idContact' => $this->id, 'idTag' => []];
			$deleteNameList = array_diff(array_keys($tagList), array_keys($data['tagList']));
			foreach ($deleteNameList as $tagName) {
				$delCondiction['idTag'][] = $tagList[$tagName]['id'];
			}
			if (!empty($delCondiction['idTag'])) {
				Yii::$app->db->createCommand()->delete('TagContact', $delCondiction)->execute();
			}

			// Связи с тегами которые нужно добавить
			$insertList = [];
			$insertNameList = array_diff(array_keys($data['tagList']), array_keys($tagList));
			foreach ($insertNameList as $tagName) {
				$insertList[] = [$this->id, $data['tagList'][$tagName]['id']];
			}
			if (!empty($insertList)) {
				Yii::$app->db->createCommand()->batchInsert('TagContact', ['idContact', 'idTag'], $insertList)->execute();
			}
		}

		return true;
	}

}
