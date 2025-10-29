<?php
// Discord OAuth2 Configuration
return [
    'client_id' => '1228027604687261736', // Discord application client ID
    'client_secret' => 'SECRET SHIT', // Discord application client secret
    'redirect_uri' => 'https://amz.odjezdy.online/duk/discord_register.php', // Adjust based on your setup
    'api_url' => 'https://discord.com/api/v10',
    'scopes' => ['identify', 'guilds.join', 'guilds', 'guilds.members.read'], // Required scopes for the application
];