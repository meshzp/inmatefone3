<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use backend\helpers\Globals;
use yii\db\Query;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * This is the model class for table "user_dids".
 *
 * The followings are the available columns in table 'user_dids':
 * @property string $id
 * @property integer $user_id
 * @property integer $did_id
 * @property integer $redirect_country_id
 * @property string $redirect
 * @property string $redirect_e164
 * @property string $redirect_override
 * @property string $redirect_function
 * @property integer $app_status
 * @property string $cli
 * @property string $cli_name
 * @property string $name
 * @property integer $termination_id
 * @property integer $termination_provider_id
 * @property integer $allowance
 * @property integer $plan_id
 * @property string $datetime_add
 * @property string $datetime_cancel
 * @property string $datetime_last_update
 * @property integer $status
 * @property integer $admin_id
 * @property integer $admin_id_last_update
 * @property integer $asterisk
 *
 * @property AppAccount $appAccount
 * @property Did $did
 */
class ClientDid extends ActiveRecord // Inherited from \protected\components\ActiveRecord
{
    public $rateCenterId;
    public $didCountryId;
    public $numberLoader;
    public $numberArray;
    public $facilityId;
    // filters
    public $providerId;
    public $npa;
    public $nxx;

    public $defective = 0;  // used when removing a client did and will set did_available flag to defective if necessary

