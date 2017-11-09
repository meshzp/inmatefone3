<?php
namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * This is the model class for table "stat_provider_inbound".
 *
 * The followings are the available columns in table 'stat_provider_inbound':
 * @property string $id
 * @property string $start_date
 * @property string $provider_id
 * @property string $calls
 * @property string $connected
 * @property string $duration
 * @property string $billsec
 *
 * @property Provider $provider
 */
class StatProviderInbound extends ActiveRecord {
    
    public $asr;
    public $acd;
    public $acd_fraction;
    public $billable_duration;
    public $billmin;
    public $date_range;
    public $grouped;    // boolean - whether grouping by provider or not (used to show date by day or by range)

    private $_dateSql;

    /**
     * @return string the associated database table name
     */
    public static function tableName() {
        return 'stat_provider_inbound';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['provider_id, calls, connected', 'length', 'max' => 11],
            ['duration, billsec', 'length', 'max' => 20],
            ['id, start_date, date_range, provider_id, calls, connected, duration, billsec, billmin, asr, acd, billable_duration', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return ActiveQuery
     */
    public function getClientTransaction()
    {
        return $this->hasOne(Provider::className(), ['provider_id' => 'provider_id']);
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'start_date'  => 'Start Date',
            'provider_id' => 'Provider',
            'calls'       => 'Calls',
            'connected'   => 'Connected',
            'duration'    => 'Duration',
            'billsec'     => 'Billsec',
        ];
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search() {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;
        /*
         *  SELECT start_date,provider_id,calls,connected,duration,billsec,
            CONCAT(FORMAT((connected/calls)*100,0),"%") AS asr,
            SEC_TO_TIME(COALESCE(ROUND(billsec/connected),0)) AS acd,
            COALESCE(ROUND((billsec/connected)/60,2),0) AS acd_fraction,
            SEC_TO_TIME(COALESCE(billsec,0)) AS billable_duration
            FROM stat_provider_inbound
         */
        // the following needs to be done first so we can set the grouped flag
        if(!empty($this->provider_id)) {
            // are we grouping by provider?
            if(strstr($this->provider_id,'-range')) {
                $pid = str_replace('-range','',$this->provider_id);
                $criteria->group = 't.provider_id';
                $criteria->compare('t.provider_id',$pid);
                $this->grouped = true;
            }
            else
             $criteria->compare('t.provider_id', $this->provider_id);
        }
        
        if($this->grouped) {
            $asrSql = 'CONCAT(FORMAT((SUM(t.connected)/SUM(t.calls))*100,0),"%")';
            $acdSql = 'SEC_TO_TIME(COALESCE(ROUND(SUM(t.billsec)/SUM(t.connected)),0))';
            $acdFractionSql = 'COALESCE(ROUND((SUM(t.billsec)/SUM(t.connected))/60,2),0)';
            $billableDurationSql = 'SEC_TO_TIME(COALESCE(SUM(t.billsec),0))';
            $criteria->select = array(
                '*',
                'SUM(t.calls) as calls',
                'SUM(t.connected) as connected',
                'SUM(t.duration) as duration',
                'SUM(t.billsec) as billsec',
                'SUM(t.billsec)/60 as billmin',
                $asrSql . " as asr",
                $acdSql . " as acd",
                $acdFractionSql . " as acd_fraction",
                $billableDurationSql . " as billable_duration",
            );
        }
        else {
            $asrSql = 'CONCAT(FORMAT((t.connected/t.calls)*100,0),"%")';
            $acdSql = 'SEC_TO_TIME(COALESCE(ROUND(t.billsec/t.connected),0))';
            $acdFractionSql = 'COALESCE(ROUND((t.billsec/t.connected)/60,2),0)';
            $billableDurationSql = 'SEC_TO_TIME(COALESCE(t.billsec,0))';
            $criteria->select = array(
                '*',
                't.billsec/60 as billmin',
                $asrSql . " as asr",
                $acdSql . " as acd",
                $acdFractionSql . " as acd_fraction",
                $billableDurationSql . " as billable_duration",
            );
        }        
        
        $criteria->with = array(
            'provider'
        );

        
        $criteria->addCondition($this->dateSql);
        
        // temp remove rows with no provider id
        $criteria->addCondition('t.provider_id > 0');

        $criteria->compare('id', $this->id, true);
        //$criteria->compare('start_date', $this->start_date, true);
        
        
        $criteria->compare('calls', $this->calls);
        $criteria->compare('connected', $this->connected);
        $criteria->compare('duration', $this->duration);
        $criteria->compare('billsec', $this->billsec);
        if(!empty($this->billmin))
            $criteria->compare('billsec', $this->billmin*60);

        return new CActiveDataProvider($this, array(
                    'criteria' => $criteria,
                    'sort' => array(
                        'defaultOrder' => 'start_date ASC',
                        'attributes' => array(
                            'provider_id'=>array(
                                'asc'=>'provider.provider_name ASC',
                                'desc'=>'provider.provider_name DESC',
                            ),
                            'asr'=>array(
                                'asc'=>'asr ASC',
                                'desc'=>'asr DESC',
                            ),
                            'acd'=>array(
                                'asc'=>'acd ASC',
                                'desc'=>'acd DESC',
                            ),
                            'acd_fraction'=>array(
                                'asc'=>'acd_fraction ASC',
                                'desc'=>'acd_fraction DESC',
                            ),
                            'billable_duration'=>array(
                                'asc'=>'billable_duration ASC',
                                'desc'=>'billable_duration DESC',
                            ),
                            'billmin'=>array(
                                'asc'=>'billmin ASC',
                                'desc'=>'billmin DESC',
                            ),
                            '*',
                        )
                    ),
                    'pagination' => false,
                ));
    }

    /**
     * @return string
     */
    public function getDateSql() {
        if(!empty($this->_dateSql))
            return $this->_dateSql;
        $this->_dateSql = '';
        if (empty($this->start_date))
            $this->start_date = date('Y-m-01') . ' - ' . date('Y-m-t');
        $dateRange = explode(' - ', $this->start_date);
        if (count($dateRange) == 1) {
            $start = trim($dateRange[0]);
            $this->_dateSql = "start_date = '$start'";
        } elseif (count($dateRange) == 2) {
            $start = trim($dateRange[0]);
            $end = trim($dateRange[1]);
            $this->_dateSql = "(start_date BETWEEN '$start' AND '$end')";
        }
        return $this->_dateSql;
    }

    /**
     * @deprecated
     * @return array
     */
    public function getInboundProviderList() {
        $dateSql = empty($this->dateSql) ? '' : 'WHERE '.$this->dateSql;
        $sql = "SELECT DISTINCT(p.provider_id) AS provider_id,p.provider_name
                FROM stat_provider_inbound s
                INNER JOIN providers p ON s.provider_id = p.provider_id
                $dateSql
                ORDER BY p.provider_name";
        $models = Yii::app()->db->createCommand($sql)->queryAll();
        // adjust the lists so we can sort by day or by range
        $list = array();
        $listTemp = CHtml::listData($models, 'provider_id', 'provider_name');
        //$listRange = array();
        foreach($listTemp as $k => &$v) {
            $list['By Day'][$k] = $v;
            $list['By Date Range'][$k.'-range'] = $v;
        }
        return $list;
    }
    
    /**
     * Render the country name value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridDate')
     * @param $data
     * @return string
     */
    public function gridDate($data) {
        if($this->grouped) {
            return $this->start_date;
        }
        return $data->start_date;
    }

}