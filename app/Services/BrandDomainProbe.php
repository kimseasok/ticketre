<?php

namespace App\Services;

use Illuminate\Support\Str;

class BrandDomainProbe
{
    /**
     * @return array{verified: bool, records: array<int, array<string, mixed>>, error: string|null}
     */
    public function checkDns(string $domain): array
    {
        $records = [];
        $verified = false;
        $error = null;
        $expected = strtolower((string) config('branding.verification.expected_cname'));

        if (function_exists('dns_get_record')) {
            try {
                $result = dns_get_record($domain, DNS_CNAME);
                foreach ($result as $record) {
                    $records[] = [
                        'type' => $record['type'] ?? 'CNAME',
                        'target' => strtolower((string) ($record['target'] ?? '')),
                    ];
                }

                foreach ($records as $record) {
                    if (($record['target'] ?? null) === $expected) {
                        $verified = true;
                        break;
                    }
                }

                if (! $verified) {
                    $error = 'expected_cname_not_found';
                }
            } catch (\Throwable $exception) {
                $error = 'dns_lookup_failed';
            }
        } else {
            $error = 'dns_lookup_unavailable';
        }

        return [
            'verified' => $verified,
            'records' => $records,
            'error' => $verified ? null : $error,
        ];
    }

    /**
     * @return array{verified: bool, issuer: string|null, error: string|null}
     */
    public function checkSsl(string $domain): array
    {
        $allowedSuffixes = (array) config('branding.verification.allowed_suffixes', []);
        $issuer = null;
        $verified = false;

        foreach ($allowedSuffixes as $suffix) {
            $suffix = trim($suffix);
            if ($suffix === '') {
                continue;
            }

            if (Str::endsWith($domain, $suffix)) {
                $verified = true;
                $issuer = config('branding.verification.ssl_authority');
                break;
            }
        }

        return [
            'verified' => $verified,
            'issuer' => $issuer,
            'error' => $verified ? null : 'ssl_certificate_unavailable',
        ];
    }
}
