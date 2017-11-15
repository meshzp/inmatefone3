<?php
/** Section to NavBar */

use yii\bootstrap\NavBar;
use kartik\nav\NavX;
use yii\helpers\Html;
?>

<?php
$piwikLoginUser = ''; // TODO: Need to declare a variable, previously was: Yii::app()->user->name == 'oz' ? 'ozm' : Yii::app()->user->name
$piwikLoginPass = 'b86792ac9e4b1b672d96de0388aa16c2';

if (Yii::$app->user->isGuest) {
    $menuItemsRight = [
        ['label' => 'Login', 'url' => ['/site/login']],
    ];
} else {
    $menuItemsLeft  = [
        ['label' => 'Clients', 'url' => ['/client/index']],
        ['label' => 'Facilities', 'url' => ['/facility/index']],
        ['label' => 'DIDs', 'url' => ['/did/index']],
        ['label' => 'Tickets', 'url' => ['/tickets']],
        ['label' => 'Reports', 'url' => '#', 'items' => [
            ['label' => 'CDRs', 'url' => ['/cdr/index']],
            ['label' => 'Providers', 'url' => ['/report/provider/index']],
            ['label' => 'Calls', 'items' => [
                ['label' => 'Billable Minutes', 'url' => ['/report/calls/mins']],
                ['label' => 'Concurrent Calls', 'url' => ['/report/calls/concurrent']],
            ]],
            ['label' => 'Financial', 'url' => ['/report/financial/index']],
            ['label' => 'First Data', 'url' => 'https://globalgatewaye4.firstdata.com/', 'linkOptions' => ['target' => '_blank']],
            ['label' => 'Pivot Test', 'url' => ['/report/pivot']],
            ['label' => 'Piwik Analytics', 'url' => 'https://piwik.clearvoipinc.com/index.php?module=Login&action=logme&login=' . $piwikLoginUser . '&password=' . $piwikLoginPass, 'linkOptions' => ['target' => '_blank']],
            ['label' => 'Checks & Money Orders', 'url' => ['/report/paper']],
            ['label' => 'Corrlinks Outbound', 'url' => ['/report/corrlinks/outbound']],
            ['label' => 'SMS Summary', 'url' => ['/report/sms']],
            ['label' => 'SMS Outbound Details', 'url' => ['/report/sms/outboundDetails']],
            ['label' => 'SMS Inbound Details', 'url' => ['/report/sms/inboundDetails']],
            ['label' => 'Facility Stats', 'url' => ['/facility/stats']],
            ['label' => 'HLR Results', 'url' => ['/hlr/results']],
        ]],
        ['label' => 'Admin', 'url' => '#', 'items' => [
            ['label' => 'Rate Centers', 'url' => ['/rateCenter/index']],
            ['label' => 'Providers', 'url' => ['/provider/index']],
            ['label' => 'Plans', 'url' => ['/plan/index']],
            ['label' => 'Messaging', 'url' => ['/messenger']],
            ['label' => 'Corrlinks', 'items' => [
                ['label' => 'Accounts', 'url' => ['/sms/account/index']],
                ['label' => 'Contacts', 'url' => ['/sms/account/contacts']],
            ]],
            ['label' => 'SMS Routing', 'url' => ['/sms/routing/index']],
            ['label' => 'Promotion', 'url' => ['/campaign/admin']],
            ['label' => 'App Accounts', 'url' => ['/appAccount/index']],
            ['label' => 'PBX Black/White Lists', 'url' => ['/pbx/lists']],
            ['label' => 'SMS Blacklist - Outbound', 'url' => ['/smsBlacklist/out']],
        ]],
        ['label' => 'Add Client', 'url' => ['/client/create']],
        [
            'label'   => YII_DEBUG
                ? '<span class="text-success">Debug On</span>'
                : '<span class="text-error">Debug Off</span>',
            'url'     => ['/site/debug', 'redirect' => Yii::$app->request->getUrl()],
            'visible' => false, /*Yii::app()->user->name === 'steve'*/ // TODO: Need replace Yii::app()->user->name
        ],
        [
            'label' => 'Show Calls? ' . Html::checkbox('showCalls', false, ['style' => 'margin:0;']),
            'url'   => false,
        ],
    ];
    $menuItemsRight = [
        // TODO: Need replace Yii::app()->user->checkAccess('User.*')
        // TODO: Need replace Yii::app()->getModule('user')->t("Users")
        [
            'label' => 'User Admin', 'url' => '#', 'visible' => true, /*Yii::app()->user->checkAccess('User.*'),*/
            'items' => [
                ['label' => 'root', /*Yii::app()->getModule('user')->t("Users"),*/
                 'url'   => ['/user/admin']],
                ['label' => 'Permissions', 'url' => ['/auth']],
            ],
        ],
        [
            'label'       => 123, //'Logout (' . Html::encode(Yii::$app->user->identity->nickname) . ')',
            'url'         => ['/site/logout'],
            'linkOptions' => ['data-method' => 'post'],
        ],
    ];
}

/** Display the NavBar */
NavBar::begin(['brandLabel' => Yii::$app->name, 'brandUrl' => Yii::$app->homeUrl, 'options' => ['class' => 'navbar-default navbar-fixed-top'], 'innerContainerOptions' => ['class' => 'container-fluid']]);

if (!empty($menuItemsLeft)) :
    echo NavX::widget(['options' => ['class' => 'navbar-nav navbar-left'], 'items' => $menuItemsLeft, 'activateParents' => true, 'encodeLabels' => false]);
endif;

if (!empty($menuItemsRight)) :
    echo NavX::widget(['options' => ['class' => 'navbar-nav navbar-right'], 'items' => $menuItemsRight, 'activateParents' => true, 'encodeLabels' => false]);
endif;

if (!Yii::$app->user->isGuest) :
    echo Html::beginForm('?', 'post', ['class' => 'navbar-form navbar-right']);
    echo Html::textInput('search', '', ['class' => 'form-control', 'placeholder' => 'Search']);
    echo Html::endForm();

    echo NavX::widget(['options' => ['class' => 'navbar-nav navbar-right'], 'items' => [['label' => '<i class="glyphicon glyphicon-question-sign" id="question-prefix"></i>', 'url' => false]], 'activateParents' => true, 'encodeLabels' => false]);
    echo "<div id='search-prefix'>
        <div class='arrow-up'></div>
        <div class='title'>Search Prefixes</div>
        <table class='table table-striped table-bordered table-condensed'>
            <tr><th>Prefix</th><th>Description</th></tr>
            <tr><td>No Prefix</td><td>Various</td></tr>
            <tr><td>8 Numbers</td><td>MG Ref</td></tr>
            <tr><td># or -</td><td>Client ID</td></tr>
            <tr><td>+</td><td>Phone Number</td></tr>
            <tr><td>cc</td><td>Credit card last 4 digits</td></tr>
            <tr><td>tx</td><td>Client Transaction ID (also ref num @ First Data)</td></tr>
            <tr><td>fi</td><td>Facility ID</td></tr>
            <tr><td>fn</td><td>Facility Name</td></tr>
            <tr><td>fs</td><td>Facility Street</td></tr>
            <tr><td>fc</td><td>Facility City</td></tr>
            <tr><td>fz</td><td>Facility Zip</td></tr>
            <tr><td>fp</td><td>Facility Phone</td></tr>
            <tr><td>ff</td><td>Facility Fax</td></tr>
            <tr><td>fa</td><td>Facility Additional Notes</td></tr>
            <tr><td></td><td>More to come...</td></tr>
        </table>
    </div>";
endif;

NavBar::end();