    public $defaultCountry = 'US';  // used for bulk loading redirect numbers
    public $addEmptyAmount = 0;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%user_dids}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['rateCenterId', 'redirect_country_id', 'defaultCountry'], 'required', 'on' => ['insert']],
            [['user_id', 'did_id', 'redirect_country_id', 'termination_id', 'termination_provider_id', 'allowance', 'plan_id', 'status', 'admin_id', 'admin_id_last_update', 'asterisk', 'rateCenterId', 'app_status', 'addEmptyAmount'], 'integer'],
            [['redirect', 'cli', 'cli_name', 'name'], 'max' => 255],
            [['redirect_e164'], 'max' => 32],
            [['redirect_e164'], 'match', 'pattern' => '/^\+\d{7,31}$/'],
            [['redirect_override', 'redirect_function'], 'max' => 100],
            [['datetime_add', 'datetime_cancel', 'datetime_last_update', 'defective', 'defaultCountry'], 'safe'],
            [['name', 'redirect_country_id', 'redirect', 'status', 'cli', 'cli_name', 'redirect_e164', 'redirect_override', 'redirect_function', 'app_status'], 'safe', 'on' => ['editable']],
            [['name', 'redirect_country_id', 'redirect', 'termination_id', 'redirect_e164'], 'safe', 'on' => ['clientUpdate']],
            [['numberLoader'], 'filters', 'filters' => 'checkNumbers', 'on' => ['insert']],
            [['providerId', 'npa', 'nxx'], 'safe', 'on' => ['insert']],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['id', 'user_id', 'did_id', 'redirect_country_id', 'redirect', 'redirect_e164', 'redirect_override', 'redirect_function', 'app_status', 'cli', 'cli_name', 'name', 'termination_id', 'termination_provider_id', 'allowance', 'plan_id', 'datetime_add', 'datetime_cancel', 'datetime_last_update', 'status', 'admin_id', 'admin_id_last_update', 'asterisk'], 'safe', 'on' => ['search']],
            [['id', 'user_id', 'did_id', 'datetime_add', 'datetime_cancel', 'datetime_last_update', 'status', 'admin_id', 'admin_id_last_update'], 'safe', 'on' => ['searchHistory']],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'                      => 'ID',
            'user_id'                 => 'User',
            'did_id'                  => 'Did',
            'redirect_country_id'     => 'Country',
            'redirect'                => 'Redirect Number',
            'redirect_e164'           => 'Redirect E164',
            'redirect_override'       => 'Redirect Override',
            'redirect_function'       => 'Redirect Function',
            'app_status'              => 'VoIP App Usage',
            'cli'                     => 'Caller ID',
            'cli_name'                => 'Caller Name',
            'name'                    => 'Contact Name',
            'termination_id'          => 'Termination',
            'termination_provider_id' => 'Terminating Carrier',
            'allowance'               => 'Minutes Left',
            'plan_id'                 => 'Plan',
            'datetime_add'            => 'Date Added',
            'datetime_cancel'         => 'Date Cancelled',
            'datetime_last_update'    => 'Last Updated',
            'status'                  => 'Status',
            'admin_id'                => 'Admin',
            'admin_id_last_update'    => 'Admin Id Last Update',
            'asterisk'                => 'Asterisk',
            'rateCenterId'            => 'Rate Center',
            'providerId'              => 'Provider',
            'npa'                     => 'NPA',
            'nxx'                     => 'NXX',
            'defaultCountry'          => 'Default Country (for redirect validation)',
            'numberLoader'            => 'Bulk Redirect Number Loader (1 per line)',
            'addEmptyAmount'          => 'Amount of DIDs to add with no redirect (max 10)',
        ];
    }



    /**
     * TODO: Need to write this method in Yii2
     * @deprecated
     * @return array
     */
    public function scopes()
    {
        return [
            'enabled'  => [
                'condition' => 't.status!=0',
            ],
            'byClient' => [
                'with'  => 'client',
                'group' => 'client.user_id',
            ],
            'recover'  => [
                'with'      => 'did',
                'condition' => 't.status=0 AND did.did_in_use = 0 AND did.did_available = 1',
            ],
        ];
    }

    /**
     * @deprecated
     * TODO: Need to write this method in Yii2
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param bool $recover
     *
     * @return ActiveRecord the data provider that can return the models based on the search/filter conditions.
     */
    public function search($recover = false)
    {
        $criteria = new CDbCriteria;

        $criteria->with   = [
            'did'     => [
                'with' => [
                    'country',
                    'rateCenter',
                    'provider',
                ],
            ],
            'country' => [
                'alias' => 'redirect_country',
            ],
            'appAccount',
        ];
        $criteria->scopes = $recover ? 'recover' : 'enabled';

        $criteria->compare('t.id', $this->id, true);
        $criteria->compare('t.user_id', $this->user_id);
        $criteria->compare('t.did_id', $this->did_id);
        $criteria->compare('t.redirect_country_id', $this->redirect_country_id);
        $criteria->compare('t.redirect', $this->redirect, true);
        $criteria->compare('t.cli', $this->cli, true);
        $criteria->compare('t.cli_name', $this->cli_name, true);
        $criteria->compare('t.name', $this->name, true);
        $criteria->compare('t.termination_id', $this->termination_id);
        $criteria->compare('t.termination_provider_id', $this->termination_provider_id);
        $criteria->compare('t.allowance', $this->allowance);
        $criteria->compare('t.plan_id', $this->plan_id);
        $criteria->compare('t.datetime_add', $this->datetime_add, true);
        $criteria->compare('t.datetime_cancel', $this->datetime_cancel, true);
        $criteria->compare('t.datetime_last_update', $this->datetime_last_update, true);
        $criteria->compare('t.admin_id', $this->admin_id);
        $criteria->compare('t.admin_id_last_update', $this->admin_id_last_update);
        $criteria->compare('t.asterisk', $this->asterisk);

        return new CActiveDataProvider($this, [
            'criteria'   => $criteria,
            'sort'       => [
                'defaultOrder' => 't.datetime_add DESC',
            ],
            'pagination' => false,
        ]);
    }

    /**
     * @deprecated
     * TODO: Need to write this method in Yii2
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function searchClientView()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;

        $criteria->with   = [
            'did'     => [
                'with' => [
                    'country',
                ],
            ],
            'country' => [
                'alias' => 'redirect_country',
            ]];
        $criteria->scopes = 'enabled';

        $criteria->compare('t.id', $this->id, true);
        $criteria->compare('t.user_id', $this->user_id);
        $criteria->compare('t.did_id', $this->did_id);
        $criteria->compare('t.redirect_country_id', $this->redirect_country_id);
        $criteria->compare('t.redirect', $this->redirect, true);
        $criteria->compare('t.cli', $this->cli, true);
        $criteria->compare('t.cli_name', $this->cli_name, true);
        $criteria->compare('t.name', $this->name, true);
        $criteria->compare('t.termination_id', $this->termination_id);
        $criteria->compare('t.termination_provider_id', $this->termination_provider_id);
        $criteria->compare('t.allowance', $this->allowance);
        $criteria->compare('t.plan_id', $this->plan_id);
        $criteria->compare('t.datetime_add', $this->datetime_add, true);
        $criteria->compare('t.datetime_cancel', $this->datetime_cancel, true);
        $criteria->compare('t.datetime_last_update', $this->datetime_last_update, true);
        $criteria->compare('t.admin_id', $this->admin_id);
        $criteria->compare('t.admin_id_last_update', $this->admin_id_last_update);

        return new CActiveDataProvider($this, [
            'criteria'   => $criteria,
            'pagination' => false,
            'sort'       => [
                'defaultOrder' => 't.datetime_add DESC',
            ],
        ]);
    }

    /**
     * @deprecated
     * TODO: Need to write this method in Yii2
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function searchHistory()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;

        $criteria->with = ['client'];

        $criteria->compare('t.user_id', $this->user_id);
        $criteria->compare('t.did_id', $this->did_id);
        $criteria->compare('t.datetime_add', $this->datetime_add, true);
        $criteria->compare('t.datetime_cancel', $this->datetime_cancel, true);
        $criteria->compare('t.datetime_last_update', $this->datetime_last_update, true);

        $criteria->compare('t.admin_id', $this->admin_id);
        $criteria->compare('t.admin_id_last_update', $this->admin_id_last_update);

        return new CActiveDataProvider($this, [
            'criteria'   => $criteria,
            'sort'       => [
                'defaultOrder' => 't.datetime_add DESC',
            ],
            'pagination' => false,
        ]);
    }

    /**
     * @param bool $includeId
     *
     * @return null|string
     */
    public function getClientFullName($includeId = false)
    {
        if (!empty($this->client)) {
            $clientName = $this->client->user_last_name . ', ' . $this->client->user_first_name;

            return $includeId ? $clientName . ' #' . $this->client->user_id : $clientName;
        }

        return null;
    }

    /**
     * @deprecated
     * @return string
     */
    public function getDidInfo()
    {
        $ret = '<div style="text-align:center;">';
        if (!empty($this->did->provider)) {
            $ret .= Html::encode($this->did->provider->provider_name) . '<br />';
        }
        if (!empty($this->did->country)) {
            $did     = in_array($this->did->country_id, [37, 223]) ? $this->did->did_area_code . '-' . $this->did->did_prefix . '-' . $this->did->did_line : $this->did->did;
            $fullDid = '+' . Html::encode($this->did->country->country_phone_code . ' ' . $did);
            // TODO: This helpers in \protected\helpers\Html.php
            $ret .= Html::dialogLink($fullDid, 'clientDid/showHistory', '', '', ['didId' => $this->did_id]) . '<br />';
        }
        if (!empty($this->did->rateCenter)) {
            $ret .= Html::encode($this->did->rateCenter->rate_center . ', ' . $this->did->rateCenter->rate_center_state);
        }
        $ret .= '</div>';

        return $ret;
    }

    /**
     * @return string
     */
    public function getDidNumber()
    {
        $ret = '<div style="text-align:center;">';
        if (!empty($this->did->country)) {
            $did     = in_array($this->did->country_id, [37, 223]) ? $this->did->did_area_code . '-' . $this->did->did_prefix . '-' . $this->did->did_line : $this->did->did;
            $fullDid = '+' . Html::encode($this->did->country->country_phone_code . ' ' . $did);
            $ret     .= '<strong>' . Html::encode($fullDid) . '</strong><br />';
        }
        $ret .= '</div>';

        return $ret;
    }

    /**
     * @deprecated
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
                $this->status = 0;
                $result       = $this->save();
                $this->afterDelete();

                return $result;
            } else {
                return false;
            }
        } else {
            // TODO: Need to replace the class for exception
            throw new CDbException(Yii::t('yii', 'The active record cannot be deleted because it is new.'));
        }
    }

    /**
     * @deprecated
     * Custom validation for the number loader
     *
     * @param string $attribute
     */
    public function checkNumbers($attribute)
    {
        // convert loaded numbers into an array
        $this->numberArray = [];
        $tempNumbers       = explode('\n', trim($this->$attribute));

        // validate each number and add to array
        foreach ($tempNumbers as $tempNumber) {
            $tempNumber = trim($tempNumber);
            if (empty($tempNumber)) {
                continue;
            }
            $libphone = Hlr::validatePhoneNumber($tempNumber, $this->defaultCountry);
            if (is_array($libphone)) {
                $this->numberArray[] = $libphone['e164'];
            } else {
                $this->addError($attribute, $libphone);
            }
        }

        $numCount = count($this->numberArray) + (int)$this->addEmptyAmount;
        if (!$numCount) {
            $this->addError($attribute, 'Please add some numbers');
        } else {
            $criteria = new CDbCriteria(); // TODO: Need replace CDbCriteria
            $criteria->compare('rate_center_id', $this->rateCenterId);
            $criteria->compare('did_in_use', 0);
            $criteria->compare('did_available', 1);
            if (!empty($this->providerId)) {
                $criteria->compare('provider_id', $this->providerId);
            }
            if (!empty($this->npa)) {
                $criteria->compare('did_area_code', $this->npa);
            }
            if (!empty($this->nxx)) {
                $criteria->compare('did_prefix', $this->nxx);
            }
            $didCount = Did::model()->count($criteria);
            if ($didCount < $numCount) {
                $this->addError($attribute, 'There aren\'t sufficient DIDs in stock for the selected rate center');
            }
        }
    }

    /**
     * @deprecated
     * @param null|array $attributes
     *
     * @return bool
     */
    public function insert($attributes = null)
    {
        // TODO: add some warning messages if things don't work
        if (!$this->getIsNewRecord()) {
            throw new CDbException(Yii::t('yii', 'The active record cannot be inserted to database because it is not new.'));
        }
        if ($this->beforeSave()) {
            Yii::trace(get_class($this) . '.insert()', 'system.db.ar.CActiveRecord');

            $clientDidRows = [];    // array to hold client did rows for multiple insertion - note: insert relies on RDbConnection and RDbCommand components
            $didIds        = [];  // array to hold added did id's so we can update the did table

            // first add any empty DIDs
            $maxDIDs = 10;
            if ((int)$this->addEmptyAmount > 0) {
                for ($i = 1; $i <= $this->addEmptyAmount; $i++) {
                    $didId = Did::model()->getAvailableDidId($this->rateCenterId, $didIds, $this->providerId, $this->npa, $this->nxx);
                    if ($didId) {
                        $didIds[] = $didId;
                        $tempRow  = [
                            'user_id'              => $this->user_id,
                            'did_id'               => $didId,
                            'redirect_country_id'  => 0,
                            'redirect'             => '',
                            'termination_id'       => 0,
                            'datetime_add'         => date('Y-m-d H:i:s'),
                            'datetime_last_update' => date('Y-m-d H:i:s'),
                            'admin_id'             => user()->id,
                            'admin_id_last_update' => user()->id,
                        ];

                        // make sure cli is empty if client is using a service other than InmateFone
                        if (!empty($this->client) && (empty($this->client->user_service) || $this->client->user_service < 0)) {
                            $tempRow['cli']      = '';
                            $tempRow['cli_name'] = '';
                        }

                        $clientDidRows[] = $tempRow;
                    }
                    if ($i >= $maxDIDs) {
                        break;
                    }
                }
            }

            // now add the given redirect numbers
            if ($this->rateCenterId > 0 && $this->user_id > 0 && is_array($this->numberArray)) {
                foreach ($this->numberArray as $number) {
                    $digitsOnly = numbersOnly($number);

                    $terminationId = Termination::model()->getTerminationIdByNumber($digitsOnly);
                    if ($terminationId) {
                        $didId = Did::model()->getAvailableDidId($this->rateCenterId, $didIds, $this->providerId, $this->npa, $this->nxx, $digitsOnly);
                        if ($didId) {
                            $didIds[] = $didId;
                            $tempRow  = [
                                'user_id'              => $this->user_id,
                                'did_id'               => $didId,
                                //                                'redirect_country_id' => $this->redirect_country_id,
                                //                                'redirect' => $number,
                                'redirect_e164'        => $number,
                                'termination_id'       => $terminationId,
                                'datetime_add'         => date('Y-m-d H:i:s'),
                                'datetime_last_update' => date('Y-m-d H:i:s'),
                                'admin_id'             => user()->id,
                                'admin_id_last_update' => user()->id,
                            ];

                            // make sure cli is empty if client is using a service other than InmateFone
                            if (!empty($this->client) && (empty($this->client->user_service) || $this->client->user_service < 0)) {
                                $tempRow['cli']      = '';
                                $tempRow['cli_name'] = '';
                            }

                            // attempt to find the correct country id
                            // @todo we already validated the number - any way to avoid validating it here too? Remember that there's a chance a redrect might be used twice
                            // actually ... prob doesn't matter because this is all (hopefully) temporary anyway until the redirect can be completely removed
                            $libphone = Hlr::validatePhoneNumber($number);
                            if (is_array($libphone)) {
                                $sql     = "SELECT country_id, country_phone_code FROM country_codes WHERE country_code_alpha_2 = :regionCode AND :number LIKE CONCAT(country_phone_code,'%') ORDER BY LENGTH(country_phone_code) DESC";
                                $country = Yii::app()->db->createCommand($sql)->queryRow(true, [':regionCode' => $libphone['region_code'], ':number' => $digitsOnly]);
                                if (!empty($country)) {
                                    $tempRow['redirect']            = substr($digitsOnly, strlen($country['country_phone_code']));
                                    $tempRow['redirect_country_id'] = $country['country_id'];
                                }
                            }

                            $clientDidRows[] = $tempRow;
                        }
                    }
                }
            }

            // insert client did associations
            if (count($clientDidRows) && count($didIds)) {
                $connection  = Yii::app()->db;
                $transaction = $connection->beginTransaction();
                try {
                    $connection->createCommand()->insert($this->tableName(), $clientDidRows);
                    $connection->createCommand()->update(
                        Did::model()->tableName(),
                        ['did_in_use' => 1, 'did_user_id' => $this->user_id],
                        ['in', 'did_id', $didIds]
                    );

                    $transaction->commit();
                } catch (Exception $e) { // an exception is raised if a query fails
                    $transaction->rollback();

                    return false;
                }

                return true;
            }
        }

        // if we get this far, something has probably gone wrong
        return false;
    }

    /**
     * @deprecated
     * This function is called from the consumer website
     *
     * @param int $limit The amount of DIDs to assign
     *
     * @return mixed The amount of dids added (could be 0) or boolean false on error
     */
    public function saveInitialClientDids($limit)
    {
        // TODO: add some warning messages if things don't work
        if (!$this->getIsNewRecord()) {
            // TODO: Нужно заменить класс исключения
            throw new CDbException(Yii::t('yii', 'The active record cannot be inserted to database because it is not new.'));
        }
        if ($this->beforeSave()) {
            Yii::trace(get_class($this) . '.saveInitialClientDids()', 'system.db.ar.CActiveRecord');

            if ($this->user_id > 0 && $this->facilityId > 0) {
                $clientDidRows = [];    // array to hold client did rows for multiple insertion - note: insert relies on RDbConnection and RDbCommand components
                $didIds        = Did::getAvailableDidIdsForFacility($this->facilityId, $limit);
                foreach ($didIds as $didId) {
                    $clientDidRows[] = [
                        'user_id'              => $this->user_id,
                        'did_id'               => $didId,
                        'redirect_country_id'  => 223,
                        'redirect'             => '',
                        'termination_id'       => 0,
                        'datetime_add'         => date('Y-m-d H:i:s'),
                        'datetime_last_update' => date('Y-m-d H:i:s'),
                        'admin_id'             => Globals::user()->id,
                        'admin_id_last_update' => Globals::user()->id,
                        'status'               => Client::STATUS_PENDING,
                    ];
                }
                // insert client did associations
                if (count($clientDidRows) && count($didIds)) {
                    $connection  = Yii::$app->db;
                    $transaction = $connection->beginTransaction();
                    try {
                        $connection->createCommand()->insert($this->tableName(), $clientDidRows);
                        $connection->createCommand()->update(
                            Did::tableName(),
                            ['did_in_use' => 1, 'did_user_id' => $this->user_id],
                            ['in', 'did_id', $didIds]
                        );

                        $transaction->commit();
                    } catch (Exception $e) {
                        $transaction->rollback();

                        return false;
                    }

                    return count($didIds);
                }

                return 0;
            } else {
                // TODO: what should happen if countryId = 0? (i.e. SIP) - note: SIP option removed for the moment
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function beforeDelete()
    {
        // clear the cli and cli name before deleting
        $this->cli      = '';
        $this->cli_name = '';

        return parent::beforeDelete();
    }

    /**
     * @deprecated
     * @return bool
     */
    public function beforeSave()
    {
        // TODO: Метод isAttributeDirty наследуется из родительского класса (указано в строке объяснения класса)
        if ($this->isNewRecord || $this->isAttributeDirty('redirect_e164') || $this->isAttributeDirty('redirect_override') || $this->isAttributeDirty('redirect_function')) {
            if (empty($this->redirect_e164) && empty($this->redirect_override)) {
                $this->redirect            = '';
                $this->redirect_country_id = 0;
            } elseif (!empty($this->redirect_override)) {
                $this->redirect            = $this->redirect_override;
                $this->redirect_country_id = 0;
            } else {
                // find the correct country id and redirect to use
                $libphone = Hlr::validatePhoneNumber($this->redirect_e164);
                if (is_array($libphone)) {
                    // attempt to find the correct country id
                    $number  = ltrim($this->redirect_e164, '+');
                    $sql     = 'SELECT country_id, country_phone_code FROM country_codes WHERE country_code_alpha_2 = :regionCode AND :number LIKE CONCAT(country_phone_code,\'%\') ORDER BY LENGTH(country_phone_code) DESC';
                    $country = Yii::$app->db->createCommand($sql, [':regionCode' => $libphone['region_code'], ':number' => $number])->queryOne();
                    if (!empty($country)) {
                        $this->redirect            = substr($number, strlen($country['country_phone_code']));
                        $this->redirect_country_id = $country['country_id'];
                    }
                }
            }

            // add function on the end if necessary
            if (!empty($this->redirect_function)) {
                $this->redirect .= ' (' . $this->redirect_function . ')';
            }
        }

        if ($this->isNewRecord) {
            $this->datetime_add = date('Y-m-d H:i:s');
            $this->admin_id     = Globals::user()->id;

            // make sure cli is empty if client is using a service other than InmateFone
            if (!isset($this->cli) && !empty($this->client) && (empty($this->client->user_service) || $this->client->user_service < 0)) {
                $this->cli      = '';
                $this->cli_name = '';
            }
        } else {
            // check if status has changed and update as necessary
            if ($this->isAttributeDirty('status')) {
                if ((int)$this->status === 0) {
                    $this->datetime_cancel = date('Y-m-d H:i:s');
                    // TODO: Свойство originalAttributes наследуется от родительского класса
                } elseif ((int)$this->originalAttributes['status'] === 0 && (int)$this->status > 0) {
                    $this->datetime_cancel = '0000-00-00 00:00:00';
                    $this->asterisk        = 0;
                }
            }

            // make sure termination ID is correct
            $redirect = Globals::numbersOnly($this->redirect);
            if ($this->redirect_country_id > 0 && !empty($redirect)) {
                $countryPhoneCode = CountryCode::getCountryPhoneCode($this->redirect_country_id);
                $terminationId    = Termination::getTerminationIdByNumber($countryPhoneCode . $redirect);
                if ($terminationId) {
                    $this->termination_id = $terminationId;
                }
            }

            // make sure the redirect number isn't the same as the did number
            if (!empty($this->did) && !empty($redirect) && $redirect == $this->did->did) {
                return false;
            }
        }
        // update datetime
        $this->datetime_last_update = date('Y-m-d H:i:s');
        $this->admin_id_last_update = Globals::user()->id;

        // make sure app_status is null if less than 0
        // this is due to having to use -1 for null value in x-editable
        if ($this->app_status < 0) {
            $this->app_status = null;
        }

        // set null values where appropriate
        $nullIfEmptyFields = ['redirect_e164', 'redirect_override', 'redirect_function'];
        foreach ($nullIfEmptyFields as $nullIfEmptyField) {
            if ($this->{$nullIfEmptyField} == '') {
                $this->{$nullIfEmptyField} = null;
            }
        }

        return parent::beforeSave();
    }

    /**
     * @deprecated
     * @inheritdoc
     */
    public function afterSave()
    {
        // check if status was changed and update related dids
        // TODO: isAttributeDirty inherited from parent, need to replace
        if ($this->isAttributeDirty('status')) {
            if ((int)$this->status === 0) {
                // update associated did
                $did = Did::findOne($this->did_id);
                if ($did !== null) {
                    $did->did_in_use    = 0;
                    $did->did_available = $this->defective ? 2 : 1;
                    $did->did_user_id   = 0;
                    // cancel dates if set to defective
                    if ($this->defective) {
                        $did->did_datetime_cancel = date('Y-m-d H:i:s');
                        $did->admin_id_cancel     = Globals::user()->id;
                    }
                    $did->save();
                }
            } else {
                // update associated did
                $did = Did::findOne($this->did_id);
                if ($did !== null && $did->did_in_use == 0 && $did->did_available > 0) {
                    $did->did_in_use    = 1;
                    $did->did_available = 1;
                    $did->did_user_id   = $this->user_id;
                    $did->save();
                    // TODO: originalAttributes inherited from parent, need to fix
                } elseif ((int)$this->originalAttributes['status'] === 0) {
                    $this->status = 0;
                    $this->save(false, ['status']);
                }
            }
        }
        $this->blockDid($this->user_id);
        parent::afterSave();
    }

    /**
     * @deprecated
     * Ported from original code - see jail.inc.php function blockDID($user_id)
     *
     * @param int $clientId
     */
    public function oldBlockDid($clientId)
    {
        // TODO: Нужно просмотерть внимательно метод этот, там есть много деталей
        // note: if converting this to transaction based queries at later date,
        // be careful that any calling functions aren't already running transactions e.g. the cron
        $clientDids = $this->enabled()// TODO: Не понятно что это за метод, нужно заменить, скорее всего из scopes
        ->with([
            'client'      => [
                'select' => 'user_balance, user_limit, user_status',
            ],
            'country'     => [
                'select' => 'country_phone_code',
            ],
            'termination' => [
                'select' => 'country_id, termination_type',
            ],
        ])
            ->findAll('t.user_id=:clientId', [':clientId' => $clientId]);
        $client     = null;
        foreach ($clientDids as $clientDid) {
            $balance = $clientDid->client->user_balance + $clientDid->client->user_limit;

            // update redirect country id if it doesn't match the termination country id
            // TODO: This was ported from original code - do we really need this? Is it correct to do it this way?
            if ((strlen($clientDid->redirect) >= 5) && !empty($clientDid->termination) && ($clientDid->redirect_country_id != $clientDid->termination->country_id)) {
                $clientDid->redirect_country_id = $clientDid->termination->country_id;
                $clientDid->save();
            }

            // TODO: should this limit to 1 row? What if there is more than plan found?
            $criteria            = new CDbCriteria; // TODO: Нужно как-то заменить код ниже где используется CDbCriteria
            $criteria->select    = 'user_plan_id, plan_id, used, allowance';
            $criteria->with      = [
                'plan' => [
                    'select' => 'plan_termination_cost_fixed, plan_termination_cost_mobile',
                ],
            ];
            $criteria->condition = 'user_id=:clientID AND (countries like("%' . $clientDid->termination->country_id . ',%")) AND status!=0';
            $criteria->params    = [':clientID' => $clientId];
            $criteria->limit     = 1;
            $clientPlan          = ClientPlan::model()->find($criteria);

            $number_allowance = 0;
            $plan_id          = 0;
            if ($clientPlan) {
                $allowance = ($clientPlan->allowance < 0) ? 0 : $clientPlan->allowance;
                switch ($clientDid->termination->termination_type) {
                    case Termination::TYPE_FIXED :
                        $number_allowance = empty($clientPlan->plan->plan_termination_cost_fixed) || (float)$clientPlan->plan->plan_termination_cost_fixed <= 0 ?
                            $allowance :
                            round($balance / $clientPlan->plan->plan_termination_cost_fixed) + $allowance;
                        break;
                    case Termination::TYPE_MOBILE :
                        $number_allowance = empty($clientPlan->plan->plan_termination_cost_mobile) || (float)$clientPlan->plan->plan_termination_cost_mobile <= 0 ?
                            $allowance :
                            round($balance / $clientPlan->plan->plan_termination_cost_mobile) + $allowance;
                        break;
                    // TODO: should this not check for empty termination cost like the others?
                    case Termination::TYPE_SATELLITE :
                        $number_allowance = round($balance / $clientPlan->plan->plan_termination_cost_mobile) + $allowance;
                        break;
                }
                $plan_id = $clientPlan->plan_id;
            } else {
                $criteria            = new CDbCriteria; // TODO: Нужно переписать на Yii2
                $criteria->select    = 'user_plan_id, plan_id';
                $criteria->condition = 'user_id=:clientID AND countries="" AND status!=0';
                $criteria->params    = [':clientID' => $clientId];
                $criteria->limit     = 1;
                $clientPlan          = ClientPlan::model()->find($criteria);
                if ($clientPlan) {
                    $criteria            = new CDbCriteria;
                    $criteria->select    = 'rate_id, fixed, mobile';
                    $criteria->condition = 'plan_id=:planId AND country_id=:countryId';
                    $criteria->params    = [':planId' => $clientPlan->plan_id, ':countryId' => $clientDid->termination->country_id];
                    $criteria->limit     = 1;
                    $planRate            = PlanRate::model()->find($criteria);
                    if ($planRate) {
                        switch ($clientDid->termination->termination_type) {
                            case Termination::TYPE_FIXED :
                                $number_allowance = empty($planRate->fixed) || (float)$planRate->fixed <= 0 ? 0 : round($balance / $planRate->fixed);
                                break;
                            case Termination::TYPE_MOBILE :
                                $number_allowance = empty($planRate->mobile) || (float)$planRate->mobile <= 0 ? 0 : round($balance / $planRate->mobile);
                                break;
                            case Termination::TYPE_SATELLITE :
                                $number_allowance = round($balance / $planRate->mobile);
                                break;
                        }
                    }
                    $plan_id = $clientPlan->plan_id;
                }
            }

            if (empty($clientDid->redirect_country_id)) {
                $number_allowance = 44600;
            }

            if ($number_allowance <= 0) {
                $number_allowance  = 0;
                $clientDid->status = ($clientDid->client->user_status == Client::STATUS_INACTIVE || $clientDid->client->user_status == Client::STATUS_PENDING) ?
                    $clientDid->client->user_status : Client::STATUS_BLOCKED;
            }

            $clientDid->allowance = $number_allowance;
            $clientDid->plan_id   = $plan_id;
            $clientDid->asterisk  = 0;
            $clientDid->save();
        }

        // total dids - SELECT COUNT(*) AS id FROM user_dids WHERE user_id='$user_id' AND status!='0'
        $totalDids = $this->count('user_id=:clientId AND status!=0', [':clientId' => $clientId]);
        // total dids blocked - SELECT COUNT(*) AS id FROM user_dids WHERE user_id='$user_id' AND status='2'
        $totalBlockedDids = $this->count('user_id=:clientId AND status=2', [':clientId' => $clientId]);
        // total user plans - SELECT COUNT(*) AS user_plan_id FROM user_plans WHERE user_id='$user_id' AND status!='0'
        $totalClientPlans = ClientPlan::model()->count('user_id=:clientId AND status!=0', [':clientId' => $clientId]);

        if ($totalDids == $totalBlockedDids && !empty($totalDids) && !empty($totalClientPlans)) {
            $client = Client::findOne($clientId);
            if ($client && $client->user_status = Client::STATUS_ACTIVE) {
                $client->user_status = Client::STATUS_BLOCKED;
                $client->save();
                $clientLog              = new ClientLog;
                $clientLog->user_id     = $clientId;
                $clientLog->user_status = Client::STATUS_BLOCKED;
                $clientLog->log_by      = 1; // the system user id
                $clientLog->save();
            }
        }
    }

    /**
     * @param int $clientId
     * @param null|string $calledBy
     */
    public function blockDidNew($clientId, $calledBy = null)
    {
        Yii::$app->db->createCommand()->insert('user_status', [
            'user_id'   => $clientId,
            'called_by' => $calledBy,
        ]);
    }

    /**
     * @deprecated
     *
     * @param int $clientId
     */
    public function blockDid($clientId)
    {
        $today      = date('Y-m-d H:i:s');
        $clientDids = (new Query())
            ->select('d.id, d.redirect_country_id, d.redirect, d.termination_id, d.status,
                            c.user_balance, c.user_limit, c.user_status, cc.country_phone_code,
                            t.country_id, t.termination_type, c.user_facility')
            ->from('user_dids d')
            ->join('user_datas c', 'c.user_id=d.user_id')
            ->join('country_codes cc', 'cc.country_id=d.redirect_country_id')
            ->join('terminations t', 't.termination_id=d.termination_id')
            ->where('d.user_id=:id AND status!=0', [':id' => $clientId])
            ->all();

        foreach ($clientDids as $clientDid) {
            $balance = $clientDid['user_balance'] + $clientDid['user_limit'];

            // update redirect country id if it doesn't match the termination country id
            // TODO: This was ported from original code - do we really need this? Is it correct to do it this way?
            if ((strlen($clientDid['redirect']) >= 5) && !empty($clientDid['termination']) && ($clientDid['redirect_country_id'] != $clientDid['country_id'])) {
                Yii::$app->db->createCommand('UPDATE user_dids SET redirect_country_id=\'' . $clientDid['country_id'] . '\' WHERE id=' . $clientDid['id'])->execute();
            }

            $isVoicemail = ($clientDid['redirect'] == '13044032527@pbx' || strstr(strtolower($clientDid['redirect']), 'vm@pbx') || strstr(strtolower($clientDid['redirect']), 'vmes@pbx'));

            if ($isVoicemail) {
                $clientPlan = (new Query())
                    ->select('up.user_plan_id, up.plan_id, up.used, up.allowance, p.plan_termination_cost_fixed, p.plan_termination_cost_mobile')
                    ->from('user_plans up')
                    ->leftJoin('plans p', 'p.plan_id=up.plan_id')
                    ->where('up.user_id=:id AND up.plan_id = 91 AND up.status!=0', [':id' => $clientId])
                    ->one();
                if (empty($clientPlan)) {
                    $isVoicemail = false;
                }
            } else {
                $clientPlan = (new Query())
                    ->select('up.user_plan_id, up.plan_id, up.used, up.allowance, p.plan_termination_cost_fixed, p.plan_termination_cost_mobile')
                    ->from('user_plans up')
                    ->leftJoin('plans p', 'p.plan_id=up.plan_id')
                    ->where('p.plan_type = "domestic" AND p.voice_enabled = 1 AND up.user_id=:id AND (up.countries = "' . $clientDid['country_id'] . '" OR up.countries like("' . $clientDid['country_id'] . ',%") OR up.countries like("%,' . $clientDid['country_id'] . ',%")) AND up.status!=0', [':id' => $clientId])
                    ->one();
            }

            $number_allowance = 0;
            $voip_allowance   = 0;
            $plan_id          = 0;
            $app_plan_id      = 'NULL';
            if ($clientPlan) {
                $allowance = ($clientPlan['allowance'] < 0) ? 0 : $clientPlan['allowance'];
                switch ($clientDid['termination_type']) {
                    case Termination::TYPE_FIXED :
                        //dumpd($clientPlan);
                        $number_allowance = empty($clientPlan['plan_termination_cost_fixed']) || (float)$clientPlan['plan_termination_cost_fixed'] <= 0 ?
                            $allowance :
                            round($balance / $clientPlan['plan_termination_cost_fixed']) + $allowance;
                        break;
                    case Termination::TYPE_MOBILE :
                        $number_allowance = empty($clientPlan['plan_termination_cost_mobile']) || (float)$clientPlan['plan_termination_cost_mobile'] <= 0 ?
                            $allowance :
                            round($balance / $clientPlan['plan_termination_cost_mobile']) + $allowance;
                        break;
                    // TODO: should this not check for empty termination cost like the others?
                    case Termination::TYPE_SATELLITE :
                        $number_allowance = round($balance / $clientPlan['plan_termination_cost_mobile']) + $allowance;
                        break;
                }
                $plan_id = $clientPlan['plan_id'];
                unset($clientPlan);

                // set voip allowance the same as voice allowance for domestic countries
                if (in_array($clientDid['redirect_country_id'], ['168', '169', '223', '224'])) {
                    $voip_allowance = $number_allowance;
                }
            } else {
                $appPlan = (new Query())
                    ->select('up.user_plan_id, up.plan_id, up.used, up.allowance, up.countries')
                    ->from('user_plans up')
                    ->leftJoin('plans p', 'p.plan_id=up.plan_id')
                    //->where('up.user_id=:id AND up.countries = "" AND up.status!=0 AND up.plan_id != 181', array(':id'=>$clientId))
                    ->where('up.user_id=:id AND p.plan_type = "international" AND p.plan_termination_minutes > 0 AND up.status!=0 AND p.voip_enabled = 1', [':id' => $clientId])
                    ->one();

                // if there is an app plan and it allows this redirect country then update the voip allowance
                if ($appPlan && ($appPlan['countries'] == '' || strstr($appPlan['countries'], $clientDid['redirect_country_id'] . ','))) {
                    $voip_allowance = $voip_allowance + (($appPlan['allowance'] < 0) ? 0 : $appPlan['allowance']);
                    $app_plan_id    = $appPlan['plan_id'];
                }
                unset($appPlan);

                // @todo make sure that, if we are adding together the voip allowance from multiple plans, that the cdr trigger can handle charging multiple plans too!!

                // note: harcoded ignoring of international texting plan for now (id: 181)
                $clientPlan = (new Query())
                    ->select('up.user_plan_id, up.plan_id, up.used, up.allowance, p.voip_enabled')
                    ->from('user_plans up')
                    ->leftJoin('plans p', 'p.plan_id=up.plan_id')
                    ->where('up.user_id=:id AND up.countries = "" AND up.status!=0 AND p.voice_enabled = 1', [':id' => $clientId])
                    ->one();

                if ($clientPlan) {
                    // note: added plan rate facility overrides 2016-09-12
                    $facilityId = empty($clientDid['user_facility']) ? 0 : (int)$clientDid['user_facility'];
                    $planRate   = (new Query())
                        ->select('pr.rate_id, COALESCE(prf.fixed, pr.fixed) AS `fixed`, COALESCE(prf.mobile, pr.mobile) AS `mobile`, COALESCE(prf.voip, pr.voip) AS `voip`')
                        ->from('plans_rates pr')
                        ->leftJoin('plan_rate_facility prf', 'prf.rate_id=pr.rate_id AND prf.facility_id = ' . $facilityId)
                        ->where('pr.plan_id=:planId AND pr.country_id=:countryId', [':planId' => $clientPlan['plan_id'], ':countryId' => $clientDid['country_id']])
                        ->one();

                    if ($planRate) {
                        switch ($clientDid['termination_type']) {
                            case Termination::TYPE_FIXED :
                                $number_allowance = empty($planRate['fixed']) || (float)$planRate['fixed'] <= 0 ? 0 : round($balance / $planRate['fixed']);
                                break;
                            case Termination::TYPE_MOBILE :
                                $number_allowance = empty($planRate['mobile']) || (float)$planRate['mobile'] <= 0 ? 0 : round($balance / $planRate['mobile']);
                                break;
                            case Termination::TYPE_SATELLITE :
                                $number_allowance = round($balance / $planRate['mobile']);
                                break;
                        }
                        // check for voip allowance
                        $voip_allowance = $voip_allowance + (empty($clientPlan['voip_enabled']) || empty($planRate['voip']) || (float)$planRate['voip'] <= 0 ? 0 : round($balance / $planRate['voip']));
                    }
                    $plan_id = $clientPlan['plan_id'];
                }
                unset($clientPlan);
            }

            if (empty($clientDid['redirect_country_id']) || $isVoicemail) {
                $number_allowance = 44600;
            }

            if ($number_allowance <= 0) {
                $number_allowance = 0;
            }

            if ($voip_allowance <= 0) {
                $voip_allowance = 0;
            }

            if (empty($number_allowance) && empty($voip_allowance)) {
                // moved this here from above so app only plans aren't blocked
                $clientDid['status'] = ($clientDid['user_status'] == Client::STATUS_INACTIVE || $clientDid['user_status'] == Client::STATUS_PENDING) ?
                    $clientDid['user_status'] : Client::STATUS_BLOCKED;
            } elseif ($clientDid['status'] == Client::STATUS_BLOCKED && $balance >= 0) {
                // reset to active if previously blocked - this matches up with the associated db trigger (user_status_before_insert)
                $clientDid['status'] = Client::STATUS_ACTIVE;
            }

            $sql = 'UPDATE user_dids SET allowance=\'' . $number_allowance . '\', voip_allowance=\'' . $voip_allowance . '\', plan_id=\'' . $plan_id . '\', app_plan_id=' . $app_plan_id . ', datetime_last_update=\'' . $today . '\', status=\'' . $clientDid['status'] . '\', admin_id_last_update=\'1\', asterisk=\'0\' WHERE id=\'' . $clientDid['id'] . '\'';
            Yii::$app->db->createCommand($sql)->execute();
        }

        $totalDids = Yii::$app->db->createCommand('SELECT COUNT(*) FROM user_dids WHERE user_id=\'' . $clientId . '\' AND status != 0 AND redirect NOT LIKE \'vm@pbx%\' AND redirect NOT LIKE \'vmes@pbx%\'')->queryScalar();
        $totalBlockedDids = Yii::$app->db->createCommand('SELECT COUNT(*) FROM user_dids WHERE user_id=\'' . $clientId . '\' AND status = 2 AND redirect NOT LIKE \'vm@pbx%\' AND redirect NOT LIKE \'vmes@pbx%\'')->queryScalar();
        $totalClientPlans = Yii::$app->db->createCommand('SELECT COUNT(*) FROM user_plans WHERE user_id=\'' . $clientId . '\' AND status != 0')->queryScalar();

        if ($totalDids == $totalBlockedDids && !empty($totalDids) && !empty($totalClientPlans)) {
            $clientStatus = Yii::$app->db->createCommand('SELECT user_status FROM user_datas WHERE user_id=\'' . $clientId . '\'')->queryScalar();
            if ($clientStatus !== false && $clientStatus = Client::STATUS_ACTIVE) {
                $sql = 'UPDATE user_datas SET user_status=\'2\' WHERE user_id=\'' . $clientId . '\'';
                Yii::$app->db->createCommand($sql)->execute();
                $clientLog              = new ClientLog();
                $clientLog->user_id     = $clientId;
                $clientLog->user_status = Client::STATUS_BLOCKED;
                $clientLog->log_by      = 1; // the system user id
                $clientLog->save();
            }
        }
    }

    /**
     * @deprecated
     * @param int $clientId
     */
    public function blockDidTest($clientId)
    {
        $today = date('Y-m-d H:i:s');

        $clientDids = (new Query())
            ->select('d.id, d.redirect_country_id, d.redirect, d.termination_id, d.status,
                            c.user_balance, c.user_limit, c.user_status, cc.country_phone_code,
                            t.country_id, t.termination_type, d.plan_id')
            ->from('user_dids d')
            ->join('user_datas c', 'c.user_id=d.user_id')
            ->join('country_codes cc', 'cc.country_id=d.redirect_country_id')
            ->join('terminations t', 't.termination_id=d.termination_id')
            ->where('d.user_id=:id AND status!=0', [':id' => $clientId])
            ->all();

        foreach ($clientDids as $clientDid) {
            $balance = $clientDid['user_balance'] + $clientDid['user_limit'];
            if ((strlen($clientDid['redirect']) >= 5) && !empty($clientDid['termination']) && ($clientDid['redirect_country_id'] != $clientDid['country_id'])) {
                Yii::$app->db->createCommand('UPDATE user_dids SET redirect_country_id=\'' . $clientDid['country_id'] . '\' WHERE id=' . $clientDid['id'] . '\'')->execute();
            }

            $isVoicemail = ($clientDid['redirect'] == '13044032527@pbx' || strstr(strtolower($clientDid['redirect']), 'vm@pbx') || strstr(strtolower($clientDid['redirect']), 'vmes@pbx'));

            // if we already have a plan id, let's use that
            $plan_id = empty($clientDid['plan_id']) ? 0 : $clientDid['plan_id'];
            if (!empty($plan_id)) {
                $clientPlan = (new Query())
                    ->select('up.user_plan_id, up.plan_id, up.used, up.allowance, p.plan_termination_cost_fixed, p.plan_termination_cost_mobile')
                    ->from('user_plans up')
                    ->leftJoin('plans p', 'p.plan_id=up.plan_id')
                    ->where('up.user_id=:id AND up.plan_id = :planId AND up.status!=0', [':id' => $clientId, ':planId' => $plan_id])
                    ->one();
            } elseif ($isVoicemail) {
                $clientPlan = (new Query())
                    ->select('up.user_plan_id, up.plan_id, up.used, up.allowance, p.plan_termination_cost_fixed, p.plan_termination_cost_mobile')
                    ->from('user_plans up')
                    ->leftJoin('plans p', 'p.plan_id=up.plan_id')
                    ->where('up.user_id=:id AND up.plan_id = 91 AND up.status!=0', [':id' => $clientId])
                    ->one();
                if (empty($clientPlan)) {
                    $isVoicemail = false;
                }
            } else {
                $clientPlan = (new Query())
                    ->select('up.user_plan_id, up.plan_id, up.used, up.allowance, p.plan_termination_cost_fixed, p.plan_termination_cost_mobile')
                    ->from('user_plans up')
                    ->leftJoin('plans p', 'p.plan_id=up.plan_id')
                    ->where('up.user_id=:id AND (up.countries like("%' . $clientDid['country_id'] . ',%")) AND up.status!=0', [':id' => $clientId])
                    ->one();
            }

            $number_allowance = 0;
            if ($clientPlan) {
                $allowance = ($clientPlan['allowance'] < 0) ? 0 : $clientPlan['allowance'];
                switch ($clientDid['termination_type']) {
                    case Termination::TYPE_FIXED :
                        $number_allowance = empty($clientPlan['plan_termination_cost_fixed']) || (float)$clientPlan['plan_termination_cost_fixed'] <= 0 ?
                            $allowance :
                            round($balance / $clientPlan['plan_termination_cost_fixed']) + $allowance;
                        break;
                    case Termination::TYPE_MOBILE :
                        $number_allowance = empty($clientPlan['plan_termination_cost_mobile']) || (float)$clientPlan['plan_termination_cost_mobile'] <= 0 ?
                            $allowance :
                            round($balance / $clientPlan['plan_termination_cost_mobile']) + $allowance;
                        break;
                    // TODO: should this not check for empty termination cost like the others?
                    case Termination::TYPE_SATELLITE :
                        $number_allowance = round($balance / $clientPlan['plan_termination_cost_mobile']) + $allowance;
                        break;
                }
                $plan_id = $clientPlan['plan_id'];
                unset($clientPlan);
            } else {
                $clientPlan = (new Query())
                    ->select('up.user_plan_id, up.plan_id, up.used, up.allowance')
                    ->from('user_plans up')
                    ->where('up.user_id=:id AND up.countries = "" AND up.status!=0', [':id' => $clientId])
                    ->one();

                if ($clientPlan) {
                    $planRate = (new Query())
                        ->select('rate_id, fixed, mobile')
                        ->from('plans_rates')
                        ->where('plan_id=:planId AND country_id=:countryId', [':planId' => $clientPlan['plan_id'], ':countryId' => $clientDid['country_id']])
                        ->one();

                    if ($planRate) {
                        switch ($clientDid['termination_type']) {
                            case Termination::TYPE_FIXED :
                                $number_allowance = empty($planRate['fixed']) || (float)$planRate['fixed'] <= 0 ? 0 : round($balance / $planRate['fixed']);
                                break;
                            case Termination::TYPE_MOBILE :
                                $number_allowance = empty($planRate['mobile']) || (float)$planRate['mobile'] <= 0 ? 0 : round($balance / $planRate['mobile']);
                                break;
                            case Termination::TYPE_SATELLITE :
                                $number_allowance = round($balance / $planRate['mobile']);
                                break;
                        }
                    }
                    $plan_id = $clientPlan['plan_id'];
                }
                unset($clientPlan);
            }

            if (empty($clientDid['redirect_country_id']) || $isVoicemail) {
                $number_allowance = 44600;
            }

            if ($number_allowance <= 0) {
                $number_allowance    = 0;
                $clientDid['status'] = ($clientDid['user_status'] == Client::STATUS_INACTIVE || $clientDid['user_status'] == Client::STATUS_PENDING) ?
                    $clientDid['user_status'] : Client::STATUS_BLOCKED;
            }

            $sql = 'UPDATE user_dids SET allowance=\'' . $number_allowance . '\', plan_id=\'' . $plan_id . '\', datetime_last_update=\'' . $today . '\', status=\'' . $clientDid['status'] . '\', admin_id_last_update=\'1\', asterisk=\'0\' WHERE id=\'' . $clientDid['id'] . '\'';
            Yii::$app->db->createCommand($sql)->execute();
        }

        $totalDids = Yii::$app->db->createCommand('SELECT COUNT(*) FROM user_dids WHERE user_id=\'' . $clientId . '\' AND status != 0 AND redirect NOT LIKE \'vm@pbx%\' AND redirect NOT LIKE \'vmes@pbx%\'')->queryScalar();
        $totalBlockedDids = Yii::$app->db->createCommand('SELECT COUNT(*) FROM user_dids WHERE user_id=\'' . $clientId . '\' AND status = 2 AND redirect NOT LIKE \'vm@pbx%\' AND redirect NOT LIKE \'vmes@pbx%\'')->queryScalar();
        $totalClientPlans = Yii::$app->db->createCommand('SELECT COUNT(*) FROM user_plans WHERE user_id=\'' . $clientId . '\' AND status != 0')->queryScalar();

        if ($totalDids == $totalBlockedDids && !empty($totalDids) && !empty($totalClientPlans)) {
            $clientStatus = Yii::$app->db->createCommand('SELECT user_status FROM user_datas WHERE user_id=\'' . $clientId . '\'')->queryScalar();
            if ($clientStatus !== false && $clientStatus = Client::STATUS_ACTIVE) {
                $sql = 'UPDATE user_datas SET user_status=\'2\' WHERE user_id=\'' . $clientId . '\'';
                Yii::$app->db->createCommand($sql)->execute();
                $clientLog              = new ClientLog;
                $clientLog->user_id     = $clientId;
                $clientLog->user_status = Client::STATUS_BLOCKED;
                $clientLog->log_by      = 1;
                $clientLog->save();
            }
        }
    }

    /**
     * Render the app status value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridAppStatus']
     *
     * @param ClientDid $data
     *
     * @return string
     */
    public function gridAppStatus($data)
    {

        if (empty($data->appAccount)) {
            return '<span class="text-error">No App Account Detected</span>';
        }

        $msg = [
            'App Account:',
            $data->appAccount->username,
            ($data->appAccount->status == 1 ? '(Active)' : '(Unverified)'),
        ];

        $class = ($data->appAccount->status == 1 ? 'text-success' : 'text-warning');

        return '<span class="' . $class . '">' . implode('<br />', $msg) . '</span>';
    }

    /**
     * Render the app status value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridAppVoice']
     *
     * @param ClientDid $data
     *
     * @return string
     */
    public function gridAppText($data)
    {

        if (empty($data->appAccount)) {
            return '';
        } elseif ($data->appAccount->status != 1) {
            return '';
        }

        $html = $this->renderEditableAppStatus($data->user_id, $data->appAccount->getActiveUserSettings($data->user_id), 'text_status');

        return $html;
    }

    /**
     * Render the app status value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridAppVoice']
     *
     * @param ClientDid $data
     *
     * @return string
     */
    public function gridAppVoice($data)
    {

        if (empty($data->appAccount)) {
            return '';
        } elseif ($data->appAccount->status != 1) {
            return '';
        }

        // display editable voice_status
        $html = $this->renderEditableAppStatus($data->user_id, $data->appAccount->getActiveUserSettings($data->user_id), 'voice_status');

        return $html;
    }

    /**
     * @deprecated
     * @param object $data
     *
     * @return string
     */
    public function gridAppSettingsScope($data)
    {
        if (empty($data->appAccount)) {
            return '';
        } elseif ($data->appAccount->status != 1) {
            $msg = [
                'App Account Unverified',
                Html::a($data->appAccount->username, Url::to(['/appAccount/index', ['AppAccount[username]' => $data->appAccount->username]])),
            ];

            return '<span class="text-warning">' . implode('<br />', $msg) . '</span>';
        }

        $gridId        = 'client-did-grid'; // better way of getting grid ID rather than hard coding?
        $clientId      = $data->user_id;
        $settingsModel = $data->appAccount->getActiveUserSettings($data->user_id);

        $name = 'appAccountSettings_scope_' . $data->id;
        $htmlOptions = [
            'template'     => '{input} {label}',
            'separator'    => "<br />\n",
            'container'    => null,
            'container'    => 'span',
            'labelOptions' => ['style' => 'font-size:10px;display:inline;'],
        ];
        $selected    = (int) $settingsModel->isOverridenByUser();

        $userOverrideUrl = Url::to(['/appAccount/userOverride', ['appAccountId' => $settingsModel->app_account_id, 'clientId' => $clientId]]);
        $script          = "
            var {$name}_val = $('input:radio[name=\"{$name}\"]:checked').val();
            var postData = {" . Yii::app()->request->csrfTokenName . ": '" . Yii::app()->request->csrfToken . "', override : {$name}_val};
            $.post('" . $userOverrideUrl . "',postData, function(data) {
                $('input:radio[name=\"{$name}\"]:checked').parent().html('Saving. Please wait...');
                $.fn.yiiGridView.update('{$gridId}');
            }, 'json');
        ";
        Yii::app()->getClientScript()->registerScript(__CLASS__ . '#' . $gridId . $name . '-event', '
            $("#' . $gridId . '").parent().on("change", "input:radio[name=\'' . $name . '\']", function() {' . $script . '});
        '); // TODO: Need to replace this part

        $html = Html::a($data->appAccount->username, Url::to(['/appAccount/index', ['AppAccount[username]' => $data->appAccount->username]]));
        $html .= '<br />' . Html::radioList($name, $selected, ['All Clients', 'This Client Only'], $htmlOptions);

        return $html;
    }

    /**
     * @deprecated
     * @param AppAccountSettings $settingsModel
     * @param string $attribute
     *
     * @return string
     */
    public function renderEditableAppStatus($clientId, $settingsModel, $attribute)
    {
        $gridId = 'client-did-grid';

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
        // TODO: Need to figure out how to edit
        $widget = Yii::app()->controller->createWidget('application.widgets.ETbEditableField', $options);

        //manually make selector non unique to match all cells in column
        $selector                   = get_class($widget->model) . '_' . $widget->attribute;
        $widget->htmlOptions['rel'] = $selector;

        //can't call run() as it registers clientScript
        $widget->renderLink();

        $html = ob_get_clean();

        //manually render client script (one for all cells in column)
        $script = $widget->registerClientScript();
        //use parent() as grid is totally replaced by new content
        // TODO: Need to replace this code that it would be possible to connect JS
        Yii::app()->getClientScript()->registerScript(__CLASS__ . '#' . $gridId . $selector . '-event', '
            $("#' . $gridId . '").parent().on("ajaxUpdate.yiiGridView", "#' . $gridId . '", function() {' . $script . '});
        ');

        // return the widget output from the buffer
        return $html;
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
    public function getDid()
    {
        return $this->hasOne(Did::className(), ['did_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCountry()
    {
        return $this->hasOne(CountryCode::className(), ['redirect_country_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTermination()
    {
        return $this->hasOne(Termination::className(), ['termination_id' => 'id']);
    }

    /**
     * @return $this
     */
    public function getAppAccount()
    {
        return $this->hasOne(AppAccount::className(), ['' => 'id'])->onCondition('t.redirect_e164 = appAccount.phone_number');
    }
}
