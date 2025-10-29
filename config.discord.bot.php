<?php
// Discord Bot Configuration
return [
    'bot_token' => '', // Discord bot token
    'guild_id' => '', // odjezdy.online server ID
    'contributor_role_id' => '', // ID of the "Přispěvatel dat o DÚK vozech" role
    'api_version' => '10', // Discord API version
    'bot_permissions' => [
        'MANAGE_ROLES', // Permission to assign roles
        'VIEW_CHANNEL', // Permission to view channels
        'SEND_MESSAGES' // Permission to send messages
    ],
    // Endpoints
    'endpoints' => [
        'add_guild_member_role' => 'https://discord.com/api/v10/guilds/{guild_id}/members/{user_id}/roles/{role_id}',
        'get_guild_member' => 'https://discord.com/api/v10/guilds/{guild_id}/members/{user_id}'
    ]
];