<?php

namespace backend\models;

use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "campaign".
 *
 * The followings are the available columns in table 'campaign':
 * @property string $id
 * @property string $name
 * @property string $description
 * @property integer $send_type
 * @property integer $send_to
 * @property string $email_from_address
 * @property string $email_from_name
 * @property string $email_subject
 * @property string $email_html
 * @property string $email_text
 * @property string $sms_from
 * @property string $sms_text
 * @property string $filter
 * @property integer $status
 * @property string $created_at
 * @property string $start_at
 *
 * The followings are the available model relations:
 * @property CampaignEmail[] $campaignEmails
 * @property CampaignSms[] $campaignSms
 */
class Campaign extends ActiveRecord
{

    // the following are bitwise constants
    const SEND_TYPE_EMAIL          = 1;
    const SEND_TYPE_SMS            = 2;
    const SEND_TO_CLIENT_EMAIL     = 1;
    const SEND_TO_CLIENT_PHONE     = 2;
    const SEND_TO_CLIENT_REDIRECTS = 4;

    const STATUS_NOT_STARTED = 0;
    const STATUS_QUEUED      = 1;
    const STATUS_RUNNING     = 2;
    const STATUS_PAUSED      = 3;
    const STATUS_FINISHED    = 4;

    // client filter attributes
    public $client_status;
    public $client_facility_type;
    public $client_exclude_plans;

    public static $sendTypeOptions = [
        self::SEND_TYPE_EMAIL => 'Email',
        self::SEND_TYPE_SMS   => 'SMS',
    ];

    public static $sendToOptions = [
        self::SEND_TO_CLIENT_EMAIL     => 'Client Email',
        self::SEND_TO_CLIENT_PHONE     => 'Client Phone',
        self::SEND_TO_CLIENT_REDIRECTS => 'Client Redirect Numbers',
    ];

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%campaign}}';
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
        ];
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['name'], 'required'],
            [['send_type', 'send_to', 'status'], 'integer'],
            [['name'], 'max' => 64],
            [['description', 'email_from_address', 'email_from_name', 'email_subject'], 'max' => 255],
            [['sms_from'], 'max' => 20],
            [['client_status', 'client_facility_type', 'client_exclude_plans'], 'safe'],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['id', 'name', 'description', 'send_type', 'email_from_address', 'email_from_name', 'email_subject', 'email_html', 'email_text', 'sms_from', 'sms_text', 'filter', 'status', 'created_at', 'start_at'], 'safe'],
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
            'campaignEmails' => [self::HAS_MANY, 'CampaignEmail', 'campaign_id'],
            'campaignSms'    => [self::HAS_MANY, 'CampaignSms', 'campaign_id'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'                 => 'ID',
            'name'               => 'Name',
            'description'        => 'Description',
            'send_type'          => 'Send Type',
            'send_to'            => 'Send To',
            'email_from_address' => 'Email From Address',
            'email_from_name'    => 'Email From Name',
            'email_subject'      => 'Email Subject',
            'email_html'         => 'Email Html',
            'email_text'         => 'Email Text',
            'sms_from'           => 'Sms From',
            'sms_text'           => 'Sms Text',
            'filter'             => 'Filter',
            'status'             => 'Status',
            'created_at'         => 'Created At',
            'start_at'           => 'Start At',
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param $params
     *
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search($params)
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $query = self::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id'                 => $this->id,
            'name'               => $this->name,
            'description'        => $this->description,
            'send_type'          => $this->send_type,
            'email_from_address' => $this->email_from_address,
            'email_from_name'    => $this->email_from_name,
            'email_subject'      => $this->email_subject,
            'email_html'         => $this->email_html,
            'email_text'         => $this->email_text,
            'sms_from'           => $this->sms_from,
            'sms_text'           => $this->sms_text,
            'filter'             => $this->filter,
            'status'             => $this->status,
            'created_at'         => $this->created_at,
            'start_at'           => $this->start_at,
        ]);

        return $dataProvider;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCampaignEmails()
    {
        return $this->hasMany(CampaignEmail::className(), ['campaign_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCampaignSms()
    {
        return $this->hasMany(CampaignSms::className(), ['campaign_id' => 'id']);
    }
}
