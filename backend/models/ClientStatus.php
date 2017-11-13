<?php

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "user_status".
 *
 * The followings are the available columns in table 'user_status':
 * @property string $id
 * @property integer $user_id
 * @property integer $old_status
 * @property integer $new_status
 * @property string $called_by
 * @property string $comment
 * @property string $created
 */
class ClientStatus extends ActiveRecord
{

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'user_status';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['user_id, created', 'required'],
            ['user_id, old_status, new_status', 'numerical', 'integerOnly' => true],
            ['called_by', 'length', 'max' => 100],
            ['comment', 'length', 'max' => 255],
            ['id, user_id, old_status, new_status, called_by, comment, created', 'safe', 'on' => 'search'],
        ];
    }

    public function beforeValidate()
    {
        if ($this->isNewRecord) {
            $this->created = date('Y-m-d H:i:s');
        }

        return parent::beforeValidate();
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'         => 'ID',
            'user_id'    => 'User',
            'old_status' => 'Old Status',
            'new_status' => 'New Status',
            'called_by'  => 'Called By',
            'comment'    => 'Comment',
            'created'    => 'Created',
        ];
    }

    public static function process($clientId, $calledBy = null)
    {
        if (empty($clientId)) {
            return false;
        }
        $clientStatus            = new ClientStatus;
        $clientStatus->user_id   = $clientId;
        $clientStatus->called_by = $calledBy;

        return $clientStatus->save();
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {

        $query = new ActiveQuery();

        $query->filterWhere(['like', 'id', $this->id]);
        $query->andFilterWhere(['=', 'user_id', $this->user_id]);
        $query->andFilterWhere(['=', 'old_status', $this->old_status]);
        $query->andFilterWhere(['=', 'new_status', $this->new_status]);
        $query->andFilterWhere(['like', 'called_by', $this->called_by]);
        $query->andFilterWhere(['like', 'comment', $this->comment]);
        $query->andFilterWhere(['like', 'created', $this->created]);

        return new ActiveDataProvider([
            'query' => $query,
        ]);
    }
}
