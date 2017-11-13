<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Html;

/**
 * This is the model class for table "cdrs".
 *
 * The followings are the available columns in table 'cdrs':
 * @property string $cdr_id
 * @property string $currency
 * @property integer $user_id
 * @property integer $facility_id
 * @property integer $sip_id
 * @property integer $did_id
 * @property integer $rate_center_id
 * @property integer $termination_id
 * @property string $termination_type
 * @property integer $country_id
 * @property string $destination
 * @property string $destination_dialed
 * @property string $cli
 * @property string $cli2
 * @property string $start_datetime
 * @property string $answered_datetime
 * @property string $end_datetime
 * @property integer $billsec
 * @property integer $duration
 * @property string $duration_reports
 * @property string $termination_rate
 * @property integer $billing
 * @property string $billed
 * @property string $minute_charge
 * @property string $connect_charge
 * @property string $disposition
 * @property string $uuid
 * @property string $token
 * @property integer $synced_billed
 * @property string $service_name
 *
 * @property string $orig_cli
 * @property string $account_code
 *
 * @property Client $client
 * @property ClientSip $clientSip
 * @property Termination $termination
 * @property Did $did
 * @property ClientCsr $clientCsr
 */
class Cdr extends ActiveRecord // TODO: Inherited from \protected\components\ActiveRecord.php
{
    public $didFilter;
    public $date;
    public $name;   // contact name from clientDid

    // the following currently used on consumer site cdr view for filtering by date
    public $fromDatetime;
    public $toDatetime;

    public $daysAudioKept = 10; // amount of days audio is being kept for

