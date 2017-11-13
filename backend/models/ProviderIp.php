<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;

/**
 * This is the model class for table "providers_ip".
 *
 * The followings are the available columns in table 'providers_ip':
 * @property string $ip_id
 * @property integer $provider_id
 * @property string $ip_address
 * @property string $ip_name
 * @property string $ip_notes
 * @property string $datetime
 * @property integer $admin_id
 * @property integer $status
 *
 * @property Provider $provider
 */
class ProviderIp extends ActiveRecord
{

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'providers_ip';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['ip_address, ip_name, ip_notes', 'required'],
            ['provider_id, admin_id, status', 'numerical', 'integerOnly' => true],
            ['ip_address, ip_name', 'length', 'max' => 255],
            ['datetime', 'safe'],
            ['ip_id, provider_id, ip_address, ip_name, ip_notes, datetime, admin_id, status', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'ip_id'       => 'Ip',
            'provider_id' => 'Provider',
            'ip_address'  => 'Ip Address',
            'ip_name'     => 'Ip Name',
            'ip_notes'    => 'Ip Notes',
            'datetime'    => 'Datetime',
            'admin_id'    => 'Admin',
            'status'      => 'Status',
        ];
    }

    /**
     * @return bool
     */
    public function beforeSave()
    {
        // date and admin info
        if ($this->isNewRecord) {
            $this->datetime = date('Y-m-d H:i:s');
            $this->admin_id = Yii::$app->getUser()->getId();
        }

        return parent::beforeSave();
    }

    /**
     * @throws Exception
     */
    public function afterSave()
    {
        // update parent provider's update flag
        if (!empty($this->provider_id)) {
            $provider = Provider::findOne($this->provider_id);
            if ($provider !== null) {
                $provider->provider_update = 1;
                if (!$provider->save()) {
                    throw new Exception(Yii::t('yii', 'Error saving update flag in parent provider.'));
                }
            } else {
                throw new Exception(Yii::t('yii', 'Parent provider not found.'));
            }
        } else {
            throw new Exception(Yii::t('yii', 'Cannot find parent provider ID.'));
        }
        parent::afterSave();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function delete()
    {
        // Provider Ip records are not deleted out but the status is changed to 0
        // It is also necessary to set the parent provider record update flag to 1
        if (!$this->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.delete()', 'system.db.ar.CActiveRecord');
            if ($this->beforeDelete()) {
                $this->provider_status = 0;
                $result                = $this->save();
                if ($result) {
                    $provider = Provider::findOne($this->provider_id);
                    if ($provider !== null) {
                        $provider->provider_update = 1;
                        $result                    = $provider->save();
                    }
                }
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
    public function getProvider()
    {
        return $this->hasOne(Provider::className(), ['provider_id' => 'provider_id']);
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

        $criteria->compare('ip_id', $this->ip_id, true);
        $criteria->compare('provider_id', $this->provider_id);
        $criteria->compare('ip_address', $this->ip_address, true);
        $criteria->compare('ip_name', $this->ip_name, true);
        $criteria->compare('ip_notes', $this->ip_notes, true);
        $criteria->compare('datetime', $this->datetime, true);
        $criteria->compare('admin_id', $this->admin_id);
        $criteria->compare('status', 1);

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
        ]);
    }

}