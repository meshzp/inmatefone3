<?php

namespace backend\models;

use backend\helpers\Globals;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * This is the model class for table "user_logs".
 *
 * The followings are the available columns in table 'user_logs':
 * @property string $log_id
 * @property integer $user_id
 * @property integer $user_status
 * @property string $log_datetime
 * @property integer $log_by
 */
class ClientLog extends ActiveRecord // TODO: Inherited from \protected\components\ActiveRecord.php
{

    const STATUS_CANCELLED = 0;
    const STATUS_ACTIVE    = 1;
    const STATUS_BLOCKED   = 2;
    const STATUS_INACTIVE  = 3;
    const STATUS_PENDING   = 4;

    private $_statusOptions;
    private $_statusClassOptions;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%user_logs}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['user_id', 'user_status', 'log_by'], 'integer'],
            [['log_datetime'], 'safe'],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['log_id', 'user_id', 'user_status', 'log_datetime', 'log_by'], 'safe', 'on' => ['search']],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'log_id'       => 'ID',
            'user_id'      => 'User',
            'user_status'  => 'Status',
            'log_datetime' => 'Datetime',
            'log_by'       => 'By',
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class'              => TimestampBehavior::className(),
                'createdAtAttribute' => 'log_datetime',
                'updatedAtAttribute' => false,
                'value'              => function($data) {
                    if (empty($data->log_datetime) || $data->log_datetime == '0000-00-00 00:00:00') {
                        return date('Y-m-d H:i:s');
                    }

                    return false;
                },
            ],
            [
                'class'              => BlameableBehavior::className(),
                'createdByAttribute' => 'log_by',
                'updatedByAttribute' => false,
                'value'              => function($data) {
                    if (empty($data->log_by)) {
                        return Globals::user()->id;
                    }

                    return false;
                },
            ],
        ];
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param array $params
     *
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search($params)
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $query = self::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => [
                    'log_id' => SORT_DESC,
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'log_id'       => $this->log_id,
            'user_id'      => $this->user_id,
            'user_status'  => $this->user_status,
            'log_datetime' => $this->log_datetime,
            'log_by'       => $this->log_by,
        ]);

        if (!empty($this->log_by)) {
            $query->andWhere("(log_by = '" . $this->log_by . "' OR admin.display_name LIKE '%" . $this->log_by . "%')");
        }

        return $dataProvider;
    }

    /**
     * @deprecated
     * @return bool
     */
    public function beforeSave()
    {
        // check if the status has actually changed
        $query = new Query();
        $query->select('user_status');
        $query->andFilterCompare('user_id', $this->user_id);
        $query->orderBy(['log_id' => SORT_DESC]);
        $lastEntry = self::find()->union($query);
        if ($lastEntry && $lastEntry->user_status == $this->user_status) {
            return false;
        }

        return parent::beforeSave();
    }

    /**
     * @param null|int $status
     *
     * @return mixed
     */
    public function getStatusOptions($status = null)
    {
        if (empty($this->_statusOptions)) {
            $statusNames          = [
                self::STATUS_CANCELLED => 'Cancelled',
                self::STATUS_ACTIVE    => 'Active',
                self::STATUS_BLOCKED   => 'Blocked',
                self::STATUS_INACTIVE  => 'Inactive',
                self::STATUS_PENDING   => 'Pending',
            ];
            $this->_statusOptions = self::getConstants('STATUS_', __CLASS__, $statusNames);
        }

        return $status !== null && isset($this->_statusOptions[$status]) ? $this->_statusOptions[$status] : $this->_statusOptions;
    }

    /**
     * @param null|itn $status
     *
     * @return mixed
     */
    public function getStatusClassOptions($status = null)
    {
        if (empty($this->_statusClassOptions)) {
            $statusClassNames          = [
                self::STATUS_CANCELLED => 'status_cancelled',
                self::STATUS_ACTIVE    => 'status_active',
                self::STATUS_BLOCKED   => 'status_blocked',
                self::STATUS_INACTIVE  => 'status_inactive',
                self::STATUS_PENDING   => 'status_pending',
            ];
            $this->_statusClassOptions = self::getConstants('STATUS_', __CLASS__, $statusClassNames);
        }

        return $status !== null && isset($this->_statusClassOptions[$status]) ? $this->_statusClassOptions[$status] : $this->_statusClassOptions;
    }

    public function getAdmin()
    {
        return $this->hasOne(User::className(), ['log_by' => 'id']);
    }

}
