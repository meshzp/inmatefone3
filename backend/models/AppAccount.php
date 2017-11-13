<?php

namespace backend\models;

use backend\helpers\Globals;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\behaviors\TimestampBehavior;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\DetailView;
use backend\helpers\Util;

/**
 * This is the model class for table "linphone".
 *
 * The followings are the available columns in table 'linphone':
 * @property string $id
 * @property string $phone_number
 * @property string $username
 * @property string $password
 * @property integer $verify_code
 * @property integer $status
 * @property string $created_at
 * @property string $updated_at
 *
 * @property AppAccountDlr[] $dlrs
 * @property Hlr[] $hlr
 * @property AppAccountRegistration[] $registrations
 * @property AppAccountSettings[] $settings
 */
class AppAccount extends ActiveRecord
{
    /**
     * Statuses accounts
     */
    const STATUS_INACTIVE   = 0;
    const STATUS_ACTIVE     = 1;
    const STATUS_UNVERIFIED = 2;

    /**
     * @var array
     */
    public $userIds = [];

    /**
     * @var array
     */
    public static $statuses = [
        self::STATUS_INACTIVE   => 'Inactive',
        self::STATUS_ACTIVE     => 'Active',
        self::STATUS_UNVERIFIED => 'Unverified',
    ];

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%app_account}}';
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
                'updatedAtAttribute' => 'updated_at',
                'value'              => date('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function afterSave()
    {
        parent::afterSave();

        // check for a default settings row in db and create one if it doesn't exist
        $sql      = 'SELECT id FROM app_account_settings WHERE user_id IS NULL AND app_account_id = :id';
        $settings = Yii::$app->db->createCommand($sql)->bindValue(':id', $this->id)->queryAll();
        if (empty($settings)) {
            $settings                 = new AppAccountSettings();
            $settings->app_account_id = $this->id;
            $settings->status         = 1;
            $settings->save();
        }

        $this->updateDids();
    }

    /**
     * @param null|int $appAccountRegistrationId
     *
     * @return bool
     */
    public function sendPin($appAccountRegistrationId = null)
    {
        $from    = '+13072228888';
        $message = 'Your Inmatefone activation PIN is ' . $this->verify_code . '. Please enter this code into the app to continue registration.';

        $dlrMask = 7;
        $dlrUrl  = null;
        if (!$this->isNewRecord) {
            $dlrMask = 31; // previously set to 7. 1 allows sending reports of successfully delivered messages to the callback url
            $dlrUrl  = 'https://portal.clearvoipinc.com/dlr/app?id=' . $this->id . '&key=hustdf67478&sent_at=%t&type=%d&smsc_reply=%A&provider_id={{providerId}}&registration_id=' . $appAccountRegistrationId;
        }

        return Util::sendSms($from, $this->phone_number, $message, $dlrMask, $dlrUrl);
    }

    public function updateDids()
    {
        if (!empty($this->phone_number)) {
            // run Block DID on any associated numbers to update the voip allowance
            $clientIds      = (new Query())
                ->select('DISTINCT(user_id)')
                ->from('user_dids')
                ->where('`status` > 0 AND redirect_e164 = :number', [':number' => $this->phone_number]);
            $clientDidModel = ClientDid::model();
            foreach ($clientIds as $clientId) {
                $clientDidModel->blockDid($clientId);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['phone_number', 'verify_code'], 'required'],
            [['verify_code', 'status'], 'integer'],
            [['phone_number'], 'max' => 32],
            [['username', 'password'], 'max' => 255],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['id', 'phone_number', 'username', 'password', 'verify_code', 'status', 'created_at', 'updated_at'], 'safe', 'on' => ['search']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'           => 'ID',
            'phone_number' => 'Phone Number',
            'username'     => 'Username',
            'password'     => 'Password',
            'verify_code'  => 'Verify Code',
            'status'       => 'Status',
            'created_at'   => 'Created At',
            'updated_at'   => 'Updated At',
        ];
    }

    /**
     * @deprecated
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = self::find();
        $query->with('hlr', 'registrations');
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => [
                    'created_at' => SORT_DESC,
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id'           => $this->id,
            'phone_number' => $this->phone_number,
            'username'     => $this->username,
            'password'     => $this->password,
            'verify_code'  => $this->verify_code,
            'status'       => $this->status,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ]);

        return $dataProvider;
    }

    /**
     * Render the status value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridDlr']
     *
     * @param $data
     *
     * @return string
     */
    public function gridDlr($data)
    {
        $session = Yii::$app->session;

        if (empty($session['sms_providers'])) {
            // TODO: Класс SmsProvider из \protected\modules\sms\models\SmsProvider.php
            $session['sms_providers'] = ArrayHelper::map(SmsProvider::findAll(), 'id', 'name');
        }

        $rows = [];
        if (is_array($data->dlrs)) {
            foreach ($data->dlrs as $dlr) {
                $type         = isset(AppAccountDlr::$types[$dlr->type]) ? '(' . AppAccountDlr::$types[$dlr->type] . ')' : '';
                $errorClass   = ArrayHelper::isIn($dlr->type, [2, 16]) ? 'text-error' : 'text-success';
                $providerName = (isset($session['sms_providers'][$dlr->sms_provider_id]) ? $session['sms_providers'][$dlr->sms_provider_id] : null);
                $rows[]       = '<div class="' . $errorClass . '">' . $dlr->sent_at . ': ' . $providerName . ' ' . $type . ' ' . $dlr->smsc_reply . '</div>';
            }
        }

        return implode('<br />', $rows);
    }

    /**
     * @deprecated
     * Render the status value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridHlr']
     *
     * @param $data
     *
     * @return string
     */
    public function gridHlr($data)
    {
        $previousResults = [];
        if (is_array($data->hlr) && count($data->hlr)) {
            foreach ($data->hlr as $hlr) {
                // only show hlr responses
                if (empty($hlr->response_at)) {
                    continue;
                }

                // TODO: Результат работы виджета сразу отображается, а в Yii1 можно было вернуть HTML код виджета и соответственно присвоить его зачение переменной
                $previousResults[] = DetailView::widget([
                    'model'       => $hlr,
                    'htmlOptions' => [
                        'class' => 'detail-view hlr-detail',
                    ],
                    'attributes'  => [
                        'Status',
                        'NetworkCode',
                        'ErrorCode',
                        'ErrorDescription',
                        'Location',
                        'CountryName',
                        'CountryCode',
                        'Organisation',
                        'NetworkName',
                        'NetworkType',
                        'Ported',
                        'PortedFrom',
                        'PortedFrom2',
                        'sent_at',
                        'response_at',
                    ],
                ]);
            }
        }

        $html = Html::a('New HLR Lookup ->', ['/hlr/index', 'number' => $data->username], ['target' => '_blank', 'class' => 'btn btn-default btn-mini pull-right']);

        if (count($previousResults)) {
            $html .= '<a id="hlr-show-' . $data->id . '" href="javascript:void(0)" onclick="$(\'#hlr-' . $data->id . ', #hlr-hide-' . $data->id . '\').show();$(\'#hlr-show-' . $data->id . '\').hide();">Click to show HLR results for ' . $data->username . ' ...</a>';
            $html .= '<a id="hlr-hide-' . $data->id . '" href="javascript:void(0)" onclick="$(\'#hlr-' . $data->id . ', #hlr-hide-' . $data->id . '\').hide();$(\'#hlr-show-' . $data->id . '\').show();" style="display:none;">Click to hide HLR results for ' . $data->username . ' ...</a>';
            $html .= '<br /><span id="hlr-' . $data->id . '" style="display:none;">' . implode('<br />', $previousResults) . '</span>';
        }

        return $html;
    }

    /**
     * Render the status value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridRegistrations']
     *
     * @param $data
     *
     * @return null
     */
    public function gridRegistrations($data)
    {
        if (is_array($data->registrations) && count($data->registrations)) {
            $dataProvider = new ArrayDataProvider([
                'allModels'  => $data->registrations,
                'pagination' => false,
                'sort'       => [
                    'defaultOrder' => [
                        'created_at' => SORT_ASC,
                    ],
                ],
            ]);

            // TODO: Результат работы виджета сразу отображается, а потому вернуть его нельзя как в Yii1 (если третим параметром передать true)
            return GridView::widget([
                'id'           => 'app-registration-grid-' . $data->id,
                'dataProvider' => $dataProvider,
                'template'     => '{items}',
                'columns'      => [
                    [
                        'name'   => 'created_at',
                        'header' => 'Registration At',
                    ],
                    [
                        'name'   => 'operating_system',
                        'header' => 'OS',
                    ],
                    [
                        'name'   => 'pin_status',
                        'header' => 'PIN?',
                        'type'   => 'raw',
                        'value'  => function ($data) {
                            if (isset(AppAccountRegistration::$pinStatuses[$data['pin_status']])) {
                                return '<span class="text-'
                                    . ($data['pin_status'] == 1
                                        ? 'success'
                                        : ($data['pin_status'] == 2
                                            ? 'error'
                                            : 'warning')) . '">'
                                    . AppAccountRegistration::$pinStatuses[$data['pin_status']] . ($data['existing_account']
                                        ? ' (Account already exists)'
                                        : '') . '</span>';
                            } else {
                                return '** unknown **';
                            }
                        },
                    ],
                ],
            ]);
        }

        return null;
    }

    /**
     * Render the app status value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridTextSetting']
     *
     * @param AppAccount $data
     *
     * @return string
     */
    public function gridTextSetting($data)
    {
        if ($data->status != 1) {
            return '';
        }
        // display editable voice_status
        $html = $this->renderEditableAppStatus($data->getDefaultSettings(), 'text_status');

        return $html;
    }

    /**
     * Render the app status value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridVoiceSetting')
     *
     * @param AppAccount $data
     *
     * @return string
     */
    public function gridVoiceSetting($data)
    {
        if ($data->status != 1) {
            return '';
        }

        // display editable voice_status
        $html = $this->renderEditableAppStatus($data->getDefaultSettings(), 'voice_status');

        return $html;
    }

    /**
     * @deprecated
     *
     * @param AppAccountSettings $settingsModel
     * @param string $attribute
     *
     * @return string
     */
    public function renderEditableAppStatus($settingsModel, $attribute)
    {
        $gridId = 'app-account-grid'; // better way of getting grid ID rather than hard coding?

        $options = [
            'model'     => $settingsModel,
            'attribute' => $attribute,
            'parentid'  => $gridId,
            'type'      => 'select',
            'encode'    => false,
            'options'   => [
                'params'    => Globals::csrf(true),
                'emptytext' => 'Not Set',
            ],
            'url'       => Url::to(['/appAccount/editableSettings']),
        ];

        $value = $settingsModel->$attribute;
        if (is_null($value)) {
            $value = -1;
        }

        switch ($attribute) {
            case 'text_status':
                $options['text']   = isset(AppAccountSettings::$textStatuses[$value]) ? AppAccountSettings::$textStatuses[$value] : null;
                $options['source'] = AppAccountSettings::$textStatuses;
                break;
            case 'voice_status':
                $options['text']   = isset(AppAccountSettings::$voiceStatuses[$value]) ? AppAccountSettings::$voiceStatuses[$value] : null;
                $options['source'] = AppAccountSettings::$voiceStatuses;
                break;
        }

        // start buffering
        ob_start();

        /** @var $widget TbEditableField */
        $widget = Yii::app()->controller->createWidget('application.widgets.ETbEditableField', $options);
        // TODO: Не понятно что делать с этим ^ кодом и соответственно с переменной $widget

        //manually make selector non unique to match all cells in column
        $selector                   = get_class($widget->model) . '_' . $widget->attribute;
        $widget->htmlOptions['rel'] = $selector;

        //can't call run() as it registers clientScript
        $widget->renderLink();

        $html = ob_get_clean();

        //manually render client script (one for all cells in column)
        $script = $widget->registerClientScript();

        //use parent() as grid is totally replaced by new content
        // TODO: Не понятно что делать с подключением JS кода в модели
        Yii::app()->getClientScript()->registerScript(__CLASS__ . '#' . $gridId . $selector . '-event', '
            $("#' . $gridId . '").parent().on("ajaxUpdate.yiiGridView", "#' . $gridId . '", function() {' . $script . '});
        ');

        // return the widget output from the buffer
        return $html;
    }

    /**
     * Render the app status value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridUsers')
     *
     * @param AppAccount $data
     *
     * @return string
     */
    public function gridUsers($data)
    {

        if (!count($data->userIds)) {
            return '';
        }

        $userLinks = [];
        foreach ($data->userIds as $userId) {
            $userLinks[] = Html::a('#' . $userId, Url::to(['client/update', ['id' => $userId]]));
        }

        return implode('<br />', $userLinks);
    }

    /**
     * Checks which settings are being used for a particular user id
     *
     * @param int $userId
     *
     * @return AppAccountSettings|null
     */
    public function getActiveUserSettings($userId)
    {
        // first see if we have an active user based override
        foreach ($this->settings as $settings) {
            if ($settings->user_id === $userId && $settings->status) {
                return $settings;
            }
        }

        // otherwise return the default
        return $this->getDefaultSettings();
    }

    /**
     * Are the settings being overridden by a particular user?
     *
     * @param int $userId
     *
     * @return boolean
     */
    public function settingsOverridenByUser($userId)
    {
        $activeUserSettings = $this->getActiveUserSettings($userId);

        return ($activeUserSettings && $activeUserSettings->user_id !== null);
    }

    /**
     * @return AppAccountSettings|null
     */
    public function getDefaultSettings()
    {
        foreach ($this->settings as $settings) {
            if ($settings->user_id === null) {
                return $settings;
            }
        }

        return null;
    }

    /**
     * @param int $userId
     *
     * @return AppAccountSettings|null
     */
    public function getUserSettings($userId)
    {
        foreach ($this->settings as $settings) {
            if ($settings->user_id === $userId) {
                return $settings;
            }
        }

        return null;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDlrs()
    {
        return $this->hasMany(AppAccountDlr::className(), ['app_account_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getHlr()
    {
        return $this->hasMany(Hlr::className(), ['username' => 'number']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRegistrations()
    {
        return $this->hasMany(AppAccountRegistration::className(), ['app_account_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSettings()
    {
        return $this->hasMany(AppAccountSettings::className(), ['app_account_id' => 'id']);
    }
}
