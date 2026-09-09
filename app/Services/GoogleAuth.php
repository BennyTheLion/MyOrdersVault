<?php
namespace MyOrdersVault\Services;

use Google\Client;
use Google\Service\Oauth2;
use MyOrdersVault\Models\User;

class GoogleAuth {
    private $client;
    private $userModel;
    
    public function __construct() {
        $config = require __DIR__ . '/../../../config/config.php';
        
        $this->client = new Client();
        $this->client->setClientId($config['google']['client_id']);
        $this->client->setClientSecret($config['google']['client_secret']);
        $this->client->setRedirectUri($config['google']['redirect_uri']);
        $this->client->addScope($config['google']['scopes']);
        $this->client->setAccessType($config['google']['access_type']);
        $this->client->setPrompt($config['google']['prompt']);
        $this->client->setIncludeGrantedScopes(true);
        
        $this->userModel = new User();
    }
    
    public function getAuthUrl() {
        return $this->client->createAuthUrl();
    }
    
    public function authenticate($code) {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        
        if (isset($token['error'])) {
            throw new \Exception('Authentication failed: ' . $token['error']);
        }
        
        $this->client->setAccessToken($token);
        
        $oauth2 = new Oauth2($this->client);
        $userInfo = $oauth2->userinfo->get();
        
        $expiresAt = date('Y-m-d H:i:s', time() + $token['expires_in']);
        
        $googleUser = [
            'id' => $userInfo->getId(),
            'email' => $userInfo->getEmail(),
            'name' => $userInfo->getName(),
            'picture' => $userInfo->getPicture(),
            'access_token' => $token['access_token'],
            'refresh_token' => isset($token['refresh_token']) ? $token['refresh_token'] : null,
            'token_expires_at' => $expiresAt
        ];
        
        $userId = $this->userModel->findOrCreate($googleUser);
        
        return [
            'user_id' => $userId,
            'access_token' => $token['access_token'],
            'refresh_token' => isset($token['refresh_token']) ? $token['refresh_token'] : null,
            'expires_at' => $expiresAt,
            'name' => $userInfo->getName(),
            'email' => $userInfo->getEmail(),
            'picture' => $userInfo->getPicture()
        ];
    }
    
    public function refreshToken($refreshToken) {
        $this->client->refreshToken($refreshToken);
        $newToken = $this->client->getAccessToken();
        
        return [
            'access_token' => $newToken['access_token'],
            'expires_at' => date('Y-m-d H:i:s', time() + $newToken['expires_in'])
        ];
    }
    
    public function revokeToken($accessToken) {
        $this->client->revokeToken($accessToken);
    }
}
