<?php

namespace app\models\altrx;

use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "User".
 *
 * @property integer $id
 * @property string $name
 *
 * @property Contact[] $contacts
 * @property Tag $tag
 */
class User extends \yii\db\ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return 'User';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['name'], 'required'],
			[['name'], 'string', 'max' => 100],
			[['name'], 'unique'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id'   => 'ID',
			'name' => 'Name',
		];
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getContactList()
	{
		return $this->hasMany(Contact::className(), ['idUser' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getTagList()
	{
		return $this->hasMany(Tag::className(), ['idUser' => 'id'])
			->indexBy('name');
	}

	/**
	 *
	 */
	public function extraFields()
	{
		return [
			'contactList' => function ($model) {
				$result = [];
				foreach ($model->contactList as $contactModel) {
					$contact = $contactModel->toArray();
					$contact['tagList'] = $contactModel->tagList;
					$result[] = $contact;
				}

				return $result;
			},
			'tagList',
		];
	}

	/**
	 *
	 */
	public function complexSave($data, & $complexErrorList)
	{
		$complexErrorList = [];
		$this->setAttributes($data);
		if (!$this->save()) {
			$complexErrorList = $this->getFirstErrors();
			return false;
		}

		$transaction = Yii::$app->db->beginTransaction();
		// 1) Удалили все контакты
		if (empty($data['contactList'])
			&& !empty($this->contactList)
		) {
			foreach ($this->contactList as $contact) {
				$contact->delete();
			}
		// 2) Добавили новый набор контактов
		} elseif (!empty($data['contactList'])
			&& empty($this->contactList)
		) {
			foreach ($data['contactList'] as $dataContact) {
				$contact = new Contact();
				$contact->idUser = $this->id;
				if (!$contact->complexSave($dataContact, $contactErrorList)) {
					$complexErrorList = array_merge($complexErrorList, $contactErrorList);
					$transaction->rollBack();
					return false;
				}
			}
		// 3) Что-то нужно удалить, что-то добавить
		} elseif (!empty($data['contactList'])
			&& !empty($this->contactList)
		) {
			$data['contactList'] = ArrayHelper::index($data['contactList'], 'name');
			$contactList = ArrayHelper::index($this->contactList, 'name');

			// Контакты которые нужно удалить
			$deleteNameList = array_diff(array_keys($contactList), array_keys($data['contactList']));
			if (!empty($deleteNameList)) {
				$deleteContactList = Contact::find()->where(['name' => $deleteNameList])->all();
				foreach ($deleteContactList as $deleteContact) {
					$deleteContact->delete();
				}
			}

			// Контакты которые нужно добавить
			$insertNameList = array_diff(array_keys($data['contactList']), array_keys($contactList));
			if (!empty($insertNameList)) {
				foreach ($insertNameList as $contactName) {
					$contact = new Contact();
					$contact->idUser = $this->id;
					if (!$contact->complexSave($data['contactList'][$contactName], $contactErrorList)) {
						$complexErrorList = array_merge($complexErrorList, $contactErrorList);
						$transaction->rollBack();
						return false;
					}
				}
			}

			// Контакты которые возможно нужно обновить
			$updateNameList = array_intersect(array_keys($data['contactList']), array_keys($contactList));
			if (!empty($updateNameList)) {
				$updateContactList = Contact::find()
					->where(['name' => $updateNameList])
					->indexBy('name')
					->all();
				foreach ($updateNameList as $contactName) {
					if (!$updateContactList[$contactName]->complexSave($data['contactList'][$contactName], $contactErrorList)) {
						$complexErrorList = array_merge($complexErrorList, $contactErrorList);
						$transaction->rollBack();
						return false;
					}
				}
			}
		}

		$transaction->commit();
		return true;
	}

}
