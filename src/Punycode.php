<?php

declare(strict_types=1);

namespace LayerShifter\TLDExtract;

use LayerShifter\TLDExtract\Exceptions\DomainOutOfBoundsException;
use LayerShifter\TLDExtract\Exceptions\LabelOutOfBoundsException;

/**
 * Punycode implementation as described in RFC 3492
 *
 * @link http://tools.ietf.org/html/rfc3492
 */
class Punycode
{
    /**
     * Bootstring parameter values
     */
    public const BASE = 36;
    public const TMIN = 1;
    public const TMAX = 26;
    public const SKEW = 38;
    public const DAMP = 700;
    public const INITIAL_BIAS = 72;
    public const INITIAL_N = 128;
    public const PREFIX = 'xn--';
    public const DELIMITER = '-';

    /**
     * Encode table
     */
    protected static array $encodeTable = [
        'a',
        'b',
        'c',
        'd',
        'e',
        'f',
        'g',
        'h',
        'i',
        'j',
        'k',
        'l',
        'm',
        'n',
        'o',
        'p',
        'q',
        'r',
        's',
        't',
        'u',
        'v',
        'w',
        'x',
        'y',
        'z',
        '0',
        '1',
        '2',
        '3',
        '4',
        '5',
        '6',
        '7',
        '8',
        '9',
    ];

    /**
     * Decode table
     */
    protected static array $decodeTable = [
        'a' => 0,
        'b' => 1,
        'c' => 2,
        'd' => 3,
        'e' => 4,
        'f' => 5,
        'g' => 6,
        'h' => 7,
        'i' => 8,
        'j' => 9,
        'k' => 10,
        'l' => 11,
        'm' => 12,
        'n' => 13,
        'o' => 14,
        'p' => 15,
        'q' => 16,
        'r' => 17,
        's' => 18,
        't' => 19,
        'u' => 20,
        'v' => 21,
        'w' => 22,
        'x' => 23,
        'y' => 24,
        'z' => 25,
        '0' => 26,
        '1' => 27,
        '2' => 28,
        '3' => 29,
        '4' => 30,
        '5' => 31,
        '6' => 32,
        '7' => 33,
        '8' => 34,
        '9' => 35
    ];

    public function __construct(
        protected string $encoding = 'UTF-8',
    ) {
    }

    /**
     * Encode a domain to its Punycode version
     *
     * @param string $input Domain name in Unicode to be encoded
     *
     * @return string Punycode representation in ASCII
     */
    public function encode(string $input): string
    {
        $input = mb_strtolower($input, $this->encoding);
        $parts = explode('.', $input);
        foreach ($parts as &$part) {
            $length = strlen($part);
            if ($length < 1) {
                throw new LabelOutOfBoundsException(
                    sprintf(
                        'The length of any one label is limited to between 1 and 63 octets, but %s given.',
                        $length,
                    )
                );
            }
            $part = $this->encodePart($part);
        }
        $output = implode('.', $parts);
        $length = strlen($output);
        if ($length > 255) {
            throw new DomainOutOfBoundsException(
                sprintf(
                    'A full domain name is limited to 255 octets (including the separators), %s given.',
                    $length,
                )
            );
        }

        return $output;
    }

    /**
     * Encode a part of a domain name, such as tld, to its Punycode version
     *
     * @param string $input Part of a domain name
     *
     * @return string Punycode representation of a domain part
     */
    protected function encodePart(string $input): string
    {
        $codePoints = $this->listCodePoints($input);

        $n = static::INITIAL_N;
        $bias = static::INITIAL_BIAS;
        $delta = 0;
        $h = $b = count($codePoints['basic']);

        $output = '';
        foreach ($codePoints['basic'] as $code) {
            $output .= $this->codePointToChar($code);
        }
        if ($input === $output) {
            return $output;
        }
        if ($b > 0) {
            $output .= static::DELIMITER;
        }

        $codePoints['nonBasic'] = array_unique($codePoints['nonBasic']);
        sort($codePoints['nonBasic']);

        $i = 0;
        $length = mb_strlen($input, $this->encoding);
        while ($h < $length) {
            $m = $codePoints['nonBasic'][$i++];
            $delta = $delta + ($m - $n) * ($h + 1);
            $n = $m;

            foreach ($codePoints['all'] as $c) {
                if ($c < $n || $c < static::INITIAL_N) {
                    $delta++;
                }
                if ($c === $n) {
                    $q = $delta;
                    for ($k = static::BASE; ; $k += static::BASE) {
                        $t = $this->calculateThreshold($k, $bias);
                        if ($q < $t) {
                            break;
                        }

                        $code = $t + ((int)($q - $t) % (static::BASE - $t));
                        $output .= static::$encodeTable[$code];

                        $q = ($q - $t) / (static::BASE - $t);
                    }

                    $output .= static::$encodeTable[(int)$q];
                    $bias = $this->adapt($delta, $h + 1, ($h === $b));
                    $delta = 0;
                    $h++;
                }
            }

            $delta++;
            $n++;
        }
        $out = static::PREFIX . $output;
        $length = strlen($out);
        if ($length > 63 || $length < 1) {
            throw new LabelOutOfBoundsException(
                sprintf(
                    'The length of any one label is limited to between 1 and 63 octets, but %s given.',
                    $length,
                )
            );
        }

        return $out;
    }

