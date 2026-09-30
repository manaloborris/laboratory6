<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_controller extends Controller
{
    protected $api;

    public function before_action()
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->api = $this->call->library('api');
    }

    protected function authenticated_user()
    {
        $payload = $this->api->require_jwt();
        $this->call->database();
        $statement = $this->db->raw(
            'SELECT id, username, email, role, is_active FROM users WHERE id = ? LIMIT 1',
            [$payload['sub']]
        );
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user || !(int) $user['is_active']) {
            $this->api->respond_error('Unauthorized', 401);
        }

        return $user;
    }
}