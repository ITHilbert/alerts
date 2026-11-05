<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Erfassung;

use Illuminate\Http\Request;

/** IP und User-Agent einer Anfrage, aufbereitet für die Details einer Meldung. */
final readonly class Herkunft
{
    private const UNBEKANNT = 'unbekannt';

    private const MAX_USER_AGENT = 200;

    private function __construct(
        public ?string $ip,
        private ?string $userAgent,
        private bool $kuerzen,
    ) {}

    public static function aus(Request $request, bool $ipKuerzen): self
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        return new self(
            is_string($ip) && $ip !== '' ? $ip : null,
            is_string($userAgent) && $userAgent !== '' ? $userAgent : null,
            $ipKuerzen,
        );
    }

    /** @return array{IP: string, User-Agent: string} */
    public function details(): array
    {
        return [
            'IP' => $this->angezeigteIp(),
            'User-Agent' => $this->angezeigterUserAgent(),
        ];
    }

    private function angezeigteIp(): string
    {
        if ($this->ip === null) {
            return self::UNBEKANNT;
        }

        return $this->kuerzen ? (self::gekuerzt($this->ip) ?? self::UNBEKANNT) : $this->ip;
    }

    private function angezeigterUserAgent(): string
    {
        if ($this->userAgent === null) {
            return self::UNBEKANNT;
        }

        return mb_strlen($this->userAgent) > self::MAX_USER_AGENT
            ? mb_substr($this->userAgent, 0, self::MAX_USER_AGENT - 1).'…'
            : $this->userAgent;
    }

    /** IPv4: letzter Block 0; IPv6: die ersten 64 Bit, Rest null. null, wenn keine gültige IP. */
    private static function gekuerzt(string $ip): ?string
    {
        $binaer = inet_pton($ip);

        if ($binaer === false) {
            return null;
        }

        $behalten = strlen($binaer) === 4 ? 3 : 8;
        $gekuerzt = inet_ntop(substr($binaer, 0, $behalten).str_repeat("\0", strlen($binaer) - $behalten));

        return $gekuerzt === false ? null : $gekuerzt;
    }
}
