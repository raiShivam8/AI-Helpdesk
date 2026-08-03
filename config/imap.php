<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IMAP default account
    |--------------------------------------------------------------------------
    */
    'default' => env('IMAP_DEFAULT_ACCOUNT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Default date format
    |--------------------------------------------------------------------------
    */
    'date_format' => 'd-M-Y',

    /*
    |--------------------------------------------------------------------------
    | Available IMAP accounts
    |--------------------------------------------------------------------------
    */
    'accounts' => [

        'default' => [
            'host'          => env('IMAP_HOST', 'imap.gmail.com'),
            'port'          => env('IMAP_PORT', 993),
            'protocol'      => env('IMAP_PROTOCOL', 'imap'),
            'encryption'    => env('IMAP_ENCRYPTION', 'ssl'),
            'validate_cert' => env('IMAP_VALIDATE_CERT', true),
            'username'      => env('IMAP_USERNAME', env('MAIL_USERNAME')),
            'password'      => env('IMAP_PASSWORD', env('MAIL_PASSWORD')),
            'authentication' => env('IMAP_AUTHENTICATION', null),
            'proxy'         => [
                'socket'          => null,
                'request_fulluri' => false,
                'username'        => null,
                'password'        => null,
            ],
            'timeout'    => 8,
            'extensions' => [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Available IMAP options
    |--------------------------------------------------------------------------
    */
    'options' => [
        'delimiter'      => '/',
        'fetch'          => \Webklex\PHPIMAP\IMAP::FT_PEEK,
        'sequence'       => \Webklex\PHPIMAP\IMAP::ST_UID,
        'fetch_body'     => true,
        'fetch_flags'    => true,
        'soft_fail'      => false,
        'rfc822'         => true,
        'debug'          => false,
        'uid_cache'      => true,
        'boundary'       => '/boundary=(.*?(?=;)|(.*))/i',
        'message_key'    => 'list',
        'fetch_order'    => 'asc',
        'dispositions'   => ['attachment', 'inline'],
        'common_folders' => [
            'root'  => 'INBOX',
            'junk'  => 'INBOX/Junk',
            'draft' => 'INBOX/Drafts',
            'sent'  => 'INBOX/Sent',
            'trash' => 'INBOX/Trash',
        ],
        'open'           => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Available decoding options
    |--------------------------------------------------------------------------
    */
    'decoding' => [
        'options' => [
            'header'     => 'utf-8',
            'message'    => 'utf-8',
            'attachment' => 'utf-8',
        ],
        'decoder' => [
            'header'     => \Webklex\PHPIMAP\Decoder\HeaderDecoder::class,
            'message'    => \Webklex\PHPIMAP\Decoder\MessageDecoder::class,
            'attachment' => \Webklex\PHPIMAP\Decoder\AttachmentDecoder::class,
        ],
    ],

    'flags' => ['recent', 'flagged', 'answered', 'deleted', 'seen', 'draft'],

    'events' => [
        'message' => [
            'new'      => \Webklex\IMAP\Events\MessageNewEvent::class,
            'moved'    => \Webklex\IMAP\Events\MessageMovedEvent::class,
            'copied'   => \Webklex\IMAP\Events\MessageCopiedEvent::class,
            'deleted'  => \Webklex\IMAP\Events\MessageDeletedEvent::class,
            'restored' => \Webklex\IMAP\Events\MessageRestoredEvent::class,
        ],
        'folder' => [
            'new'     => \Webklex\IMAP\Events\FolderNewEvent::class,
            'moved'   => \Webklex\IMAP\Events\FolderMovedEvent::class,
            'deleted' => \Webklex\IMAP\Events\FolderDeletedEvent::class,
        ],
        'flag' => [
            'new'     => \Webklex\IMAP\Events\FlagNewEvent::class,
            'deleted' => \Webklex\IMAP\Events\FlagDeletedEvent::class,
        ],
    ],

    'masks' => [
        'message'    => \Webklex\PHPIMAP\Support\Masks\MessageMask::class,
        'attachment' => \Webklex\PHPIMAP\Support\Masks\AttachmentMask::class,
    ],
];
