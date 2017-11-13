<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;

/**
 * This is the model class for table "providers".
 *
 * The followings are the available columns in table 'providers':
 * @property string $provider_id
 * @property string $provider_name
 * @property string $provider_type
 * @property string $provider_dids_leading_digit
 * @property string $provider_datetime
 * @property integer $admin_id
 * @property integer $provider_status
 * @property integer $provider_update
 *
 * @property ProviderIp $ips
 */
class Provider extends ActiveRecord
{

    const STATUS_INACTIVE  = 0;
    const STATUS_ACTIVE    = 1;
    const TYPE_ORIGINATING = 'originating';
    const TYPE_TERMINATING = 'terminating';

    private $_statusOptions;
    private $_typeOptions;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'providers';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['provider_name, provider_type', 'required'],
            ['admin_id, provider_status, provider_update', 'numerical', 'integerOnly' => true],
            ['provider_name, provider_type, provider_dids_leading_digit', 'length', 'max' => 255],
            ['provider_datetime,ips', 'safe'],
            ['provider_id, provider_name, provider_type, provider_dids_leading_digit, provider_datetime, admin_id, provider_status, provider_update', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'provider_id'                 => 'ID',
            'provider_name'               => 'Name',
            'provider_type'               => 'Type',
            'provider_dids_leading_digit' => 'Dids Leading Digit',
            'provider_datetime'           => 'Datetime',
            'admin_id'                    => 'Admin',
            'provider_status'             => 'Status',
            'provider_update'             => 'Update',
        ];
    }

    public function beforeSave()
    {
        // date and admin info
        if ($this->isNewRecord) {
            $this->provider_datetime = date('Y-m-d H:i:s');
            $this->admin_id          = Yii::$app->getUser()->getId();
        }

        return parent::beforeSave();
    }

    /**
     * @param bool $permanently
     * @throws Exception
     * @return bool|false|int
     */
    public function delete($permanently = false)
    {
        if ($permanently) {
            return parent::delete();
        }
        // Provider records are not deleted out but the status is changed to 0 and update flag set to 1
        if (!$this->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.delete()', 'system.db.ar.CActiveRecord');
            if ($this->beforeDelete()) {
                $this->provider_status = 0;
                $this->provider_update = 1;
                $result                = $this->save();
                $this->afterDelete();

                return $result;
            } else {
                return false;
            }
        } else {
            throw new Exception(Yii::t('yii', 'The active record cannot be deleted because it is new.'));
        }
    }

    /**
     * @return ActiveQuery
     */
    public function getIps()
    {
        return $this->hasMany(ProviderIp::className(), ['provider_id' => 'provider_id']);
    }

    /**
     * @deprecated
     * @return array
     */
    public function scopes()
    {
        return [
            'enabled'         => [
                'condition' => 'provider_status=1',
            ],
            'byName'          => [
                'order' => 'provider_name ASC',
            ],
            'originatingOnly' => [
                'condition' => 'provider_type="' . self::TYPE_ORIGINATING . '"',
            ],
            'terminatingOnly' => [
                'condition' => 'provider_type="' . self::TYPE_TERMINATING . '"',
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

        $criteria->compare('provider_id', $this->provider_id, true);
        $criteria->compare('provider_name', $this->provider_name, true);
        $criteria->compare('provider_type', $this->provider_type, true);
        $criteria->compare('provider_dids_leading_digit', $this->provider_dids_leading_digit, true);
        $criteria->compare('provider_datetime', $this->provider_datetime, true);
        $criteria->compare('admin_id', $this->admin_id);
        $criteria->compare('provider_status', 1);    // ($this->provider_status === null ? 1 : $this->provider_status)
        $criteria->compare('provider_update', $this->provider_update);

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
        ]);
    }

    /**
     * @deprecated
     * @param null $status
     *
     * @return mixed
     */
    public function getStatusOptions($status = null)
    {
        if (empty($this->_statusOptions)) {
            $statusNames          = [
                self::STATUS_INACTIVE => 'Inactive',
                self::STATUS_ACTIVE   => 'Active',
            ];
            $this->_statusOptions = self::getConstants('STATUS_', __CLASS__, $statusNames);
        }

        return $status !== null && isset($this->_statusOptions[$status]) ? $this->_statusOptions[$status] : $this->_statusOptions;
    }

    /**
     * @deprecated
     * @return mixed
     */
    public function getTypeOptions()
    {
        if (empty($this->_typeOptions)) {
            $this->_typeOptions = self::getConstants('TYPE_', __CLASS__, self::CONSTANT_FORMAT_LABEL);
        }

        return $this->_typeOptions;
    }

    /**
     * @param $gridId
     * @param string $separator
     *
     * @return string
     */
    public function getIpList($gridId, $separator = "<br />")
    {
        $ret = [];
        foreach ($this->ips as $ip) {
            $ret[] = "$ip->ip_name ($ip->ip_address)";
        }

        return implode($separator, $ret);
    }

    /**
     * @deprecated
     * @param null $type
     * @param bool $addDefaultOption
     *
     * @return array
     */
    public function getProviderList($type = null, $addDefaultOption = false)
    {
        if ($type === null) {
            $type = self::TYPE_ORIGINATING;
        }
        switch ($type) {
            case self::TYPE_ORIGINATING:
                $models = $this->enabled()->originatingOnly()->byName()->findAll();
                break;
            case self::TYPE_TERMINATING:
                $models = $this->enabled()->terminatingOnly()->byName()->findAll();
                break;
            default:
                $models = $this->enabled()->byName()->findAll();
                break;
        }
        $list = CHtml::listData($models, 'provider_id', 'provider_name');
        if ($addDefaultOption) {
            $list = [0 => 'Default'] + $list;
        }

        return $list;
    }

}