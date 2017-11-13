<?php

namespace backend\models;

use backend\helpers\Globals;
use Yii;
use yii\base\Model;

/**
 * Search class.
 * Search is the data structure for keeping
 * search data. It is used by the 'search' action of 'SiteController'.
 */
class Search extends Model
{
    public $q;

    /**
     * This will return an array of search data
     */
    public function lookup()
    {
        if (empty($this->q)) {
            return [];
        }

        $q  = trim(strtolower($this->q));
        $q1 = substr($q, 0, 1);
        $q2 = substr($q, 0, 2);
        $q3 = substr($q, 0, 3);
        $q4 = substr($q, 0, 4);
        if (!strpos($q, "@")) {
            $qnum = Globals::numbersOnly($q);
        } else {
            $qnum = "";
        }

        // PaperInstruments
        if (strlen($qnum) > 0 && substr($q, 0, 2) == "pi") {
            $piclients = Yii::$app->db->createCommand()
                ->select('t.ref, t.added, t.status, u.user_id, u.user_first_name, u.user_last_name, u.user_inmate_first_name, 
                            u.user_inmate_last_name, u.user_status, u.user_reg_number, u.user_phone')
                ->from('paper t')
                ->join('user_datas u', 'u.user_id=t.user_id')
                ->where('t.ref LIKE :qnum', [':qnum' => '%' . $qnum . '%'])
                ->order('user_id ASC')
                ->limit(5)
                ->queryAll();
        } else {
            $piclients = [];
        }

        // Clients
        if (in_array($q1, ['#', '-']) || $q2 == 'ui' || $q1 == '+') {
            if ($q1 == '+') {
                // match the phone field
                $clients = Yii::$app->db->createCommand()
                    ->select('t.user_id, t.user_first_name, t.user_last_name, t.user_inmate_first_name, 
                            t.user_inmate_last_name, t.user_status, t.user_reg_number, t.user_phone')
                    ->from(Client::tableName() . ' t')
                    ->join('user_phone up', 'up.user_id=t.user_id')
                    ->where('up.user_phone LIKE :qnum', [':qnum' => '%' . $qnum . '%'])
                    ->order('user_id ASC')
                    ->limit(5)
                    ->queryAll();
            } else {
                // exact match on client Id
                $clients = Yii::$app->db->createCommand()
                    ->select('user_id, user_first_name, user_last_name, user_inmate_first_name, user_inmate_last_name, user_status, user_reg_number')
                    ->from(Client::tableName())
                    ->where('user_id = :qnum', [':qnum' => $qnum])
                    ->order('user_id ASC')
                    ->limit(5)
                    ->queryAll();
            }
        } else {
            // exact match on client id or partial matches on other fields
            $clients = Yii::$app->db->createCommand()
                ->select('user_id, user_first_name, user_last_name, user_inmate_first_name, user_inmate_last_name, user_status, user_reg_number, (CASE WHEN user_status = 1 THEN 0 ELSE 1 END) AS activefirst')
                ->from(Client::tableName())
                ->where('user_id = :qnum', [':qnum' => $qnum])
                ->orWhere(['like', 'user_full_name', '%' . $q . '%'])
                ->orWhere(['like', 'user_inmate_full_name', '%' . $q . '%'])
                ->orWhere(['like', 'user_email', '%' . $q . '%'])
                ->orWhere(['like', 'user_reg_number', $q . '%'])
                ->orWhere(['like', 'REPLACE(user_reg_number, "-", "")', (is_numeric($qnum) ? $qnum : $q) . '%'])
                ->orWhere(['like', 'REPLACE(REPLACE(REPLACE(REPLACE(user_phone, "(", ""), ")", ""), " ", ""), "-", "")', (is_numeric($qnum) ? $qnum : $q) . '%'])
                ->order('activefirst ASC, user_status DESC, user_last_name ASC, user_first_name ASC')
                ->limit(10)
                ->queryAll();
        }

        // Client CC
        if ($q2 == 'cc' || in_array($q3, ['#cc', '-cc', 'ucc', 'ccc'])) {
            // partial match on last 4 digits of card
            $clientcc = Yii::$app->db->createCommand()
                ->select('t.billing_id, t.user_id, t.cc_type, t.cc_last_4, u.user_first_name, u.user_last_name, u.user_inmate_first_name, u.user_inmate_last_name, u.user_status, t.cc_number')
                ->from(ClientBilling::tableName() . ' t')
                ->join(Client::tableName() . ' u', 'u.user_id=t.user_id')
                ->where(['like', 't.cc_last_4', $qnum . '%'])
                ->andWhere('t.user_id!=0')
                ->andWhere('t.billing_status=1')
                ->group('t.user_id')
                ->order('t.cc_last_4 ASC')
                ->limit(10)
                ->queryAll();
        } else {
            $clientcc = [];
        }

        // Client Transactions - here we check if we should search on the last four digits or full number
        if ($q2 == 'tx') {
            // match on transaction id
            $clienttx = Yii::$app->db->createCommand()
                ->select('t.transaction_id, t.user_id, t.credit_update, t.transaction_currency, t.reason, t.comment, u.user_first_name, u.user_last_name, u.user_inmate_first_name, u.user_inmate_last_name, u.user_status')
                ->from(ClientTransaction::tableName() . ' t')
                ->join(Client::tableName() . ' u', 'u.user_id=t.user_id')
                ->where('t.transaction_id = :qnum', [':qnum' => $qnum])
                ->andWhere('t.user_id!=0')
                ->queryAll();
        } else {
            $clienttx = [];
        }

        // Client CC Transactions - here we check if we should search on the last four digits or full number
        $last4    = $q2 == 'cc' || in_array($q3, ['#cc', '-cc', 'ucc', 'ccc', 'cct']) || $q4 == 'tccl';
        $fullCard = $q2 == 'tc' || $q3 == 'cct';
        if ($last4 || $fullCard) {
            $sql       = "SELECT a.*, GROUP_CONCAT(a.requested) AS datetimes FROM (
                    SELECT u.user_id, u.user_status,
                    (CASE WHEN t.cc_number IS NULL THEN tfd.cc_number_plain ELSE t.cc_number END) AS cc_number,
                    (CASE WHEN t.cc_number IS NULL THEN RIGHT(tfd.cc_number_plain, 4) ELSE RIGHT(t.cc_number, 4) END) AS last4,
                    (CASE WHEN t.cc_type IS NULL THEN tfd.cc_type ELSE t.cc_type END) AS cc_type,
                    (CASE WHEN t.datetime IS NULL THEN tfd.requested ELSE t.datetime END) AS requested,
                    (CASE WHEN t.amount IS NULL THEN tfd.amount ELSE t.amount END) AS amount,
                    CONCAT(u.user_last_name, ', ', u.user_first_name, ' / ', u.user_inmate_last_name, ', ', u.user_inmate_first_name) AS `user`
                    FROM user_transactions ut
                    INNER JOIN user_datas u ON ut.user_id = u.user_id
                    LEFT JOIN transactions t ON t.user_transaction_id = ut.transaction_id
                    LEFT JOIN transaction_fd tfd ON tfd.user_transaction_id = ut.transaction_id
                    WHERE (RIGHT(t.cc_number, 4) LIKE '$qnum%' OR RIGHT(tfd.cc_number_plain, 4) LIKE '$qnum%')
                    ORDER BY requested DESC
                    ) a
                    WHERE a.amount > 0
                    GROUP BY user_id, last4
                    ORDER BY requested DESC
                    LIMIT 5";
            $clientcct = Yii::$app->db->createCommand($sql)->queryAll();
        } else {
            $clientcct = [];
        }

        // DIDs
        $dids = Yii::$app->db->createCommand()
            ->select('t.did, t.did_full, t.did_area_code, t.did_prefix, t.did_line, t.country_id, t.did_user_id, c.country_code_alpha_3, c.country_name, c.country_phone_code, u.user_first_name, u.user_last_name')
            ->from(Did::tableName() . ' t')
            ->join(Client::tableName() . ' u', 'u.user_id=t.did_user_id')
            ->join(CountryCode::tableName() . ' c', 'c.country_id=t.country_id')
            ->where('t.did_in_use!=0 AND t.did_user_id!=0 AND (t.did LIKE :qnum OR t.did_full LIKE :qnum)', [':qnum' => $qnum . '%'])
            //->andWhere(array('like', 't.did_full', '%'.$qnum.'%'))
            ->order('t.did_full ASC')
            ->limit(5)
            ->queryAll();

        $redirects = Yii::$app->db->createCommand()
            ->select('t.redirect, t.redirect_country_id, t.user_id, c.country_code_alpha_3, c.country_name, c.country_phone_code, u.user_first_name, u.user_last_name')
            ->from(ClientDid::tableName() . ' t')
            ->join(Client::tableName() . ' u', 'u.user_id=t.user_id')
            ->join(CountryCode::tableName() . ' c', 'c.country_id=t.redirect_country_id')
            ->where('t.status!=0 AND u.user_status!=0 AND t.redirect LIKE :qnum', [':qnum' => $qnum . '%'])
            //->andWhere(array('like', 't.did_full', '%'.$qnum.'%'))
            ->order('t.redirect ASC')
            ->limit(5)
            ->queryAll();

        // Facilities
        if (in_array($q2, ['fi', 'fn', 'fs', 'fc', 'fz', 'fp', 'ff', 'fa'])) {
            // various matches
            $whereParams = [':q' => substr($q, 2) . '%'];
            switch ($q2) {
                case 'fi':
                    $where       = 'facility_id = :qnum';
                    $whereParams = [':qnum' => $qnum];
                    break;
                case 'fn':
                    $where = 'facility_name LIKE :q';
                    break;
                case 'fs':
                    $where = 'facility_street LIKE :q';
                    break;
                case 'fc':
                    $where = 'facility_city LIKE :q';
                    break;
                case 'fz':
                    $where = 'facility_zip LIKE :q';
                    break;
                case 'fp':
                    $where = 'facility_phone LIKE :q';
                    break;
                case 'ff':
                    $where = 'facility_fax LIKE :q';
                    break;
                case 'fa':
                    $where       = 'facility_additional_notes LIKE :q';
                    $whereParams = [':q' => '%' . substr($q, 2) . '%'];
                    break;
                default:
                    $where = '';
            }

            $facilities = Yii::$app->db->createCommand()
                ->select('facility_id, facility_name, facility_state')
                ->from(Facility::tableName())
                ->where('facility_available=1')
                ->andWhere($where, $whereParams)
                ->order('facility_name ASC')
                ->limit(5)
                ->queryAll();
        } else {
            $whereParams = [':q' => '%' . $q . '%'];
            $facilities  = Yii::$app->db->createCommand()
                ->select('facility_id, facility_name, facility_state')
                ->from(Facility::tableName())
                ->where('facility_available=1')
                ->andWhere('facility_name LIKE :q OR facility_street LIKE :q OR facility_city LIKE :q OR facility_state LIKE :q OR facility_zip LIKE :q or facility_phone LIKE :q OR facility_fax LIKE :q OR facility_additional_notes LIKE :q', $whereParams)
                ->order('facility_name ASC')
                ->limit(5)
                ->queryAll();
        }

        return [
            'piclients'  => $piclients,
            'clients'    => $clients,
            'clientcc'   => $clientcc,
            'clienttx'   => $clienttx,
            'clientcct'  => $clientcct,
            'dids'       => $dids,
            'facilities' => $facilities,
            'redirects'  => $redirects,
        ];
    }

    /**
     * Declares the validation rules.
     * The rules state that username and password are required,
     * and password needs to be authenticated.
     */
    public function rules()
    {
        return [
            // username and password are required
            ['q', 'safe'],
        ];
    }
}
