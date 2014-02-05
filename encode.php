<?php

    namespace Encoding;

    function encodeString($string)
    {
        require_once 'encoding.php';
     
        $text = \ForceUTF8\Encoding::toUTF8($string);
        $text = html_entity_decode_numeric($text);

        return $text;
    }


    /**
    * Decodes all HTML entities, including numeric and hexadecimal ones.
    * 
    * @param mixed $string
    * @return string decoded HTML
    */
    function html_entity_decode_numeric($string, $quote_style = ENT_COMPAT, $charset = "utf-8")
    {
        $string = html_entity_decode($string, $quote_style, $charset);
        $string = preg_replace_callback('~&#x([0-9a-fA-F]+);~i', "\Encoding\chr_utf8_callback", $string);
        $string = preg_replace_callback('~&#([0-9]+);~', function($m){ return chr_utf8($m[1]); }, $string);
        return $string; 
    }

    /** 
     * Callback helper 
     */
    function chr_utf8_callback($matches)
    { 
        return chr_utf8(hexdec($matches[1])); 
    }

    /**
    * Multi-byte chr(): Will turn a numeric argument into a UTF-8 string.
    * 
    * @param mixed $num
    * @return string
    */
    function chr_utf8($num)
    {
        if ($num < 128) return chr($num);
        if ($num < 2048) return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
        if ($num < 65536) return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        if ($num < 2097152) return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        return '';
    }
    