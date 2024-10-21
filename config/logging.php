<?php

return [
  'channels' => [
    'stack' => [
      'driver' => 'stack',
      'channels' => ['slack'],
      'ignore_exceptions' => false,
    ],
    
    'slack' => [
      'driver' => 'slack',
      'url' => env('LOG_SLACK_WEBHOOK_URL'),
      'username' => env('LOG_SLACK_USERNAME', 'scrapper'),
      'level' => env('LOG_LEVEL', 'info'),
      'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
      'replace_placeholders' => true,
    ],
  ],
];