    private $_audioCheckFailed = false;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%cdrs}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['destination', 'destination_dialed', 'cli', 'cli2', 'duration_reports', 'disposition', 'uuid', 'token'], 'required'],
            [['user_id', 'facility_id', 'sip_id', 'did_id', 'rate_center_id', 'termination_id', 'country_id', 'billsec', 'duration', 'billing', 'synced_billed'], 'integer'],
            [['currency'], 'max' => 3],
            [['termination_type'], 'max' => 40],
            [['destination', 'destination_dialed', 'cli', 'cli2', 'duration_reports', 'disposition', 'uuid', 'token'], 'max' => 255],
            [['termination_rate', 'billed', 'minute_charge', 'connect_charge'], 'max' => 10],
            [['start_datetime', 'answered_datetime', 'end_datetime', 'datetime'], 'safe'],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['cdr_id', 'currency', 'user_id', 'facility_id', 'sip_id', 'didFilter', 'did_id', 'rate_center_id', 'termination_id', 'termination_type', 'country_id', 'destination', 'destination_dialed', 'cli', 'cli2', 'start_datetime', 'answered_datetime', 'end_datetime', 'billsec', 'duration', 'duration_reports', 'termination_rate', 'billing', 'billed', 'minute_charge', 'connect_charge', 'disposition', 'uuid', 'dtmf', 'fromDatetime', 'toDatetime', 'vm_detected', 'name', 'orig_cli', 'account_code'], 'safe', 'on' => ['search']],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'cdr_id'             => 'ID',
            'currency'           => 'Currency',
            'user_id'            => 'Client',
            'facility_id'        => 'Facility',
            'sip_id'             => 'SIP',
            'did_id'             => 'DID',
            'rate_center_id'     => 'Rate Center',
            'termination_id'     => 'Termination',
            'termination_type'   => 'Termination Type',
            'country_id'         => 'Country',
            'destination'        => 'Destination',
            'destination_dialed' => 'Destination Dialed',
            'cli'                => 'CLI',
            'cli2'               => 'CLI',
            'start_datetime'     => 'Start Datetime',
            'answered_datetime'  => 'Answered',
            'end_datetime'       => 'End Datetime',
            'billsec'            => 'Billsec',
            'duration'           => 'Duration',
            'duration_reports'   => 'Duration Reports',
            'termination_rate'   => 'Termination Rate',
            'billing'            => 'Billing',
            'billed'             => 'Billed',
            'minute_charge'      => 'Minute Charge',
            'connect_charge'     => 'Connect Charge',
            'disposition'        => 'Disposition',
            'uuid'               => 'Uuid',
            'token'              => 'Token',
            'synced_billed'      => 'Synced Billed',
            'orig_cli'           => 'Orig, CLI',
            'account_code'       => 'Account Code',
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

        $criteria->select = 't.cdr_id,t.currency,t.user_id,t.sip_id,t.did_id,t.rate_center_id,t.token,t.termination_id,t.termination_type, 
                            t.destination,t.destination_dialed,t.cli,t.cli2,t.start_datetime,t.answered_datetime,t.end_datetime,t.billsec, 
                            t.duration,t.duration_reports,t.termination_rate,t.billing,t.billed,t.minute_charge,t.connect_charge,t.disposition,
                            t.service_name,t.dtmf,t.audio,t.ip_v4,t.vm_detected,t.orig_cli,clientDid.name AS name,t.account_code';

        $criteria->with = [
            'client'      => [
                'alias'  => 'client',
                'select' => 'client.user_first_name,client.user_last_name',
            ],
            'clientSip'   => [
                'alias'  => 'clientSip',
                'select' => 'clientSip.sip_name, clientSip.sip_user_id',
            ],
            'did'         => [
                'alias'  => 'did',
                'select' => 'did.did, did.country_id ',
                'with'   => [
                    'country' => [
                        'alias'  => 'didCountry',
                        'select' => 'didCountry.country_code_alpha_3, didCountry.country_name, didCountry.country_phone_code',
                    ],
                ],
            ],
            'termination' => [
                'alias'  => 'termination',
                'select' => 'termination.termination_country, termination.termination_type, termination.country_id',
                'with'   => [
                    'country' => [
                        'alias'  => 'terminationCountry',
                        'select' => 'terminationCountry.country_code_alpha_3, terminationCountry.country_name, terminationCountry.country_phone_code',
                    ],
                ],
            ],
        ];

        // add client csr in if search might include user_id # 1
        if (empty($this->user_id) || $this->user_id == 1) {
            $criteria->with['clientCsr'] = [
                'alias'  => 'clientCsr',
                'select' => 'clientCsr.user_id, clientCsr.is_redirect',
            ];
        }

        // this isn't ideal as it only gets current point in time info rather than when the call was made
        // should really put an extra field in cdrs containing the user_did_id
        $criteria->join = 'LEFT OUTER JOIN user_dids clientDid ON t.user_id=clientDid.user_id AND t.did_id = clientDid.did_id AND clientDid.status > 0';

        $criteria->compare('t.cdr_id', $this->cdr_id);
        $criteria->limit = 5000; // just in case someone sets to all, would this work?

        if (!empty($this->user_id)) {
            if (is_numeric(trim($this->user_id))) {
                $criteria->compare('t.user_id', $this->user_id);
            } else {
                $criteria->addcondition("(t.user_id = $this->user_id OR client.user_full_name LIKE '%" . $this->user_id . "%' OR client.user_inmate_full_name LIKE '%" . $this->user_id . "%')");
            }
        }

        if (!empty($this->start_datetime)) {
            $dateRange = explode(' - ', $this->start_datetime);
            if (count($dateRange) == 1 || $dateRange[0] == @$dateRange[1]) {
                $datetime = date('Y-m-d', strtotime($dateRange[0]));
                $dateSql  = "(DATE(t.start_datetime) = '$datetime' OR DATE(t.answered_datetime) = '$datetime')";
            } elseif (count($dateRange) == 2) {
                $datetimeFrom = date('Y-m-d', strtotime(trim($dateRange[0])));
                $datetimeTo   = date('Y-m-d', strtotime(trim($dateRange[1])));
                $dateSql      = "((DATE(t.start_datetime) BETWEEN '$datetimeFrom' AND '$datetimeTo') OR (DATE(t.answered_datetime) BETWEEN '$datetimeFrom' AND '$datetimeTo'))";
            } else {
                $dateSql = "(DATE_FORMAT(t.start_datetime,'%Y-%m') = '" . date('Y-m') . "' OR DATE_FORMAT(t.answered_datetime,'%Y-%m') = '" . date('Y-m') . "')";
            }
            $criteria->addcondition($dateSql);
        }

        $criteria->compare('t.currency', $this->currency, true);
        $criteria->compare('t.facility_id', $this->facility_id);
        $criteria->compare('t.sip_id', $this->sip_id);
        if (!empty($this->didFilter)) {
            $did = q('%' . $this->didFilter . '%');
            $criteria->addCondition("did.did LIKE $did OR t.service_name LIKE $did");
        }

        $criteria->compare('t.rate_center_id', $this->rate_center_id);
        $criteria->compare('t.termination_id', $this->termination_id);
        $criteria->compare('t.termination_type', $this->termination_type, true);
        $criteria->compare('t.country_id', $this->country_id);
        $criteria->compare('t.destination', $this->destination, true);
        $criteria->compare('t.destination_dialed', $this->destination_dialed, true);
        $criteria->compare('t.cli', $this->cli, true);
        $criteria->compare('t.cli2', $this->cli2, true);
        $criteria->compare('t.answered_datetime', $this->answered_datetime, true);
        $criteria->compare('t.billsec', $this->billsec);
        $criteria->compare('t.duration', $this->duration);
        $criteria->compare('t.duration_reports', $this->duration_reports, true);
        $criteria->compare('t.termination_rate', $this->termination_rate, true);
        $criteria->compare('t.billing', $this->billing);
        $criteria->compare('t.billed', $this->billed);
        $criteria->compare('t.minute_charge', $this->minute_charge);
        $criteria->compare('t.connect_charge', $this->connect_charge);
        $criteria->compare('t.disposition', $this->disposition, true);
        $criteria->compare('t.uuid', $this->uuid, true);
        $criteria->compare('t.token', $this->token, true);
        $criteria->compare('t.synced_billed', $this->synced_billed);
        $criteria->compare('t.dtmf', $this->dtmf, true);
        $criteria->compare('t.account_code', $this->account_code, true);

        $escape = strstr($this->orig_cli, '*') || strstr($this->orig_cli, '?') ? false : true;
        $criteria->compare('t.orig_cli', $this->orig_cli, true, 'AND', $escape);

        $criteria->compare('clientDid.name', $this->name, true);

        if ($this->vm_detected == 'TRUE') {
            $criteria->compare('t.vm_detected', $this->vm_detected);
            $criteria->compare('t.dtmf', 'none');
        }
        $criteria->addCondition('t.service_name != \'service.800.nobill\' OR t.service_name IS NULL');

        $sort               = new CSort();
        $sort->attributes   = [
            'country_id'   => [
                'asc'  => 'country.country_name ASC',
                'desc' => 'country.country_name DESC',
            ],
            'provider_id'  => [
                'asc'  => 'provider.provider_name ASC',
                'desc' => 'provider.provider_name DESC',
            ],
            'rate_center'  => [
                'asc'  => 'rateCenter.rate_center ASC',
                'desc' => 'rateCenter.rate_center DESC',
            ],
            'user_id'      => [
                'asc'  => 'client.user_full_name ASC',
                'desc' => 'client.user_full_name DESC',
            ],
            'clientStatus' => [
                'asc'  => 'client.user_status ASC',
                'desc' => 'client.user_status DESC',
            ],
            'name'         => [
                'asc'  => 'clientDid.name ASC',
                'desc' => 'clientDid.name DESC',
            ],
            '*',
        ];
        $sort->defaultOrder = 't.start_datetime DESC, t.answered_datetime DESC, t.cdr_id DESC';

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
            'sort'     => $sort,
        ]);
    }

    /**
     * @deprecated
     * Gets the background row color for cdr grid
     * Based on current model
     *
     * @param int $row The row number
     *
     * @return string
     */
    public function getRowColor($row)
    {
        if (empty($this->token)) {
            $colorCode = $row & 1 ? '#ffffff' : '#eeeeff';
        } else {
            // TODO: Use not known to the component
            $colorCode = Yii::$app->color->getRowColor($this->token);
        }

        return $colorCode;
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function searchClientView()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.
        $criteria = new CDbCriteria;

        $criteria->select = 't.cdr_id,t.currency,t.user_id,t.sip_id,t.did_id,t.rate_center_id,t.token,t.termination_id,t.termination_type, 
                            t.destination,t.destination_dialed,t.cli,t.cli2,t.start_datetime,t.answered_datetime,t.end_datetime,t.billsec, 
                            t.duration,t.duration_reports,t.termination_rate,t.billing,t.billed,t.disposition,t.service_name,clientDid.name AS name';

        $criteria->with = [
            'client'      => [
                'alias'  => 'client',
                'select' => 'client.user_first_name,client.user_last_name',
            ],
            'clientSip'   => [
                'alias'  => 'clientSip',
                'select' => 'clientSip.sip_name, clientSip.sip_user_id',
            ],
            'did'         => [
                'alias'  => 'did',
                'select' => 'did.did, did.country_id ',
                'with'   => [
                    'country' => [
                        'alias'  => 'didCountry',
                        'select' => 'didCountry.country_code_alpha_3, didCountry.country_name, didCountry.country_phone_code',
                    ],
                ],
            ],
            'termination' => [
                'alias'  => 'termination',
                'select' => 'termination.termination_country, termination.termination_type, termination.country_id',
                'with'   => [
                    'country' => [
                        'alias'  => 'terminationCountry',
                        'select' => 'terminationCountry.country_code_alpha_3, terminationCountry.country_name, terminationCountry.country_phone_code',
                    ],
                ],
            ],
        ];

        $criteria->join = 'LEFT OUTER JOIN user_dids clientDid ON t.user_id=clientDid.user_id AND t.did_id = clientDid.did_id AND clientDid.status > 0';

        $criteria->compare('t.cdr_id', $this->cdr_id);
        $criteria->limit = 5000; // just in case someone sets to all, would this work?

        if (!empty($this->user_id)) {
            if (is_numeric(trim($this->user_id))) {
                $criteria->compare('t.user_id', $this->user_id);
            } else {
                $criteria->addcondition("(t.user_id = $this->user_id OR client.user_full_name LIKE '%" . $this->user_id . "%' OR client.user_inmate_full_name LIKE '%" . $this->user_id . "%')");
            }
        }

        $dateSql      = false;
        $datetimeFrom = trim($this->fromDatetime);
        $datetimeTo   = trim($this->toDatetime);
        if (!empty($datetimeFrom) && !empty($datetimeTo)) {
            $dateSql = "((t.start_datetime BETWEEN :fromDatetime AND :toDatetime) OR (t.answered_datetime BETWEEN :fromDatetime AND :toDatetime))";
        } elseif (!empty($datetimeFrom)) {
            $dateSql = "((t.start_datetime > :fromDatetime) OR (t.answered_datetime > :fromDatetime))";
        } elseif (!empty($datetimeTo)) {
            $dateSql = "((t.start_datetime < :toDatetime) OR (t.answered_datetime < :toDatetime))";
        }
        if ($dateSql) {
            $criteria->addcondition($dateSql);
            if (!empty($datetimeFrom)) {
                $criteria->params[':fromDatetime'] = $datetimeFrom;
            }
            if (!empty($datetimeTo)) {
                $criteria->params[':toDatetime'] = $datetimeTo;
            }
        }

        if (!empty($this->start_datetime)) {
            $dateRange = explode(' - ', $this->start_datetime);
            if (count($dateRange) == 1 || $dateRange[0] == @$dateRange[1]) {
                $datetime = date('Y-m-d', strtotime($dateRange[0]));
                $dateSql  = "(DATE(t.start_datetime) = '$datetime' OR DATE(t.answered_datetime) = '$datetime')";
            } elseif (count($dateRange) == 2) {
                $datetimeFrom = date('Y-m-d', strtotime(trim($dateRange[0])));
                $datetimeTo   = date('Y-m-d', strtotime(trim($dateRange[1])));
                $dateSql      = "((DATE(t.start_datetime) BETWEEN '$datetimeFrom' AND '$datetimeTo') OR (DATE(t.answered_datetime) BETWEEN '$datetimeFrom' AND '$datetimeTo'))";
            } else {
                $dateSql = "(DATE_FORMAT(t.start_datetime,'%Y-%m') = '" . date('Y-m') . "' OR DATE_FORMAT(t.answered_datetime,'%Y-%m') = '" . date('Y-m') . "')";
            }
            $criteria->addcondition($dateSql);
        }

        $criteria->compare('t.currency', $this->currency, true);
        $criteria->compare('t.facility_id', $this->facility_id);
        $criteria->compare('t.sip_id', $this->sip_id);
        $criteria->compare('t.did_id', $this->did_id);
        $criteria->compare('t.rate_center_id', $this->rate_center_id);
        $criteria->compare('t.termination_id', $this->termination_id);
        $criteria->compare('t.termination_type', $this->termination_type, true);
        $criteria->compare('t.country_id', $this->country_id);
        $criteria->compare('t.destination', $this->destination, true);
        $criteria->compare('t.destination_dialed', $this->destination_dialed, true);
        $criteria->compare('t.cli', $this->cli, true);
        $criteria->compare('t.cli2', $this->cli2, true);
        $criteria->compare('t.answered_datetime', $this->answered_datetime, true);
        $criteria->compare('t.billsec', $this->billsec);
        $criteria->compare('t.duration', $this->duration);
        $criteria->compare('t.duration_reports', $this->duration_reports, true);
        $criteria->compare('t.termination_rate', $this->termination_rate, true);
        $criteria->compare('t.billing', $this->billing);
        $criteria->compare('t.billed', $this->billed, true);
        $criteria->compare('t.disposition', $this->disposition, true);
        $criteria->compare('t.uuid', $this->uuid, true);
        $criteria->compare('t.token', $this->token, true);
        $criteria->compare('t.synced_billed', $this->synced_billed);
        $criteria->addCondition("t.service_name != 'service.800.nobill' OR t.service_name IS NULL");

        $sort               = new CSort();
        $sort->attributes   = [
            'country_id'     => [
                'asc'  => 'country.country_name ASC',
                'desc' => 'country.country_name DESC',
            ],
            'provider_id'    => [
                'asc'  => 'provider.provider_name ASC',
                'desc' => 'provider.provider_name DESC',
            ],
            'rate_center'    => [
                'asc'  => 'rateCenter.rate_center ASC',
                'desc' => 'rateCenter.rate_center DESC',
            ],
            'user_id'        => [
                'asc'  => 'client.user_full_name ASC',
                'desc' => 'client.user_full_name DESC',
            ],
            'clientStatus'   => [
                'asc'  => 'client.user_status ASC',
                'desc' => 'client.user_status DESC',
            ],
            'name'           => [
                'asc'  => 'clientDid.name ASC',
                'desc' => 'clientDid.name DESC',
            ],
            'start_datetime' => [
                'asc'  => 't.start_datetime ASC,t.answered_datetime ASC',
                'desc' => 't.start_datetime DESC,t.answered_datetime DESC',
            ],
            '*',
        ];
        $sort->defaultOrder = 't.cdr_id DESC';

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
            'sort'     => $sort,
        ]);
    }

    /**
     * Render the client name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridClientName']
     *
     * @param object $data
     *
     * @return null|string
     */
    public function gridClientName($data)
    {
        if (!empty($data->client)) {
            $clientId   = Html::encode($data->client->user_id);
            $clientName = Html::encode($data->client->user_first_name . ' ' . $data->client->user_last_name);
            $clientInfo = $clientName . '(#' . $clientId . ')';

            return Html::a($clientInfo, ['client/update', 'id' => $data->client->user_id]);
        }

        return null;
    }

    /**
     * Render the contact name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridContactName']
     *
     * @param object $data
     *
     * @return null|string
     */
    public function gridContactName($data)
    {
        if (!empty($data->name)) {
            return Html::encode($data->name);
        }

        return null;
    }

    /**
     * Render the country name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridDid']
     *
     * @param object $data
     *
     * @return null|string
     */
    public function gridDid($data)
    {
        if (!empty($data->did)) {
            $flag = empty($data->did->country) ? '' : Html::img(Yii::$app->request->baseUrl . '/img/country_flags/' .
                    $data->did->country->country_code_alpha_3 . '.png', ['alt' => $data->did->country->country_name]) .
                '&nbsp;+' . $data->did->country->country_phone_code . '&nbsp;';

            return $flag . $data->did->did;
        } elseif (!empty($data->service_name)) {
            return $data->service_name;
        }

        return null;
    }

    /**
     * Render the country name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridTermination']
     *
     * @param object $data
     *
     * @return null|string
     */
    public function gridTermination($data)
    {
        if (!empty($data->termination)) {
            $flag = empty($data->termination->country) ? '' :
                Html::img(Yii::$app->request->baseUrl . '/img/country_flags/' . $data->termination->country->country_code_alpha_3 . '.png', ['alt' => $data->termination->country->country_name]) . '&nbsp;';

            return $flag . $data->termination->termination_country . '(' . $data->termination->termination_type . ')';
        }

        return null;
    }

    /**
     * Render the balance value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridBilled']
     * This is so that the currency symbol doesn't get called multiple times
     *
     * @param object $data
     *
     * @return string
     */
    public function gridBilled($data)
    {
        return Html::encode($this->getCurrencySymbol($data->currency) . ' ' . $data->billed . ' ' . $data->currency);
    }

    /**
     * Render the balance value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridBilled']
     * This is so that the currency symbol doesn't get called multiple times
     *
     * @param object $data
     *
     * @return string
     */
    public function gridMinuteCharge($data)
    {
        if ($data->minute_charge === null) {
            return '?';
        }

        return Html::encode($this->getCurrencySymbol($data->currency) . ' ' . $data->minute_charge . ' ' . $data->currency);
    }

    /**
     * Render the balance value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridBilled']
     * This is so that the currency symbol doesn't get called multiple times
     *
     * @param object $data
     *
     * @return string
     */
    public function gridConnectCharge($data)
    {
        if ($data->connect_charge === null) {
            return '?';
        }

        return Html::encode($this->getCurrencySymbol($data->currency) . ' ' . $data->connect_charge . ' ' . $data->currency);
    }

    /**
     * @deprecated
     * Render the balance value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridBilled']
     * This is so that the currency symbol doesn't get called multiple times
     *
     * @param object $data
     *
     * @return string
     */
    public function gridAudio($data)
    {
        if (empty($data->audio) || strtotime($data->start_datetime) < strtotime('-' . $this->daysAudioKept . ' days')) {
            return '';
        }

        $host      = empty($data->ip_v4) ? '206.190.134.84' : $data->ip_v4;
        $cs        = '';
        $csDisplay = '';
        if ($data->user_id == 1 && !in_array($data->cli, ['Unknown', '13072228888', '8453265300', ''])) {
            $cs         = '&cs=yes';
            $csDisplay  = '<br /><small class="text-error">CSR Call<br />';
            $csClients  = [];
            $maxPerLine = 4;
            $current    = 0;
            $maxTotal   = 4;
            foreach ($data->clientCsr as $clientCsr) {
                if (empty($clientCsr->is_redirect)) {
                    $link = Html::a($clientCsr->user_id, ['client/update', 'id' => $clientCsr->user_id]);
                    if ($current === $maxPerLine) {
                        $link    = '<br />' . $link;
                        $current = 1;
                    } else {
                        $current++;
                    }
                    $csClients[] = $link;
                    if ($current === $maxTotal) {
                        $csClients[] = '...';
                        break;
                    }
                }
            }
            $csDisplay .= implode(',', $csClients) . '</small>';
        }
        $url = "http://$host/audio.php?file=" . $data->audio . $cs;

        return Html::a('<i class="icon-play-circle"></i>', $url, ['target' => '_blank', 'class' => 'playaudio']) . $csDisplay;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getClient()
    {
        return $this->hasOne(Client::className(), ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getClientSip()
    {
        return $this->hasOne(ClientSip::className(), ['sip_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTermination()
    {
        return $this->hasOne(Termination::className(), ['termination_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDid()
    {
        return $this->hasOne(Did::className(), ['did_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getClientCsr()
    {
        return $this->hasMany(ClientCsr::className(), ['cdr_id' => 'id']);
    }
}
