<?php

namespace app\controllers\api;

use Yii;
use yii\rest\ActiveController;
use app\models\altrx\User;
use yii\web\HttpException;

class UserController extends ActiveController
{
	public $modelClass = 'app\models\altrx\User';

	/**
	 *
	 */
	public function actionComplex($id)
	{
		$data = Yii::$app->request->getBodyParams();
		if (empty($data['User'])) {

			return $this->responseError(['Miss User']);
		}

		$user = User::find()
			->with('contactList.tagList')
			->where(['id' => $id])
			->one();
		if (empty($user)) {
			$user = new User();
			$user->id = $id;
			$user->load($data, 'User');
			Yii::$app->response->statusCode = 201;
		}
		if (!$user->complexSave($data['User'], $complexErrorList)) {

			return $this->responseError($complexErrorList);
		}

		Yii::$app->response->statusCode = 200;
		return '';
	}

	/**
	 *
	 */
	private function responseError($errorList)
	{
		Yii::$app->response->statusCode = 422;
		return ['errors' => array_values($errorList)];
	}

}
