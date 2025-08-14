<?php

return [
    'autoload' => false,
    'hooks' => [
        'epay_config_init' => [
            'epay',
        ],
        'addon_action_begin' => [
            'epay',
        ],
        'action_begin' => [
            'epay',
        ],
        'user_sidenav_after' => [
            'invite',
            'leescore',
            'recharge',
            'withdraw',
        ],
        'user_register_successed' => [
            'invite',
        ],
        'upgrade' => [
            'leescore',
        ],
        'app_init' => [
            'leescore',
            'qrcode',
        ],
        'leescorehook' => [
            'leescore',
        ],
        'leesignhook' => [
            'leesign',
        ],
        'config_init' => [
            'nkeditor',
        ],
        'do_upgrade' => [
            'tablemake',
        ],
    ],
    'route' => [
        '/invite/[:id]$' => 'invite/index/index',
        '/leescore/goods$' => 'leescore/goods/index',
        '/leescore/order$' => 'leescore/order/index',
        '/score$' => 'leescore/index/index',
        '/address$' => 'leescore/address/index',
        '/leesign$' => 'leesign/index/index',
        '/qrcode$' => 'qrcode/index/index',
        '/qrcode/build$' => 'qrcode/index/build',
    ],
    'priority' => [],
    'domain' => '',
];
