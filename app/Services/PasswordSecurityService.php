<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class PasswordSecurityService
{
    public const SAFE = 'safe';
    public const COMPROMISED = 'compromised';
    public const UNCHECKED = 'unchecked';

    public function assess(string $password): string
    {
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        try {
            $response = Http::accept('text/plain')
                ->withHeaders(['Add-Padding' => 'true'])
                ->timeout(4)
                ->get("https://api.pwnedpasswords.com/range/{$prefix}");

            if (! $response->successful()) {
                return self::UNCHECKED;
            }

            foreach (preg_split('/\r\n|\r|\n/', $response->body()) as $line) {
                [$candidate] = array_pad(explode(':', trim($line), 2), 2, null);

                if ($candidate !== null && hash_equals($suffix, strtoupper($candidate))) {
                    return self::COMPROMISED;
                }
            }

            return self::SAFE;
        } catch (Throwable) {
            return self::UNCHECKED;
        }
    }

    public function response(string $status): array
    {
        return [
            'status' => $status,
            'warning' => match ($status) {
                self::COMPROMISED => 'This password has appeared in a known data breach. Your password was saved, but we recommend changing it from your profile.',
                self::UNCHECKED => 'Your password was saved, but we could not verify whether it has appeared in a data breach. We recommend changing or rechecking it later.',
                default => null,
            },
        ];
    }
}
