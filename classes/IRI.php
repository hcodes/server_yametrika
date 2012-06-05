<?php

/**
 * IRI parser/serialiser/normaliser
 *
 * Copyright (c) 2007-2009, Geoffrey Sneddon and Steve Minutillo.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *  * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *
 *  * Neither the name of the SimplePie Team nor the names of its contributors
 *       may be used to endorse or promote products derived from this software
 *       without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package IRI
 * @author Geoffrey Sneddon
 * @author Steve Minutillo
 * @copyright 2007-2009 Geoffrey Sneddon and Steve Minutillo
 * @license http://www.opensource.org/licenses/bsd-license.php
 * @link http://hg.gsnedders.com/iri/
 *
 * @todo Per-scheme validation
 */
class IRI
{
    /**
     * Scheme
     *
     * @var string
     */
    private $scheme;

    /**
     * User Information
     *
     * @var string
     */
    private $iuserinfo;

    /**
     * ihost
     *
     * @var string
     */
    private $ihost;

    /**
     * Port
     *
     * @var string
     */
    private $port;

    /**
     * ipath
     *
     * @var string
     */
    private $ipath;

    /**
     * iquery
     *
     * @var string
     */
    private $iquery;

    /**
     * ifragment
     *
     * @var string
     */
    private $ifragment;
    
    /**
     * Normalization database
     *
     * Each key is the scheme, each value is an array with each key as the IRI
     * part and value as the default value for that part.
     */
    private $normalization = array(
        'acap' => array(
            'port' => 674
        ),
        'dict' => array(
            'port' => 2628
        ),
        'file' => array(
            'ihost' => 'localhost'
        ),
        'http' => array(
            'port' => 80,
            'ipath' => '/'
        ),
        'https' => array(
            'port' => 443,
            'ipath' => '/'
        ),
    );

    /**
     * Return the entire IRI when you try and read the object as a string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->iri;
    }

    /**
     * Overload __set() to provide access via properties
     *
     * @param string $name Property name
     * @param mixed $value Property value
     * @return void
     */
    public function __set($name, $value)
    {
        if (method_exists($this, 'set_' . $name))
        {
            call_user_func(array($this, 'set_' . $name), $value);
        }
        elseif (
               $name === 'iauthority'
            || $name === 'iuserinfo'
            || $name === 'ihost'
            || $name === 'ipath'
            || $name === 'iquery'
            || $name === 'ifragment'
        )
        {
            call_user_func(array($this, 'set_' . substr($name, 1)), $value);
        }
    }

    /**
     * Overload __get() to provide access via properties
     *
     * @param string $name Property name
     * @return mixed
     */
    public function __get($name)
    {
        if (!$this->is_valid())
        {
            return false;
        }
        elseif (method_exists($this, 'get_' . $name))
        {
            $return = call_user_func(array($this, 'get_' . $name));
        }
        elseif (isset($this->$name))
        {
            $return = $this->$name;
        }
        else
        {
            trigger_error('Undefined property: ' . get_class($this) . '::' . $name, E_USER_NOTICE);
            $return = null;
        }
        
        if ($return === null && isset($this->normalization[$this->scheme][$name]))
        {
            return $this->normalization[$this->scheme][$name];
        }
        else
        {
            return $return;
        }
    }

