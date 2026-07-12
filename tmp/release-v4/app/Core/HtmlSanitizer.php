<?php
declare(strict_types=1);

namespace MaisonBebe\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

final class HtmlSanitizer
{
    private const TAGS = ['p','h2','h3','h4','ul','ol','li','strong','em','blockquote','a','br','figure','figcaption','img'];
    private const ATTRS = ['a'=>['href','title','rel'],'img'=>['src','alt','width','height','loading']];

    public static function clean(string $html): string
    {
        $document = new DOMDocument('1.0','UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root=$document->getElementById('root');
        if(!$root){return '';}
        self::walk($root);
        $output='';foreach(iterator_to_array($root->childNodes) as $child){$output.=$document->saveHTML($child);}
        return $output;
    }

    private static function walk(DOMNode $node):void
    {
        foreach(iterator_to_array($node->childNodes) as $child){
            if($child instanceof DOMElement){
                $tag=strtolower($child->tagName);
                if(!in_array($tag,self::TAGS,true)){
                    while($child->firstChild){$node->insertBefore($child->firstChild,$child);} $node->removeChild($child);continue;
                }
                foreach(iterator_to_array($child->attributes) as $attribute){if(!in_array(strtolower($attribute->name),self::ATTRS[$tag]??[],true)){$child->removeAttribute($attribute->name);}}
                if($tag==='a'){$href=$child->getAttribute('href');if(!preg_match('#^(https?://|/|mailto:)#i',$href)){$child->removeAttribute('href');}else{$child->setAttribute('rel','noopener');}}
                if($tag==='img'){$src=$child->getAttribute('src');if(!str_starts_with($src,'/')&&!str_starts_with($src,'https://')){$child->removeAttribute('src');}$child->setAttribute('loading','lazy');}
                self::walk($child);
            }
        }
    }
}