    /**
     * List code points for a given input
     *
     * @return array Multi-dimension array with basic, non-basic and aggregated code points
     */
    protected function listCodePoints(string $input): array
    {
        $codePoints = array(
            'all' => array(),
            'basic' => array(),
            'nonBasic' => array(),
        );

        $length = mb_strlen($input, $this->encoding);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($input, $i, 1, $this->encoding);
            $code = $this->charToCodePoint($char);
            if ($code < 128) {
                $codePoints['all'][] = $codePoints['basic'][] = $code;
            } else {
                $codePoints['all'][] = $codePoints['nonBasic'][] = $code;
            }
        }

        return $codePoints;
    }

    /**
     * Convert a single or multi-byte character to its code point
     */
    protected function charToCodePoint(string $char): int
    {
        $code = ord($char[0]);
        if ($code < 128) {
            return $code;
        } elseif ($code < 224) {
            return (($code - 192) * 64) + (ord($char[1]) - 128);
        } elseif ($code < 240) {
            return (($code - 224) * 4096) + ((ord($char[1]) - 128) * 64) + (ord($char[2]) - 128);
        } else {
            return (($code - 240) * 262144) + ((ord($char[1]) - 128) * 4096) + ((ord($char[2]) - 128) * 64) + (ord($char[3]) - 128);
        }
    }

    /**
     * Convert a code point to its single or multi-byte character
     *
     * @param integer $code
     *
     * @return string
     */
    protected function codePointToChar(int $code): string
    {
        if ($code <= 0x7F) {
            return chr($code);
        } elseif ($code <= 0x7FF) {
            return chr(($code >> 6) + 192) . chr(($code & 63) + 128);
        } elseif ($code <= 0xFFFF) {
            return chr(($code >> 12) + 224) . chr((($code >> 6) & 63) + 128) . chr(($code & 63) + 128);
        } else {
            return chr(($code >> 18) + 240) . chr((($code >> 12) & 63) + 128) . chr((($code >> 6) & 63) + 128) . chr(($code & 63) + 128);
        }
    }

    /**
     * Calculate the bias threshold to fall between TMIN and TMAX
     */
    protected function calculateThreshold(int $k, int $bias): int
    {
        if ($k <= $bias + static::TMIN) {
            return static::TMIN;
        }

        if ($k >= $bias + static::TMAX) {
            return static::TMAX;
        }

        return $k - $bias;
    }

    /**
     * Bias adaptation
     */
    protected function adapt(int $delta, int $numPoints, bool $firstTime): int
    {
        $delta = (int) (
        ($firstTime)
            ? $delta / static::DAMP
            : $delta / 2
        );
        $delta += (int) ($delta / $numPoints);

        $k = 0;
        while ($delta > ((static::BASE - static::TMIN) * static::TMAX) / 2) {
            $delta = (int) ($delta / (static::BASE - static::TMIN));
            $k += static::BASE;
        }
        $k += (int) (((static::BASE - static::TMIN + 1) * $delta) / ($delta + static::SKEW));

        return $k;
    }

    /**
     * Decode a Punycode domain name to its Unicode counterpart
     *
     * @param string $input Domain name in Punycode
     *
     * @return string Unicode domain name
     */
    public function decode(string $input): string
    {
        $input = strtolower($input);
        $parts = explode('.', $input);
        foreach ($parts as &$part) {
            $length = strlen($part);
            if ($length > 63 || $length < 1) {
                throw new LabelOutOfBoundsException(sprintf('The length of any one label is limited to between 1 and 63 octets, but %s given.',
                    $length));
            }
            if (!str_starts_with($part, static::PREFIX)) {
                continue;
            }

            $part = substr($part, strlen(static::PREFIX));
            $part = $this->decodePart($part);
        }
        $output = implode('.', $parts);
        $length = strlen($output);
        if ($length > 255) {
            throw new DomainOutOfBoundsException(sprintf('A full domain name is limited to 255 octets (including the separators), %s given.',
                $length));
        }

        return $output;
    }

    /**
     * Decode a part of domain name, such as tld
     *
     * @param string $input Part of a domain name
     *
     * @return string Unicode domain part
     */
    protected function decodePart(string $input): string
    {
        $n = static::INITIAL_N;
        $i = 0;
        $bias = static::INITIAL_BIAS;
        $output = '';

        $pos = strrpos($input, static::DELIMITER);
        if ($pos !== false) {
            $output = substr($input, 0, $pos++);
        } else {
            $pos = 0;
        }

        $outputLength = strlen($output);
        $inputLength = strlen($input);
        while ($pos < $inputLength) {
            $oldi = $i;
            $w = 1;

            for ($k = static::BASE; ; $k += static::BASE) {
                $digit = static::$decodeTable[$input[$pos++]];
                $i += ($digit * $w);
                $t = $this->calculateThreshold($k, $bias);

                if ($digit < $t) {
                    break;
                }

                $w *= (static::BASE - $t);
            }

            $bias = $this->adapt($i - $oldi, ++$outputLength, ($oldi === 0));
            $n += (int) ($i / $outputLength);
            $i %= ($outputLength);
            $output = mb_substr($output, 0, $i, $this->encoding) . $this->codePointToChar($n) . mb_substr($output, $i,
                    $outputLength - 1, $this->encoding);

            $i++;
        }

        return $output;
    }
}
