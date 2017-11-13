<?php

namespace backend\models;

use Yii;
use backend\helpers\Globals;
use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\behaviors\TimestampBehavior;
use yii\behaviors\BlameableBehavior;

/**
 * This is the model class for table "users_associations".
 *
 * The followings are the available columns in table 'users_associations':
 * @property string $association_id
 * @property string $user_id
 * @property integer $associated_user_id
 * @property string $association_datetime
 * @property integer $association_admin_id
 * @property string $association_cancel_datetime
 * @property integer $association_cancel_admin_id
 * @property integer $association_status
 */
class ClientAssociation extends ActiveRecord // Inherited from \protected\components\ActiveRecord.php
{

    /**
     * @var string
     */
    public $associated_name;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%users_associations}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['user_id', 'associated_user_id'], 'required'],
            [['associated_user_id', 'association_admin_id', 'association_cancel_admin_id', 'association_status'], 'integer'],
            [['user_id'], 'max' => 255],
            [['user_id'], 'filter', 'filter' => 'checkAssociation', 'on' => ['insert']],
            [['association_datetime', 'association_cancel_datetime'], 'safe'],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['association_id', 'user_id', 'associated_user_id', 'associated_name', 'association_datetime', 'association_admin_id', 'association_cancel_datetime', 'association_cancel_admin_id', 'association_status'], 'safe', 'on' => ['search']],
        ];
    }

    /**
     * @return bool
     */
    public function beforeSave()
    {
        if ($this->isNewRecord) {
            $this->association_status = 1;
        }

        return parent::beforeSave();
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class'              => TimestampBehavior::className(),
                'createdAtAttribute' => 'association_datetime',
                'updatedAtAttribute' => false,
                'value'              => date('Y-m-d H:i:s'),
            ],
            [
                'class'              => BlameableBehavior::className(),
                'createdByAttribute' => 'association_admin_id',
                'updatedByAttribute' => false,
                'value'              => Globals::user()->id,
            ],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'association_id'              => 'AID',
            'user_id'                     => 'Client ID',
            'associated_user_id'          => 'CID', // Associated Client ID
            'association_datetime'        => 'Datetime',
            'association_admin_id'        => 'Admin',
            'association_cancel_datetime' => 'Cancel Datetime',
            'association_cancel_admin_id' => 'Cancel Admin',
            'association_status'          => 'Status',
        ];
    }

    /**
     * @deprecated
     * @return array
     */
    public function scopes()
    {
        return [
            'enabled'        => [
                'condition' => 'association_status=1',
            ],
            'associatedWith' => [
                'select'    => 'association_id, user_id',
                'condition' => 'association_status!=0',
                'with'      => [
                    'associatedParent' => [
                        'select' => 'user_first_name, user_last_name, user_inmate_first_name, user_inmate_last_name',
                    ],
                ],
            ],
        ];
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;

        $criteria->with = ['associated'];

        $criteria->compare('association_id', $this->association_id);
        $criteria->compare('t.user_id', $this->user_id);
        $criteria->compare('associated_user_id', $this->associated_user_id);
        if (!empty($this->associated_name)) {
            $criteria->addcondition("(associated.user_full_name LIKE '%" . $this->associated_name . "%' OR associated.user_inmate_full_name LIKE '%" . $this->associated_name . "%')");
        }

        $criteria->compare('association_datetime', $this->association_datetime, true);
        //$criteria->compare('association_admin_id', $this->association_admin_id);
        //$criteria->compare('association_cancel_datetime', $this->association_cancel_datetime, true);
        //$criteria->compare('association_cancel_admin_id', $this->association_cancel_admin_id);
        $criteria->compare('association_status', $this->association_status);

        return new CActiveDataProvider($this, [
            'criteria'   => $criteria,
            'sort'       => [
                'defaultOrder' => 'association_datetime DESC',
                'attributes'   => [
                    'associated_name' => [
                        'asc'  => 'associated.user_last_name ASC',
                        'desc' => 'associated.user_last_name DESC',
                    ],
                    '*', // this adds all of the other columns as sortable
                ],
            ],
            'pagination' => false,
        ]);
    }

    /**
     * @param int $clientId
     * @param bool $asLink
     *
     * @return null|string
     */
    public function fetchAssociatedWith($clientId, $asLink = true)
    {
        $model = $this->associatedWith()->find('associated_user_id=:clientId', [':clientId' => $clientId]);
        if (empty($model) || empty($model->associatedParent)) {
            return null;
        }
        if ($asLink) {
            $text = $model->associatedParent->user_last_name . ', ' . $model->associatedParent->user_first_name . ' / ' . $model->associatedParent->user_inmate_last_name . ', ' . $model->associatedParent->user_inmate_first_name;
            $url  = Url::to(['/client/update', ['id' => $model->user_id]]);

            return Html::a($text, $url);
        } else {
            return $model;
        }
    }

    /**
     * Render the name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridName']
     *
     * @param object $data
     *
     * @return string
     */
    public function gridName($data)
    {
        if (empty($data->associated)) {
            return "** Warning: Client #{$data->associated_user_id} Not Found **";
        }
        $text = $data->associated->user_last_name . ', ' . $data->associated->user_first_name . ' / ' . $data->associated->user_inmate_last_name . ', ' . $data->associated->user_inmate_first_name;
        $url  = Url::to(['/client/update', ['id' => $data->associated_user_id]]);

        return Html::a($text, $url, ['target' => '_blank']);
    }

    /**
     * @deprecated
     * Custom validation for creating the association
     *
     * @param string $attribute
     */
    public function checkAssociation($attribute)
    {
        $this->user_id = numbersOnly($this->user_id);
        if ($this->associated_user_id == $this->user_id) {
            $this->addError($attribute, 'You cannot add an assocation to the same client');
        } else {
            $exists = $this->count('associated_user_id=:associatedUserId AND user_id=:userId AND association_status!=0', [':associatedUserId' => $this->associated_user_id, ':userId' => $this->user_id]);
            if ($exists) {
                $this->addError($attribute, 'An association with this client already exists');
            }
        }
    }

    /**
     * @deprecated
     * is this correct?? not yet checked lionel's code
     *
     * @param bool $permanently
     *
     * @return bool|false|int
     */
    public function delete($permanently = false)
    {
        if ($permanently) {
            return parent::delete();
        }

        if (!$this->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.delete()', 'system.db.ar.CActiveRecord');
            if ($this->beforeDelete()) {
                $this->association_status          = 0;
                $this->association_cancel_datetime = date('Y-m-d H:i:s');
                $this->association_cancel_admin_id = user()->id;
                $result                            = $this->save();
                $this->afterDelete();

                return $result;
            } else {
                return false;
            }
        } else {
            throw new CDbException(Yii::t('yii', 'The active record cannot be deleted because it is new.'));
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAssociated()
    {
        return $this->hasOne(Client::className(), ['associated_user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAssociatedParent()
    {
        return $this->hasOne(Client::className(), ['user_id' => 'id']);
    }
}
