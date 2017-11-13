<?php

namespace backend\models;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "dispute".
 *
 * The followings are the available columns in table 'dispute':
 * @property string $id
 * @property string $ref
 * @property integer $status
 * @property string $added
 * @property string $added_by
 * @property string $closed
 * @property string $closed_by
 */
class Dispute extends ActiveRecord
{

    const STATUS_IN_PROGRESS = 0;
    const STATUS_CLOSED_WIN  = 1;
    const STATUS_CLOSED_LOSS = 2;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'dispute';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['ref, status, added, added_by', 'required'],
            ['status', 'numerical', 'integerOnly' => true],
            ['ref', 'length', 'max' => 255],
            ['added_by, closed_by', 'length', 'max' => 11],
            ['closed', 'safe'],
            ['id, ref, status, added, added_by, closed, closed_by', 'safe', 'on' => 'search'],
        ];
    }

    public function beforeValidate()
    {
        // create token and datetime for new records
        if ($this->isNewRecord) {
            $this->status   = self::STATUS_IN_PROGRESS;
            $this->added    = date('Y-m-d H:i:s');
            $this->added_by = Yii::$app->getUser()->getId();
        } elseif ($this->status > 0) {    //  && empty($this->closed)
            $this->closed    = date('Y-m-d H:i:s');
            $this->closed_by = Yii::$app->getUser()->getId();
        }

        return parent::beforeValidate();
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'        => 'ID',
            'ref'       => 'Ref',
            'status'    => 'Status',
            'added'     => 'Added',
            'added_by'  => 'Added By',
            'closed'    => 'Closed',
            'closed_by' => 'Closed By',
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {

        $query = new ActiveQuery();

        $query->filterWhere(['like', 'id', $this->id]);
        $query->compare(['like', 'ref', $this->ref]);
        $query->compare(['=', 'status', $this->status]);
        $query->compare(['like', 'added', $this->added]);
        $query->compare(['like', 'added_by', $this->added_by]);
        $query->compare(['like', 'closed', $this->closed]);
        $query->compare(['like', 'closed_by', $this->closed_by]);

        return new ActiveDataProvider($this, [
            'query' => $query,
        ]);
    }

}