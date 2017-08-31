<?php

/*
  Copyright (c) 2008-9, the Phantom Inker.  All rights reserved.
  Portions Copyright (c) 2004-2008 AddedBytes.com

  Redistribution and use in source and binary forms, with or without
  modification, are permitted provided that the following conditions
  are met:

 * Redistributions of source code must retain the above copyright
  notice, this list of conditions and the following disclaimer.

 * Redistributions in binary form must reproduce the above copyright
  notice, this list of conditions and the following disclaimer in
  the documentation and/or other materials provided with the
  distribution.

  THIS SOFTWARE IS PROVIDED BY THE PHANTOM INKER "AS IS" AND ANY EXPRESS
  OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
  WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
  DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
  LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
  CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
  SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
  BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
  WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
  OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN
  IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

class BeforeSaveToDBParser extends StrippedNBBCParser {


    public function __construct() {
        parent::__construct();
    }

    public function getBeginEndCode($format) {
        switch (strtolower($format)) {
            case 'html':
                $begin_code = '<code class="CodeInline">';
                $end_code = '</code>';
                break;
            case 'wysiwyg':$begin_code = '<pre class="CodeBlock">';
                $end_code = '</pre>';
                break;
            case 'markdown':
                $begin_code = '<code>';
                $end_code = '</code>';
                break;
            default:
                $begin_code = '[code]';
                $end_code = '[/code]';
                break;
        }
        return [$begin_code, $end_code];
    }

}
class BeforeDisplayParser extends StrippedNBBCParser {

    public function __construct() {
        parent::__construct();
    }

    public function getBeginEndCode($format) {

//'Wysiwyg', 'Html', 'Markdown', 'BBCode', 'Text', 'TextEx';
        switch (strtolower($format)) {
            case 'bbcode':
                $begin_code = '<pre>';
                $end_code = '</pre>';
                break;
            case 'html':
                $begin_code = '<code class="CodeInline">';
                $end_code = '</code>';
                break;
            case 'wysiwyg':$begin_code = '<pre class="CodeBlock">';
                $end_code = '</pre>';
                break;
            case 'markdown':
                $begin_code = '<code>';
                $end_code = '</code>';
                break;
            default:
                $begin_code = '[code]';
                $end_code = '[/code]';
                break;
        }
        return [$begin_code, $end_code];
    }

}

abstract class StrippedNBBCParser {

    public $tag_rules;

    public function __construct() {
        $this->tag_rules = [];
    }

    public function AddRule($code, $args) {
        $this->tag_rules[$code] = $args;
    }

    public function parsePart($noncode, $currentPost) {
        $parts = preg_split("/\[(.*?)\]/", $noncode, -1, PREG_SPLIT_DELIM_CAPTURE);
        $numparts = count($parts);
        $stack = [];
        for ($i = 1; $i < $numparts; $i+=2) {
            $token = $parts[$i];
            if ($token[0] === '/') {
                $stack_item_length = count($stack);
                //If the end token is empty, just close the first item on the stack.
                if($token==='/'&&$stack_item_length>0)
                {
                    $token=$stack[$stack_item_length - 1]->endToken;
                }
                for ($j = $stack_item_length - 1; $j > -1; $j--) {
                    if ($stack[$j] && $stack[$j]->endToken === $token) {
                        $toParse = "";
                        for ($k = $stack[$j]->index + 1; $k < $i; $k+=2) {
                            if ($parts[$k] === false) {
                                break;
                            }
                            $toParse.=$parts[$k];
                            $parts[$k] = false;
                            if ($k - $i > 1 && $parts[$k + 1] !== false) {
                                $toParse.='[' . $parts[$k + 1] . ']';
                                $parts[$k + 1] = false;
                            }
                        }
                        $parts[$stack[$j]->index + 1] = $this->parseItem($stack[$j], $toParse);
                        $parts[$stack[$j]->index] = false;
                        $parts[$i] = false;
                        $stack[$j] = false;
                        break;
                    }
                }
            } else {
                $params = explode(' ', $token);
                $param_array = [];
                $param_array['_default'] = '';
                for ($j = 0; $j < count($params); $j++) {
                    $param_parts = explode('=', $params[$j]);
                    if ($j === 0) {
                        $token = $param_parts[0];
                        if (isset($param_parts[1])) {
                            $param_array['_default'] = $param_parts[1];
                            $param_array[$token] = $param_parts[1];
                        }
                        continue;
                    }
                    $paramName = $param_parts[0];
                    while (strlen($paramName) > 0 && $paramName[0] === '_') {
                        $paramName = substr($paramName, 1);
                    }
                    if (strlen($paramName) < 1) {
                        break;
                    }
                    if (empty($param_array['_default'])) {
                        $param_array['_default'] = isset($param_parts[1])?$param_parts[1]:false;
                    }
                    $param_array[$paramName] = isset($param_parts[1])?$param_parts[1]:false;
                }
                if (isset($this->tag_rules[$token])) {
                    $item = new stdClass();
                    $item->token = $token;
                    $item->startToken = $parts[$i];
                    $item->endToken = "/$token";
                    $item->index = $i;
                    $param_array['_name'] = $token;
                    $param_array['_currentPost'] = $currentPost;
                    $param_array['_tag'] = '[' . $parts[$i] . ']';
                    $param_array['_endtag'] = "[/$token]";
                    $item->params = $param_array;
                    if (isset($this->tag_rules[$token]['end_tag'])) {
                        $item->params['_hasend'] = false;
                        $parts[$i + 1] = $this->parseItem($item, '') . $parts[$i + 1];
                        $parts[$i] = false;
                    } else {
                        $item->params['_hasend'] = true;
                        $stack[] = $item;
                    }
                }
            }
        }
        $newbody = "";
        for ($i = 0; $i < $numparts - 1; $i+=2) {
            if ($parts[$i]) {
                $newbody.=$parts[$i];
            }
            if ($parts[$i + 1]) {
                $newbody.='[' . $parts[$i + 1] . ']';
            }
        }
        if ($parts[$i]) {
            $newbody.=$parts[$i];
        }
        return $newbody;
    }

    function parseItem($item, $content) {
        $newcontent = false;
        $rule = $this->tag_rules[$item->token];
        if (isset($rule['method'])) {
            //$parser,$action, $name, $default, $params, $content
            $params = [$this, BBCODE_CHECK, $item->token, $item->params['_default'], $item->params, $content];
            if(call_user_func_array($rule['method'], $params)){
                $params[1]=0;
                $newcontent = call_user_func_array($rule['method'], $params);
            }
            if ($newcontent === false) {
                $newcontent = '[' . $item->startToken . ']' . (isset($rule['end_tag'])?"":($content . '[' . $item->endToken . ']'));
                return $newcontent;
            }
        }
        if (isset($rule['simple_start'])) {
            $newcontent = $rule['simple_start'] . ($newcontent ? $newcontent : $content);
        }
        if (isset($rule['simple_end'])) {
            $newcontent = ($newcontent ? $newcontent : $content) . $rule['simple_end'];
        }
        return $newcontent===false?$content:$newcontent;
    }

    public abstract function getBeginEndCode($format);

    public function parse($Body, $currentPost) {
        list($begin_code, $end_code) = $this->getBeginEndCode($currentPost->Format);
        $start=0;
        $end=0;
        $previous=0;
        $newbody="";
        while(($start=strpos($Body,$begin_code,$previous))!==false)
        {
            $start+=strlen($begin_code);
            $end=strpos($Body,$end_code,$start);
            if($end===false)
            {
                break;
            }
            //Parse everything up until the part between code tags, and just leave that part alone.
            $newbody.=$this->parsePart(substr($Body, $previous, $start-$previous),$currentPost).substr($Body, $start,$end-$start);
            $previous=$end;
        }
        //Parse everything that wasn't parsed.
        $newbody.=$this->parsePart(substr($Body,$previous),$currentPost);
        return $newbody;
    }

}
