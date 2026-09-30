<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Api_controller.php';

class Auth_api extends Api_controller
{
    public function register()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('auth-register:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 60);
        $body = $this->api->body();
        $username = trim((string) ($body['username'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || strlen($username) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255 || strlen($password) < 8) {
            $this->api->respond_error('Enter a username, valid email, and password with at least 8 characters.', 422);
        }

        $this->call->database();
        $existing = $this->db->raw(
            'SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1',
            [$email, $username]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $this->api->respond_error('That email or username is already registered.', 409);
        }

        $this->db->raw(
            'INSERT INTO users (username, email, password) VALUES (?, ?, ?)',
            [$username, $email, password_hash($password, PASSWORD_DEFAULT)]
        );

        $user = $this->db->raw(
            'SELECT id, username, email, role FROM users WHERE email = ? LIMIT 1',
            [$email]
        )->fetch(PDO::FETCH_ASSOC);
        $tokens = $this->api->issue_tokens([
            'id' => $user['id'],
            'role' => $user['role'],
            'scopes' => ['read', 'write', 'delete'],
        ]);

        $this->api->respond(['message' => 'Account created.', 'user' => $user, 'tokens' => $tokens], 201);
    }

    public function login()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('auth-login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 60);
        $body = $this->api->body();
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        $this->call->database();
        $user = $this->db->raw(
            'SELECT id, username, email, password, role, is_active FROM users WHERE email = ? LIMIT 1',
            [$email]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user || !(int) $user['is_active'] || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid email or password.', 401);
        }

        $tokens = $this->api->issue_tokens([
            'id' => $user['id'],
            'role' => $user['role'],
            'scopes' => ['read', 'write', 'delete'],
        ]);
        unset($user['password'], $user['is_active']);

        $this->api->respond(['message' => 'Login successful.', 'user' => $user, 'tokens' => $tokens]);
    }

    public function refresh()
    {
        $this->api->require_method('POST');
        $body = $this->api->body();
        $refreshToken = (string) ($body['refresh_token'] ?? '');

        if ($refreshToken === '') {
            $this->api->respond_error('A refresh token is required.', 422);
        }

        $this->call->database();
        $this->api->refresh_access_token($refreshToken);
    }

    public function logout()
    {
        $this->api->require_method('POST');
        $this->authenticated_user();
        $body = $this->api->body();
        $refreshToken = (string) ($body['refresh_token'] ?? '');

        if ($refreshToken !== '') {
            $this->api->revoke_refresh_token($refreshToken);
        }

        $this->api->respond(['message' => 'Logged out.']);
    }
}