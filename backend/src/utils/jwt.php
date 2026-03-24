<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTUtil {
    public static function encode($payload) {
        $secret = env('JWT_SECRET');
        if (!$secret) {
            throw new RuntimeException('JWT secret is not configured');
        }
        $expiry = env('JWT_EXPIRES_IN', 3600);
        $payload['exp'] = time() + $expiry;
        return JWT::encode($payload, $secret, 'HS256');
    }
    
    public static function decode($token) {
        $secret = env('JWT_SECRET');
        if (!$secret) {
            throw new RuntimeException('JWT secret is not configured');
        }
        return JWT::decode($token, new Key($secret, 'HS256'));
    }
}