    /**
     * Overload __isset() to provide access via properties
     *
     * @param string $name Property name
     * @return bool
     */
    public function __isset($name)
    {
        if (method_exists($this, 'get_' . $name) || isset($this->$name))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Overload __unset() to provide access via properties
     *
     * @param string $name Property name
     * @param mixed $value Property value
     * @return void
     */
    public function __unset($name)
    {
        if (method_exists($this, 'set_' . $name))
        {
            call_user_func(array($this, 'set_' . $name), '');
        }
    }

    /**
     * Create a new IRI object, from a specified string
     *
     * @param string $iri
     * @return IRI
     */
    public function __construct($iri = null)
    {
        $this->set_iri($iri);
    }

    /**
     * Create a new IRI object by resolving a relative IRI
     *
     * Returns false if $base is not absolute, otherwise an IRI.
     *
     * @param IRI $base (Absolute) Base IRI
     * @param IRI|string $relative Relative IRI
     * @return IRI|false
     */
    public static function absolutize(IRI $base, $relative)
    {
        if (!($relative instanceof IRI))
        {
            $relative = new IRI($relative);
        }
        if ($base->scheme !== null)
        {
            if ($relative->iri !== '' && $relative->iri !== null)
            {
                if ($relative->scheme !== null)
                {
                    $target = clone $relative;
                }
                elseif ($relative->iauthority !== null)
                {
                    $target = clone $relative;
                    $target->scheme = $base->scheme;
                }
                else
                {
                    $target = new IRI;
                    $target->scheme = $base->scheme;
                    $target->iuserinfo = $base->iuserinfo;
                    $target->ihost = $base->ihost;
                    $target->port = $base->port;
                    if ($relative->ipath !== '')
                    {
                        if ($relative->ipath[0] === '/')
                        {
                            $target->ipath = $relative->ipath;
                        }
                        elseif (($base->iuserinfo !== null || $base->ihost !== null || $base->port !== null) && $base->ipath === null)
                        {
                            $target->ipath = '/' . $relative->ipath;
                        }
                        elseif (($last_segment = strrpos($base->ipath, '/')) !== false)
                        {
                            $target->ipath = substr($base->ipath, 0, $last_segment + 1) . $relative->ipath;
                        }
                        else
                        {
                            $target->ipath = $relative->ipath;
                        }
                        $target->ipath = $target->remove_dot_segments($target->ipath);
                        $target->iquery = $relative->iquery;
                    }
                    else
                    {
                        $target->ipath = $base->ipath;
                        if ($relative->iquery !== null)
                        {
                            $target->iquery = $relative->iquery;
                        }
                        elseif ($base->iquery !== null)
                        {
                            $target->iquery = $base->iquery;
                        }
                    }
                    $target->ifragment = $relative->ifragment;
                }
            }
            else
            {
                $target = clone $base;
                $target->ifragment = null;
            }
            $target->scheme_normalization();
            return $target;
        }
        else
        {
            return false;
        }
    }

    /**
     * Create a new IRI object by creating a relative IRI from two IRIs
     *
     * @param IRI $base Base IRI
     * @param IRI $destination Destination IRI
     * @return IRI
     */
    public static function build_relative(IRI $base, IRI $destination)
    {
    }

    /**
     * Parse an IRI into scheme/authority/path/query/fragment segments
     *
     * @param string $iri
     * @return array
     */
    private function parse_iri($iri)
    {
        $iri = trim($iri, "\x20\x09\x0A\x0C\x0D");
        static $cache = array();
        if (isset($cache[$iri]))
        {
            return $cache[$iri];
        }
        elseif ($iri === '')
        {
            return $cache[$iri] = array(
                'scheme' => null,
                'authority' => null,
                'path' => '',
                'query' => null,
                'fragment' => null
            );
        }
        elseif (preg_match('/^((?P<scheme>[^:\/?#]+):)?(\/\/(?P<authority>[^\/?#]*))?(?P<path>[^?#]*)(\?(?P<query>[^#]*))?(#(?P<fragment>.*))?$/', $iri, $match))
        {
            if (!isset($match[1]) || $match[1] === '')
            {
                $match['scheme'] = null;
            }
            if (!isset($match[3]) || $match[3] === '')
            {
                $match['authority'] = null;
            }
            if (!isset($match[5]) || $match[5] === '')
            {
                $match['path'] = '';
            }
            if (!isset($match[6]) || $match[6] === '')
            {
                $match['query'] = null;
            }
            if (!isset($match[8]) || $match[8] === '')
            {
                $match['fragment'] = null;
            }
            return $cache[$iri] = $match;
        }
    }

    /**
     * Remove dot segments from a path
     *
     * @param string $input
     * @return string
     */
    private function remove_dot_segments($input)
    {
        $output = '';
        while (strpos($input, './') !== false || strpos($input, '/.') !== false || $input === '.' || $input === '..')
        {
            // A: If the input buffer begins with a prefix of "../" or "./", then remove that prefix from the input buffer; otherwise,
            if (strpos($input, '../') === 0)
            {
                $input = substr($input, 3);
            }
            elseif (strpos($input, './') === 0)
            {
                $input = substr($input, 2);
            }
            // B: if the input buffer begins with a prefix of "/./" or "/.", where "." is a complete path segment, then replace that prefix with "/" in the input buffer; otherwise,
            elseif (strpos($input, '/./') === 0)
            {
                $input = substr_replace($input, '/', 0, 3);
            }
            elseif ($input === '/.')
            {
                $input = '/';
            }
            // C: if the input buffer begins with a prefix of "/../" or "/..", where ".." is a complete path segment, then replace that prefix with "/" in the input buffer and remove the last segment and its preceding "/" (if any) from the output buffer; otherwise,
            elseif (strpos($input, '/../') === 0)
            {
                $input = substr_replace($input, '/', 0, 4);
                $output = substr_replace($output, '', strrpos($output, '/'));
            }
            elseif ($input === '/..')
            {
                $input = '/';
                $output = substr_replace($output, '', strrpos($output, '/'));
            }
            // D: if the input buffer consists only of "." or "..", then remove that from the input buffer; otherwise,
            elseif ($input === '.' || $input === '..')
            {
                $input = '';
            }
            // E: move the first path segment in the input buffer to the end of the output buffer, including the initial "/" character (if any) and any subsequent characters up to, but not including, the next "/" character or the end of the input buffer
            elseif (($pos = strpos($input, '/', 1)) !== false)
            {
                $output .= substr($input, 0, $pos);
                $input = substr_replace($input, '', 0, $pos);
            }
            else
            {
                $output .= $input;
                $input = '';
            }
        }
        return $output . $input;
    }

    /**
     * Replace invalid character with percent encoding
     *
     * @param string $string Input string
     * @param string $extra_chars Valid characters not in iunreserved or
     *                            iprivate (this is ASCII-only)
     * @param bool $iprivate Allow iprivate
     * @return string
     */
    private function replace_invalid_with_pct_encoding($string, $extra_chars, $iprivate = false)
    {
        // Replace invalid percent characters
        $string = preg_replace('/%($|[^A-Fa-f0-9]|[A-Fa-f0-9][^A-Fa-f0-9])/', '%25\1', $string);
        
        // Normalize as many pct-encoded sections as possible
        $string = preg_replace_callback('/(?:%[A-Fa-f0-9]{2})+/', array(&$this, 'remove_iunreserved_percent_encoded'), $string);
        
        // Add unreserved and % to $extra_chars (the latter is safe because all
        // pct-encoded sections are now valid).
        $extra_chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~%';
        
        // Now replace any bytes that aren't allowed with their pct-encoded versions
        $position = 0;
        $strlen = strlen($string);
        while (($position += strspn($string, $extra_chars, $position)) < $strlen)
        {
            $value = ord($string[$position]);
            
            // Start position
            $start = $position;
        
            // By default we are valid
            $valid = true;
            
            // No one byte sequences are valid due to the while.
            // Two byte sequence:
            if (($value & 0xE0) === 0xC0)
            {
                $character = ($value & 0x1F) << 6;
                $length = 2;
                $remaining = 1;
            }
            // Three byte sequence:
            elseif (($value & 0xF0) === 0xE0)
            {
                $character = ($value & 0x0F) << 12;
                $length = 3;
                $remaining = 2;
            }
            // Four byte sequence:
            elseif (($value & 0xF8) === 0xF0)
            {
                $character = ($value & 0x07) << 18;
                $length = 4;
                $remaining = 3;
            }
            // Invalid byte:
            else
            {
                $valid = false;
                $length = 1;
                $remaining = 0;
            }
            
            if ($remaining)
            {
                if ($position + $length <= $strlen)
                {
                    for ($position++; $remaining; $position++)
                    {
                        $value = ord($string[$position]);
                        
                        // Check that the byte is valid, then add it to the character:
                        if (($value & 0xC0) === 0x80)
                        {
                            $character |= ($value & 0x3F) << (--$remaining * 6);
                        }
                        // If it is invalid, count the sequence as invalid and reprocess the current byte:
                        else
                        {
                            $valid = false;
                            $position--;
                            break;
                        }
                    }
                }
                else
                {
                    $position = $strlen - 1;
                    $valid = false;
                }
            }
                
            // Percent encode anything invalid or not in ucschar
            if (
                // Invalid sequences
                !$valid
                // Non-shortest form sequences are invalid
                || $length > 1 && $character <= 0x7F
                || $length > 2 && $character <= 0x7FF
                || $length > 3 && $character <= 0xFFFF
                // Outside of range of ucschar codepoints
                // Noncharacters
                || ($character & 0xFFFE) === 0xFFFE
                || $character >= 0xFDD0 && $character <= 0xFDEF
                || (
                    // Everything else not in ucschar
                       $character > 0xD7FF && $character < 0xF900
                    || $character < 0xA0
                    || $character > 0xEFFFD
                )
                && (
                    // Everything not in iprivate, if it applies
                       !$iprivate
                    || $character < 0xE000
                    || $character > 0x10FFFD
                )
            )
            {
                // If we were a character, pretend we weren't, but rather an error.
                if ($valid)
                    $position--;
                    
                for ($j = $start; $j <= $position; $j++)
                {
                    $string = substr_replace($string, sprintf('%%%02X', ord($string[$j])), $j, 1);
                    $j += 2;
                    $position += 2;
                    $strlen += 2;
                }
            }
        }
        
        return $string;
    }

    /**
     * Callback function for preg_replace_callback.
     *
     * Removes sequences of percent encoded bytes that represent UTF-8
     * encoded characters in iunreserved
     *
     * @param array $match PCRE match
     * @return string Replacement
     */
    private function remove_iunreserved_percent_encoded($match)
    {
        // As we just have valid percent encoded sequences we can just explode
        // and ignore the first member of the returned array (an empty string).
        $bytes = explode('%', $match[0]);
        
        // Initialize the new string (this is what will be returned) and that
        // there are no bytes remaining in the current sequence (unsurprising
        // at the first byte!).
        $string = '';
        $remaining = 0;
        
        // Loop over each and every byte, and set $value to its value
        for ($i = 1, $len = count($bytes); $i < $len; $i++)
        {
            $value = hexdec($bytes[$i]);
            
            // If we're the first byte of sequence:
            if (!$remaining)
            {
                // Start position
                $start = $i;
                
                // By default we are valid
                $valid = true;
                
                // One byte sequence:
                if ($value <= 0x7F)
                {
                    $character = $value;
                    $length = 1;
                }
                // Two byte sequence:
                elseif (($value & 0xE0) === 0xC0)
                {
                    $character = ($value & 0x1F) << 6;
                    $length = 2;
                    $remaining = 1;
                }
                // Three byte sequence:
                elseif (($value & 0xF0) === 0xE0)
                {
                    $character = ($value & 0x0F) << 12;
                    $length = 3;
                    $remaining = 2;
                }
                // Four byte sequence:
                elseif (($value & 0xF8) === 0xF0)
                {
                    $character = ($value & 0x07) << 18;
                    $length = 4;
                    $remaining = 3;
                }
                // Invalid byte:
                else
                {
                    $valid = false;
                    $remaining = 0;
                }
            }
            // Continuation byte:
            else
            {
                // Check that the byte is valid, then add it to the character:
                if (($value & 0xC0) === 0x80)
                {
                    $remaining--;
                    $character |= ($value & 0x3F) << ($remaining * 6);
                }
                // If it is invalid, count the sequence as invalid and reprocess the current byte as the start of a sequence:
                else
                {
                    $valid = false;
                    $remaining = 0;
                    $i--;
                }
            }
            
            // If we've reached the end of the current byte sequence, append it to Unicode::$data
            if (!$remaining)
            {
                // Percent encode anything invalid or not in iunreserved
                if (
                    // Invalid sequences
                    !$valid
                    // Non-shortest form sequences are invalid
                    || $length > 1 && $character <= 0x7F
                    || $length > 2 && $character <= 0x7FF
                    || $length > 3 && $character <= 0xFFFF
                    // Outside of range of iunreserved codepoints
                    || $character < 0x2D
                    || $character > 0xEFFFD
                    // Noncharacters
                    || ($character & 0xFFFE) === 0xFFFE
                    || $character >= 0xFDD0 && $character <= 0xFDEF
                    // Everything else not in iunreserved (this is all BMP)
                    || $character === 0x2F
                    || $character > 0x39 && $character < 0x41
                    || $character > 0x5A && $character < 0x61
                    || $character > 0x7A && $character < 0x7E
                    || $character > 0x7E && $character < 0xA0
                    || $character > 0xD7FF && $character < 0xF900
                )
                {
                    for ($j = $start; $j <= $i; $j++)
                    {
                        $string .= '%' . strtoupper($bytes[$j]);
                    }
                }
                else
                {
                    for ($j = $start; $j <= $i; $j++)
                    {
                        $string .= chr(hexdec($bytes[$j]));
                    }
                }
            }
        }
        
        // If we have any bytes left over they are invalid (i.e., we are
        // mid-way through a multi-byte sequence)
        if ($remaining)
        {
            for ($j = $start; $j < $len; $j++)
            {
                $string .= '%' . strtoupper($bytes[$j]);
            }
        }
        
        return $string;
    }
    
    private function scheme_normalization()
    {
        if (isset($this->normalization[$this->scheme]['iuserinfo']) && $this->iuserinfo === $this->normalization[$this->scheme]['iuserinfo'])
        {
            $this->iuserinfo = null;
        }
        if (isset($this->normalization[$this->scheme]['ihost']) && $this->ihost === $this->normalization[$this->scheme]['ihost'])
        {
            $this->ihost = null;
        }
        if (isset($this->normalization[$this->scheme]['port']) && $this->port === $this->normalization[$this->scheme]['port'])
        {
            $this->port = null;
        }
        if (isset($this->normalization[$this->scheme]['ipath']) && $this->ipath === $this->normalization[$this->scheme]['ipath'])
        {
            $this->ipath = null;
        }
        if (isset($this->normalization[$this->scheme]['iquery']) && $this->iquery === $this->normalization[$this->scheme]['iquery'])
        {
            $this->iquery = null;
        }
        if (isset($this->normalization[$this->scheme]['ifragment']) && $this->ifragment === $this->normalization[$this->scheme]['ifragment'])
        {
            $this->ifragment = null;
        }
    }

    /**
     * Check if the object represents a valid IRI. This needs to be done on each
     * call as some things change depending on another part of the IRI.
     *
     * @return bool
     */
    public function is_valid()
    {
        if ($this->ipath !== null && (
               substr($this->ipath, 0, 2) === '//' && $this->get_iauthority() === null
            || substr($this->ipath, 0, 1) !== '/' && $this->ipath !== '' && $this->get_iauthority() !== null
            || strpos($this->ipath, ':') !== false && (strpos($this->ipath, '/') === false ? true : strpos($this->ipath, ':') < strpos($this->ipath, '/')) && $this->scheme === null && $this->get_iauthority() === null
            )
        )
        {
            return false;
        }
        
        return true;
    }

    /**
     * Set the entire IRI. Returns true on success, false on failure (if there
     * are any invalid characters).
     *
     * @param string $iri
     * @return bool
     */
    private function set_iri($iri)
    {
        if ($iri !== null)
        {
            $parsed = $this->parse_iri((string) $iri);
            
            return $this->set_scheme($parsed['scheme'])
                && $this->set_authority($parsed['authority'])
                && $this->set_path($parsed['path'])
                && $this->set_query($parsed['query'])
                && $this->set_fragment($parsed['fragment']);
        }
    }

    /**
     * Set the scheme. Returns true on success, false on failure (if there are
     * any invalid characters).
     *
     * @param string $scheme
     * @return bool
     */
    private function set_scheme($scheme)
    {
        if ($scheme === null)
        {
            $this->scheme = null;
        }
        elseif (
               !($scheme = (string) $scheme)
            || !isset($scheme[0])
            || $scheme[0] < 'A'
            || $scheme[0] > 'Z' && $scheme[0] < 'a'
            || $scheme[0] > 'z'
            || strspn($scheme, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-.') !== strlen($scheme)
        )
        {
            $this->scheme = null;
            return false;
        }
        else
        {
            $this->scheme = strtolower($scheme);
        }
        return true;
    }

    /**
     * Set the authority. Returns true on success, false on failure (if there are
     * any invalid characters).
     *
     * @param string $authority
     * @return bool
     */
    private function set_authority($authority)
    {
        if (($iuserinfo_end = strrpos($authority, '@')) !== false)
        {
            $iuserinfo = substr($authority, 0, $iuserinfo_end);
            $authority = substr($authority, $iuserinfo_end + 1);
        }
        else
        {
            $iuserinfo = null;
        }
        if (($port_start = strpos($authority, ':', strpos($authority, ']'))) !== false)
        {
            if (($port = substr($authority, $port_start + 1)) === false)
            {
                $port = null;
            }
            $authority = substr($authority, 0, $port_start);
        }
        else
        {
            $port = null;
        }

        return $this->set_userinfo($iuserinfo) && $this->set_host($authority) && $this->set_port($port);
    }

    /**
     * Set the iuserinfo.
     *
     * @param string $iuserinfo
     * @return bool
     */
    private function set_userinfo($iuserinfo)
    {
        if ($iuserinfo === null)
        {
            $this->iuserinfo = null;
        }
        else
        {
            $this->iuserinfo = $this->replace_invalid_with_pct_encoding($iuserinfo, '!$&\'()*+,;=:');
            $this->scheme_normalization();
        }
        
        return true;
    }

    /**
     * Set the ihost. Returns true on success, false on failure (if there are
     * any invalid characters).
     *
     * @param string $ihost
     * @return bool
     */
    private function set_host($ihost)
    {
        if ($ihost === null)
        {
            $this->ihost = null;
            return true;
        }
        elseif (substr($ihost, 0, 1) === '[' && substr($ihost, -1) === ']')
        {
            if (Net_IPv6::check_ipv6(substr($ihost, 1, -1)))
            {
                $this->ihost = '[' . Net_IPv6::compress(substr($ihost, 1, -1)) . ']';
            }
            else
            {
                $this->ihost = null;
                return false;
            }
        }
        else
        {
            $ihost = $this->replace_invalid_with_pct_encoding($ihost, '!$&\'()*+,;=');
            
            // Lowercase, but ignore pct-encoded sections (as they should
            // remain uppercase). This must be done after the previous step
            // as that can add unescaped characters.
            $position = 0;
            $strlen = strlen($ihost);
            while (($position += strcspn($ihost, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ%', $position)) < $strlen)
            {
                if ($ihost[$position] === '%')
                {
                    $position += 3;
                }
                else
                {
                    $ihost[$position] = strtolower($ihost[$position]);
                    $position++;
                }
            }
            
            $this->ihost = $ihost;
        }
        
        $this->scheme_normalization();
        
        return true;
    }

    /**
     * Set the port. Returns true on success, false on failure (if there are
     * any invalid characters).
     *
     * @param string $port
     * @return bool
     */
    private function set_port($port)
    {
        if ($port === null)
        {
            $this->port = null;
            return true;
        }
        elseif (strspn($port, '0123456789') === strlen($port))
        {
            $this->port = (int) $port;
            $this->scheme_normalization();
            return true;
        }
        else
        {
            $this->port = null;
            return false;
        }
    }

    /**
     * Set the ipath.
     *
     * @param string $ipath
     * @return bool
     */
    private function set_path($ipath)
    {
        if ($ipath === null)
        {
            $this->ipath = null;
            return true;
        }
        else
        {
            $ipath = explode('/', $ipath);
            $this->ipath = '';
            foreach ($ipath as $segment)
            {
                $this->ipath .= $this->replace_invalid_with_pct_encoding($segment, '!$&\'()*+,;=@:');
                $this->ipath .= '/';
            }
            $this->ipath = substr($this->ipath, 0, -1);
            if ($this->scheme !== null)
            {
                $this->ipath = $this->remove_dot_segments($this->ipath);
            }
            $this->scheme_normalization();
            return true;
        }
    }

    /**
     * Set the iquery.
     *
     * @param string $iquery
     * @return bool
     */
    private function set_query($iquery)
    {
        if ($iquery === null)
        {
            $this->iquery = null;
        }
        else
        {
            $this->iquery = $this->replace_invalid_with_pct_encoding($iquery, '!$&\'()*+,;=:@/?', true);
            $this->scheme_normalization();
        }
        return true;
    }

    /**
     * Set the ifragment.
     *
     * @param string $ifragment
     * @return bool
     */
    private function set_fragment($ifragment)
    {
        if ($ifragment === null)
        {
            $this->ifragment = null;
        }
        else
        {
            $this->ifragment = $this->replace_invalid_with_pct_encoding($ifragment, '!$&\'()*+,;=:@/?');
            $this->scheme_normalization();
        }
        return true;
    }

    /**
     * Convert an IRI to a URI (or parts thereof)
     *
     * @return string
     */
    private function to_uri($string)
    {
        static $non_ascii = "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";
        
        $position = 0;
        $strlen = strlen($string);
        while (($position += strcspn($string, $non_ascii, $position)) < $strlen)
        {
            $string = substr_replace($string, sprintf('%%%02X', ord($string[$position])), $position, 1);
            $position += 3;
            $strlen += 2;
        }
        
        return $string;
    }

    /**
     * Get the complete IRI
     *
     * @return string
     */
    private function get_iri()
    {
        $iri = '';
        $defined = false;
        if ($this->scheme !== null)
        {
            $iri .= $this->scheme . ':';
        }
        if (($iauthority = $this->iauthority) !== null)
        {
            $iri .= '//' . $iauthority;
        }
        if ($this->ipath !== null)
        {
            $iri .= $this->ipath;
            $defined = true;
        }
        if ($this->iquery !== null)
        {
            $iri .= '?' . $this->iquery;
        }
        if ($this->ifragment !== null)
        {
            $iri .= '#' . $this->ifragment;
        }

        if ($iri !== '' || $defined)
        {
            return $iri;
        }
        else
        {
            return null;
        }
    }

    /**
     * Get the complete URI
     *
     * @return string
     */
    private function get_uri()
    {
        $iri = $this->iri;
        if (is_string($iri))
            return $this->to_uri($iri);
        else
            return $iri;
    }

    /**
     * Get the complete iauthority
     *
     * @return string
     */
    private function get_iauthority()
    {
        $iauthority = '';
        if ($this->iuserinfo !== null)
        {
            $iauthority .= $this->iuserinfo . '@';
        }
        if ($this->ihost !== null)
        {
            $iauthority .= $this->ihost;
        }
        if ($this->port !== null)
        {
            $iauthority .= ':' . $this->port;
        }

        if ($this->iuserinfo !== null || $this->ihost !== null || $this->port !== null)
        {
            return $iauthority;
        }
        else
        {
            return null;
        }
    }

    /**
     * Get the complete authority
     *
     * @return string
     */
    private function get_authority()
    {
        $iauthority = $this->iauthority;
        if (is_string($iauthority))
            return $this->to_uri($iauthority);
        else
            return $iauthority;
    }
}

/**
 * Class to validate and to work with IPv6 addresses.
 *
 * This was originally based on the PEAR class of the same name, but has been
 * almost entirely rewritten.
 */
class Net_IPv6
{
    /**
     * Uncompresses an IPv6 address
     *
     * RFC 4291 allows you to compress concecutive zero pieces in an address to
     * '::'. This method expects a valid IPv6 address and expands the '::' to
     * the required number of zero pieces.
     *
     * Example:  FF01::101   ->  FF01:0:0:0:0:0:0:101
     *           ::1         ->  0:0:0:0:0:0:0:1
     *
     * @author Alexander Merz <alexander.merz@web.de>
     * @author elfrink at introweb dot nl
     * @author Josh Peck <jmp at joshpeck dot org>
     * @copyright 2003-2005 The PHP Group
     * @license http://www.opensource.org/licenses/bsd-license.php
     * @param string $ip An IPv6 address
     * @return string The uncompressed IPv6 address
     */
    public static function uncompress($ip)
    {
        $c1 = -1;
        $c2 = -1;
        if (substr_count($ip, '::') === 1)
        {
            list($ip1, $ip2) = explode('::', $ip);
            if ($ip1 === '')
            {
                $c1 = -1;
            }
            else
            {
                $c1 = substr_count($ip1, ':');
            }
            if ($ip2 === '')
            {
                $c2 = -1;
            }
            else
            {
                $c2 = substr_count($ip2, ':');
            }
            if (strpos($ip2, '.') !== false)
            {
                $c2++;
            }
            // ::
            if ($c1 === -1 && $c2 === -1)
            {
                $ip = '0:0:0:0:0:0:0:0';
            }
            // ::xxx
            else if ($c1 === -1)
            {
                $fill = str_repeat('0:', 7 - $c2);
                $ip = str_replace('::', $fill, $ip);
            }
            // xxx::
            else if ($c2 === -1)
            {
                $fill = str_repeat(':0', 7 - $c1);
                $ip = str_replace('::', $fill, $ip);
            }
            // xxx::xxx
            else
            {
                $fill = ':' . str_repeat('0:', 6 - $c2 - $c1);
                $ip = str_replace('::', $fill, $ip);
            }
        }
        return $ip;
    }

    /**
     * Compresses an IPv6 address
     *
     * RFC 4291 allows you to compress concecutive zero pieces in an address to
     * '::'. This method expects a valid IPv6 address and compresses consecutive
     * zero pieces to '::'.
     *
     * Example:  FF01:0:0:0:0:0:0:101   ->  FF01::101
     *           0:0:0:0:0:0:0:1        ->  ::1
     *
     * @see uncompress()
     * @param string $ip An IPv6 address
     * @return string The compressed IPv6 address
     */
    public static function compress($ip)
    {
        // Prepare the IP to be compressed
        $ip = self::uncompress($ip);
        $ip_parts = self::split_v6_v4($ip);
        
        // Break up the IP into each seperate part
        $ipp = explode(':', $ip_parts[0]);
        
        // Initialise vars to count consecutive zero pieces
        $consecutive_zeros = 0;
        $max_consecutive_zeros = 0;
        for ($i = 0; $i < count($ipp); $i++)
        {
            // Normalise the number (this changes things like 01 to 0)
            $ipp[$i] = dechex(hexdec($ipp[$i]));
            
            // Count the zeros
            if ($ipp[$i] === '0')
            {
                $consecutive_zeros++;
            }
            elseif ($consecutive_zeros > $max_consecutive_zeros)
            {
                $consecutive_zeros_pos = $i - $consecutive_zeros;
                $max_consecutive_zeros = $consecutive_zeros;
                $consecutive_zeros = 0;
            }
        }
        if ($consecutive_zeros > $max_consecutive_zeros)
        {
            $consecutive_zeros_pos = $i - $consecutive_zeros;
            $max_consecutive_zeros = $consecutive_zeros;
            $consecutive_zeros = 0;
        }
        
        // Rebuild the IP
        if ($max_consecutive_zeros > 0)
        {
            $cip = '';
            for ($i = 0; $i < count($ipp); $i++)
            {
                // Add a : for the longest consecutive sequence, or :: if it's at the end
                if ($i === $consecutive_zeros_pos)
                {
                    if ($i === count($ipp) - $max_consecutive_zeros)
                    {
                        $cip .= '::';
                    }
                    else
                    {
                        $cip .= ':';
                    }
                }
                // Otherwise, just add the piece to the new output
                elseif ($i < $consecutive_zeros_pos || $i >= $consecutive_zeros_pos + $max_consecutive_zeros)
                {
                    if ($i !== 0)
                    {
                        $cip .= ':';
                    }
                    $cip .= $ipp[$i];
                }
            }
        }
        // Cheat if we don't have any zero pieces
        else
        {
            $cip = implode(':', $ipp);
        }
        
        // Re-add any IPv4 part of the address
        if ($ip_parts[1] !== '')
        {
            $cip .= ":{$ip_parts[1]}";
        }
        return $cip;
    }

    /**
     * Splits an IPv6 address into the IPv6 and IPv4 representation parts
     *
     * RFC 4291 allows you to represent the last two parts of an IPv6 address
     * using the standard IPv4 representation
     *
     * Example:  0:0:0:0:0:0:13.1.68.3
     *           0:0:0:0:0:FFFF:129.144.52.38
     *
     * @param string $ip An IPv6 address
     * @return array [0] contains the IPv6 represented part, and [1] the IPv4 represented part
     */
    private static function split_v6_v4($ip)
    {
        if (strpos($ip, '.') !== false)
        {
            $pos = strrpos($ip, ':');
            $ipv6_part = substr($ip, 0, $pos);
            $ipv4_part = substr($ip, $pos + 1);
            return array($ipv6_part, $ipv4_part);
        }
        else
        {
            return array($ip, '');
        }
    }

    /**
     * Checks an IPv6 address
     *
     * Checks if the given IP is a valid IPv6 address
     *
     * @param string $ip An IPv6 address
     * @return bool true if $ip is a valid IPv6 address
     */
    public static function check_ipv6($ip)
    {
        $ip = self::uncompress($ip);
        list($ipv6, $ipv4) = self::split_v6_v4($ip);
        $ipv6 = explode(':', $ipv6);
        $ipv4 = explode('.', $ipv4);
        if (count($ipv6) === 8 && count($ipv4) === 1 || count($ipv6) === 6 && count($ipv4) === 4)
        {
            foreach ($ipv6 as $ipv6_part)
            {
                $ipv6_part = ltrim($ipv6_part, '0');
                if ($ipv6_part === '')
                    $ipv6_part = '0';
                $value = hexdec($ipv6_part);
                if (dechex($value) !== strtolower($ipv6_part) || $value < 0 || $value > 0xFFFF)
                    return false;
            }
            if (count($ipv4) === 4)
            {
                foreach ($ipv4 as $ipv4_part)
                {
                    $value = (int) $ipv4_part;
                    if ((string) $value !== $ipv4_part || $value < 0 || $value > 0xFF)
                        return false;
                }
            }
            return true;
        }
        else
        {
            return false;
        }
    }
}

?>