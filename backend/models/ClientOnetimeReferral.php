<?php

namespace backend\models;

use Yii;
use backend\helpers\Globals;
use yii\behaviors\TimestampBehavior;
use yii\behaviors\BlameableBehavior;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * This is the model class for table "user_onetime_referral".
 *
 * The followings are the available columns in table 'user_onetime_referral':
 * @property string $id
 * @property string $user_id
 * @property string $referred_by
 * @property integer $created_by
 * @property string $created_at
 * @property string $credited_at
 * @property integer $credit_transaction_id
 * @property string $status
 *
 * The followings are the available model relations:
 * @property UserDatas $referredBy
 * @property AuthUsers $createdBy
 * @property UserDatas $user
 */
class ClientOnetimeReferral extends ActiveRecord // TODO: Inherited from \protected\components\ActiveRecord.php
{
    const STATUS_UNCREDITED = 'uncredited';
    const STATUS_CREDITED   = 'credited'; // $5 one off credit applied

    public $referred_by_name;
    public $referrer_name;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%user_onetime_referral}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['user_id', 'referred_by'], 'required'],
            [['created_by', 'credit_transaction_id'], 'integer'],
            [['user_id', 'referred_by'], 'max' => 11],
            [['status'], 'max' => 32],
            [['credited_at'], 'safe'],
            [['user_id'], 'unique'],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['id', 'user_id', 'referred_by', 'referred_by_name', 'referrer_name', 'created_by', 'created_at', 'credited_at', 'status'], 'safe', 'on' => ['search']],
        ];
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        // NOTE: you may need to adjust the relation name and the related
        // class name for the relations automatically generated below.
        return [
            'referrer'   => [self::BELONGS_TO, 'Client', 'user_id'],
            'referredBy' => [self::BELONGS_TO, 'Client', 'referred_by'],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'id'                    => 'ID',
            'user_id'               => 'User',
            'referred_by'           => 'Client ID',
            'created_by'            => 'Created By',
            'created_at'            => 'Created At',
            'credited_at'           => 'Credited At',
            'status'                => 'Status',
            'credit_transaction_id' => 'Credit Transaction ID',
        ];
    }

    /**
     * @deprecated
     * @return array
     */
    public function scopes()
    {
        return [
            'withReferredBy' => [
                'select' => 'id, referred_by',
                'with'   => [
                    'referredBy' => [
                        'select' => 'user_first_name, user_last_name, user_inmate_first_name, user_inmate_last_name',
                    ],
                ],
            ],
        ];
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;

        $criteria->with = ['referrer'];

        $criteria->compare('id', $this->id);
        $criteria->compare('user_id', $this->user_id);
        $criteria->compare('referred_by', $this->referred_by);

        if (!empty($this->referrer_name)) {
            $criteria->addcondition("(referrer.user_full_name LIKE '%" . $this->referrer_name . "%' OR referrer.user_inmate_full_name LIKE '%" . $this->referrer_name . "%')");
        }

        $criteria->compare('created_by', $this->created_by);
        $criteria->compare('created_at', $this->created_at, true);
        $criteria->compare('credited_at', $this->credited_at, true);
        $criteria->compare('status', $this->status, true);

        return new CActiveDataProvider($this, [
            'criteria'   => $criteria,
            'sort'       => [
                'defaultOrder' => 'referrer.user_last_name',
                'attributes'   => [
                    'referrer_name' => [
                        'asc'  => 'referrer.user_last_name ASC',
                        'desc' => 'referrer.user_last_name DESC',
                    ],
                    '*',
                ],

            ],
            'pagination' => false,
        ]);
    }

    /**
     * Render the name value for grid output.
     * This should be used in a grid using 'value'=>[$model, 'gridName']
     *
     * @param object $data
     *
     * @return string
     */
    public function gridName($data)
    {
        if (empty($data->referrer)) {
            return '** Warning: Client #' . $data->user_id . ' Not Found **';
        }
        $text = $data->referrer->user_last_name . ', ' . $data->referrer->user_first_name . ' / ' . $data->referrer->user_inmate_last_name . ', ' . $data->referrer->user_inmate_first_name;
        $url  = Url::to(['/client/update', ['id' => $data->user_id]]);

        return Html::a($text, $url, ['target' => '_blank']);
    }

    /**
     * Render the name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridName']
     *
     * @param object $data
     *
     * @return string
     */
    public function gridStatus($data)
    {
        return $data->status == ClientOnetimeReferral::STATUS_CREDITED ?
            '<span class="label label-success"><small>Credited<br />' . $data->credited_at . '</small></span>' :
            '<span class="label label-important"><small>Not Credited</small></span>';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class'              => TimestampBehavior::className(),
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => false,
                'value'              => date('Y-m-d H:i:s'),
            ],
            [
                'class'              => BlameableBehavior::className(),
                'createdByAttribute' => 'created_by',
                'updatedByAttribute' => false,
                'value'              => Globals::user()->id,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        if ($this->isNewRecord) {
            $this->status = self::STATUS_UNCREDITED;
        }

        return parent::beforeSave();
    }

    /**
     * @deprecated
     * @param bool $skipArchive
     *
     * @return bool|false|int
     */
    public function delete($skipArchive = false)
    {
        // referrals that have been credited should not be deleted
        if ($this->status != self::STATUS_UNCREDITED) {
            return false;
        }

        if ($skipArchive) {
            return parent::delete();
        }
        // Records should not be fully deleted ideally
        // another table & model has been created to archive deleted items
        if (!$this->getIsNewRecord()) {
            $archiveModel                        = new ClientOnetimeReferralRemoved();
            $archiveModel->id                    = $this->id;
            $archiveModel->user_id               = $this->user_id;
            $archiveModel->referred_by           = $this->referred_by;
            $archiveModel->created_by            = $this->created_by;
            $archiveModel->created_at            = $this->created_at;
            $archiveModel->credited_at           = $this->credited_at;
            $archiveModel->credit_transaction_id = $this->credit_transaction_id;
            $archiveModel->status                = $this->status;
            $archiveModel->removed_at            = date('Y-m-d H:i:s');
            $archiveModel->removed_by            = Globals::user()->id;
            if ($archiveModel->save()) {
                return parent::delete();
            } else {
                return false;
            }
        } else {
            throw new CDbException(Yii::t('yii', 'The active record cannot be deleted because it is new.'));
        }
    }

    /**
     * @deprecated
     * @param int $clientId
     * @param bool $asLink
     *
     * @return null
     */
    public function fetchReferredBy($clientId, $asLink = true)
    {
        $model = $this->withReferredBy()->find('t.user_id=:clientId', [':clientId' => $clientId]);
        if (empty($model) || empty($model->referredBy)) {
            return null;
        }
        if ($asLink) {
            $text = $model->referredBy->user_last_name . ', ' . $model->referredBy->user_first_name . ' / ' . $model->referredBy->user_inmate_last_name . ', ' . $model->referredBy->user_inmate_first_name;
            $url  = Url::to(['/client/update', ['id' => $model->referred_by]]);

            return Html::a($text, $url);
        } else {
            return $model;
        }
    }
}
