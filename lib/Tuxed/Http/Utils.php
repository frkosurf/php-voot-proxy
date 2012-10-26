<?php

namespace Tuxed\Http;

class Utils
{
    // taken from http://www.php.net/manual/en/function.json-encode.php#80339
    public static function json_format($json)
    {
        $tab = "  ";
        $new_json = "";
        $indent_level = 0;
        $in_string = false;

        $json_obj = json_decode($json);

        if($json_obj === false)

            return false;

        $json = json_encode($json_obj);
        $len = strlen($json);

        for ($c = 0; $c < $len; $c++) {
            $char = $json[$c];
            switch ($char) {
                case '{':
                case '[':
                    if (!$in_string) {
                        $new_json .= $char . "\n" . str_repeat($tab, $indent_level+1);
                        $indent_level++;
                    } else {
                        $new_json .= $char;
                    }
                    break;
                case '}':
                case ']':
                    if (!$in_string) {
                        $indent_level--;
                        $new_json .= "\n" . str_repeat($tab, $indent_level) . $char;
                    } else {
                        $new_json .= $char;
                    }
                    break;
                case ',':
                    if (!$in_string) {
                        $new_json .= ",\n" . str_repeat($tab, $indent_level);
                    } else {
                        $new_json .= $char;
                    }
                    break;
                case ':':
                    if (!$in_string) {
                        $new_json .= ": ";
                    } else {
                        $new_json .= $char;
                    }
                    break;
                case '"':
                    if ($c > 0 && $json[$c-1] != '\\') {
                        $in_string = !$in_string;
                    }
                default:
                    $new_json .= $char;
                    break;
            }
        }

        return $new_json;
    }

    public static function parseBasicAuthHeader($h)
    {
        // RFC 2045, section 6.8
        $basicTokenRegExp = '(?:[[:alpha:][:digit:]+/]+=*)';    // FIXME, only 1 or 2 "="s at the end
        $result = preg_match('|^Basic (?P<value>' . $basicTokenRegExp . ')$|', $h, $matches);
        if ($result === FALSE || $result === 0) {
            throw new UtilsException("invalid basic authentication header value");
        }
        $d = base64_decode($matches['value'], TRUE);
        if (FALSE === $d) {
            throw new UtilsException("invalid basic authentication header");
        }
        // FIXME: better check for allowed decoded characters
        if (FALSE === strpos($d, ":")) {
            throw new UtilsException("basic authentication header does not encode username and password");
        }
        $userpass = explode(":", $d);
        if (2 !== count($userpass)) {
            throw new UtilsException("decoded basic authentication header needs to contain exactly 1 colon");
        }

        return $userpass;
    }

}
