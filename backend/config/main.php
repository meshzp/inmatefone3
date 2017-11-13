<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-backend',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'backend\controllers',
    'bootstrap' => ['log'],
    'modules' => [
        'rbacadmin' => [
            'class'         => 'dieruckus\rbacadmin\Module',
            'allowToAll'    => [
                '/site/index',
            ],
            'allowToLogged' => [
                '/site/logout',
                '/site/#',
            ],
            'allowToGuest'  => [
                '/site/login',
                '/rbacadmin/auth/two-factor',
                '/rbacadmin/auth/two-factor-recovery',
            ],
        ],
        // Connectivity this module
        'tickets' => [
            'class' => 'vityachis\tickets\Module',
            'defaultDirDownload' => '@backend/web/ticket_attached_files',
        ],
        // To work properly a \kartik\grid\GridView
        'gridview' => [
            'class' => 'kartik\grid\Module',
        ],
    ],
    'components'          => [
        'authManager'  => [
            'class' => 'dieruckus\rbacadmin\components\rbac\PermManager',
        ],
        'user'         => [
            'identityClass'   => 'dieruckus\rbacadmin\models\AdminUser',
            'identityCookie'  => ['name' => '_aidentity', 'httpOnly' => true],
            'enableAutoLogin' => true,
        ],
        'settings'     => [
            'class' => 'dieruckus\rbacadmin\components\Settings',
        ],
        'request'      => [
            'csrfParam' => '_csrf-backend',
        ],
        'session'      => [
            // this is the name of the session cookie used for login on the backend
            'name' => 'advanced-backend',
        ],
        'log'          => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets'    => [
                [
                    'class'  => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        /*
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
        */
    ],
    'params'              => $params,
    'as access'           => [
        'class' => 'dieruckus\rbacadmin\components\rbac\PermAccessControl',
    ],
];
