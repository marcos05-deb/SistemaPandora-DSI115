<?php

namespace App\Services\Auth;

use App\Models\Especialista;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;

    private int $ttlMinutes;

    private string $algorithm = 'HS256';

    public function __construct()
    {
        $key = config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }
        $this->secret = $key;
        $this->ttlMinutes = 480;
    }

    public function create(Especialista $user): string
    {
        $now = time();
        $payload = [
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + ($this->ttlMinutes * 60),
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    public function validate(string $token): ?int
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return (int) $payload->sub;
        } catch (\Exception) {
            return null;
        }
    }
}
