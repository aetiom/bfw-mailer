<?php

namespace BfwMailer\Helpers;

/**
 * Helpers to work with HTML code
 * @author Aetiom <aetiom@protonmail.com>
 * @package bfw-mailer
 * @version 1.1
 */
class Html {
    
    /**
     * Convert HTML to Text (plain text)
     *      * convert secure html into raw html
     *      * remove head, title, style and script contents
     *      * stripes other html tags
     *      * only trims blank spaces, NUL and vertical tab
     *      * keeps horizontal tab, line break and carriage return
     * 
     * @param string $html : html string to convert
     * @return string converted plain text
     */
    static public function toText($html)
    {
        $tag_to_remove = array('head', 'title', 'style', 'script');
        $tag_regex = implode('|', $tag_to_remove);
        
        // decode secure html
        $unsecure_html = html_entity_decode($html, ENT_QUOTES | ENT_HTML401);
        
        $text = strip_tags(preg_replace('/<('.$tag_regex.')[^>]*>.*?<\/\\1>/si', '', $unsecure_html));
        return trim($text, ' \0\x0B');
    }
}
