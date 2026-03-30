<?php

return [
    'processing' => 'Processing your request...',
    'executing' => [
        'Let me analyze that for you...',
        'Working on it...',
        'Just a moment...',
        'Give me a moment to work on this...',
        'I\'m on it...',
        'Let me check that for you...',
        'Analyzing the request...',
        'Processing your command...',
        'Working through this now...',
        'One moment please...',
    ],
    'project' => [
        'not_found' => 'Could not identify the project. Please be more specific.',
        'no_projects' => "You don't have any projects yet. Would you like to create one? Send path to code base to create a new project.",
    ],
    'cli_error' => 'Error executing CLI command: :error',
    'cli_success' => 'Task completed successfully!',
    'processing_error' => 'An error occurred while processing your request.',

    'auth' => [
        'unauthorized' => 'Unauthorized access.',
        'invalid_key' => 'Invalid API key.',
    ],

    'webhook' => [
        'accepted' => 'Request accepted for processing.',
    ],

    'project_status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'archived' => 'Archived',
    ],

    'channel_type' => [
        'telegram' => 'Telegram',
        'web' => 'Web',
        'whatsapp' => 'WhatsApp',
    ],

    'linear' => [
        'status' => [
            'backlog' => 'Backlog',
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'in_review' => 'In Review',
            'done' => 'Done',
            'canceled' => 'Canceled',
        ],
        'webhook' => [
            'received' => '📋 Linear issue received: :title',
            'completed' => '✅ Issue resolved: :title',
            'error' => '❌ Failed to process Linear issue: :error',
        ],
        'issue' => [
            'title' => 'Issue',
            'description' => 'Description',
        ],
    ],

    'nightwatch' => [
        'webhook' => [
            'received' => '🐛 Nightwatch exception detected: :title',
            'completed' => '✅ Exception handled: :title',
            'error' => '❌ Failed to process Nightwatch issue: :error',
            'resolved' => '✅ Issue resolved: :title',
            'reopened' => '🔔 Issue reopened: :title',
            'ignored' => '➖ Issue ignored: :title',
            'ignored_opened' => '🔍 Issue detected but ignored (type: :type): :title',
            'event' => '📬 Nightwatch event (:event): :title',
        ],
    ],

    'validation' => [
        'path_exists' => "A project named ':name' already exists at this path.",
    ],
];
