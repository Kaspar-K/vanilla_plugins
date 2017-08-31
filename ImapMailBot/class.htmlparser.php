<?php

class HTMLParser {

    function PrintUrl($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        $href = isset($params['href']) ? $params['href'] : (isset($params['_default']) ? $params['_default'] : "");
        return "[url=$href]" . $content . '[/url]';
    }

    function PrintTag($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        $style = isset($params['style']) ? $params['style'] : (isset($params['_default']) ? $params['_default'] : '');
        $headers = ['h1' => 5, 'h2' => 4, 'h3' => 4, 'h4' => 3];
        //If the tag is a 'u','b' or 'i' tag we shouldn't change anything about it
        if ($name == 'u' || $name == 'b' || $name == 'i') {
            return "[$name]$content" . "[/$name]";
        }
        //If it's a td tag, remove all line breaks and add a bit of whitespace.
        if($name=='td')
            {
            $content=str_replace("[br]", " ", $content)."    ";
            }

            //If it's a header tag, increase the size.
        if (isset($headers[$name])) {
            $content = "[b][size=\"$headers[$name]\"]$content" . '[/size][/b]';
        }
        //Change style attribute to bbcode
        $style_conversion = ['bold' => 'b', 'italic' => 'i', 'underline' => 'u'];
        foreach ($style_conversion as $to_replace => $replacewith) {
            if (strpos($style, $to_replace) !== false) {
                $content = "[$replacewith]$content" . "[/$replacewith]";
            }
        }
        //If it's one of the following tags, introduce line breaks
        $newlines_req = ['p' => true, 'tr' => true, 'h1' => true, 'h2' => true, 'h3' => true, 'h4' => true, 'div' => true];
        if (isset($newlines_req[$name])) {
            $content = "[br]" . $content . "[br]";
        }
        return $content;
    }

    private $html;
    private $allowedTags = ['span', 'p', 'tr', 'h1', 'h2', 'h3', 'h4', 'div', 'b', 'i', 'u','td'];
    private $specialTags = ['a', 'img', 'hr', 'br', 'strong', 'em'];

    public function __construct($html) {
        $shouldNotStrip = "";
        foreach ($this->allowedTags as $tag) {
            $shouldNotStrip.="<$tag>";
        }
        foreach ($this->specialTags as $tag) {
            $shouldNotStrip.="<$tag>";
        }
        //Remove all tags we've not defined rules for.
        $html = strip_tags($html, $shouldNotStrip);
        //Strong tag should be a bold tag, em tag becomes an i tag
        $html = str_ireplace('<strong>', '[b]', $html);
        $html = str_ireplace('</strong>', '[/b]', $html);
        $html = str_ireplace('<em>', '[i]', $html);
        $html = str_ireplace('</em>', '[/i]', $html);
        //Convert image tag to image bb tag
        $html = preg_replace('/<img(.*?)src="(.*?)"(.*?)>/', '[img]$2[/img]', $html);
        //Change all html tags that are left over to bb tags
        $html = str_ireplace('<', '[', $html);
        $html = str_ireplace('>', ']', $html);
        //Replace [hrX] with [rule] and [brX] with [br] (remove attributes)
        $html = preg_replace("/\[hr(.*?)\]/i", '[rule]', $html);
        $html = preg_replace("/\[br(.*?)\]/i", '[br]', $html);
        $this->html = $html;
    }

    public function getBBCode() {
        if (c("Garden.InputFormatter", false) === "BBCode"&&Gdn::pluginManager()->isEnabled("NBBCSaveParser")) {
            //This includes the unsafe parser.
            new NBBCSaveParserPlugin();
            $htmlparser = new UnsafeParser();
            $htmlparser->SetIgnoreNewlines(true);
            $htmlparser->SetAllowAmpersand(true);
            $htmlparser->ClearRules();
            $rules = [];
            foreach ($this->allowedTags as $key) {
                //Add a new rule for all defined tags
                $rules[$key] = [
                    'mode' => BBCODE_MODE_CALLBACK,
                    'method' => [$this, 'printTag'],
                    'class' => 'inline',
                    'allow_in' => Array('listitem', 'block', 'columns', 'inline', 'link')];
            }
            $htmlparser->tag_rules = $rules;
            //the a tag is special and requires a specific function.
            $htmlparser->AddRule("a", [
                'mode' => BBCODE_MODE_CALLBACK,
                'method' => [$this, 'printURL'],
                'class' => 'inline',
                'allow_in' => Array('listitem', 'block', 'columns', 'inline', 'link')]);
            //Replace all [br] tags with simple newlines.
            return str_replace("[br]", "\n", $htmlparser->Parse($this->html));
        }
        return $this->html;
    }

}
