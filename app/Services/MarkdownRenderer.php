<?php
namespace App\Services;

final class MarkdownRenderer
{
    private array $codeFragments = [];
    private string $fragmentPrefix = '';
    public function render(string $markdown): string
    {
        if (strlen($markdown)>1048576) throw new \InvalidArgumentException('Markdown exceeds the supported size.');
        $this->codeFragments=[]; $this->fragmentPrefix='SPSCODE'.bin2hex(random_bytes(8)).'Z';
        $html = $markdown;
        $html = $this->renderCodeBlocks($html);
        $html = $this->renderHeadings($html);
        $html = $this->renderHorizontalRules($html);
        $html = $this->renderTables($html);
        $html = $this->renderUnorderedLists($html);
        $html = $this->renderOrderedLists($html);
        $html = $this->renderInlineFormatting($html);
        $html = $this->renderParagraphs($html);
        $html = strtr($html,$this->codeFragments);
        $html = $this->sanitize($html);
        return $html;
    }


    /**
     * GitHub-style pipe tables. Runs after headings and rules but before lists and
     * paragraphs, so a table block is already wrapped in <table> by the time
     * renderParagraphs() sees it and is left alone.
     *
     *   | Name | Price |
     *   |------|------:|
     *   | Mala |   499 |
     *
     * The delimiter row sets alignment: :--- left, ---: right, :---: centre.
     */
    private function renderTables(string $html): string
    {
        $lines = explode("\n", $html);
        $out = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $header = trim($lines[$i]);
            $delimiter = trim($lines[$i + 1] ?? '');

            $looksLikeTable = $header !== '' && str_contains($header, '|')
                && $delimiter !== '' && preg_match('/^\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?$/', $delimiter) === 1;

            if (!$looksLikeTable) { $out[] = $lines[$i]; continue; }

            $headers = $this->splitTableRow($header);
            $aligns = array_map(static function (string $spec): string {
                $spec = trim($spec);
                $left = str_starts_with($spec, ':');
                $right = str_ends_with($spec, ':');
                if ($left && $right) return ' style="text-align:center"';
                if ($right) return ' style="text-align:right"';
                return '';
            }, $this->splitTableRow($delimiter));

            if (count($headers) === 0) { $out[] = $lines[$i]; continue; }

            $body = [];
            $j = $i + 2;
            while ($j < $count && trim($lines[$j]) !== '' && str_contains($lines[$j], '|')) {
                $body[] = $this->splitTableRow(trim($lines[$j]));
                $j++;
            }

            $table = '<table><thead><tr>';
            foreach ($headers as $index => $cell) {
                $table .= '<th' . ($aligns[$index] ?? '') . '>' . $cell . '</th>';
            }
            $table .= '</tr></thead><tbody>';
            foreach ($body as $row) {
                $table .= '<tr>';
                foreach ($headers as $index => $_) {
                    $table .= '<td' . ($aligns[$index] ?? '') . '>' . ($row[$index] ?? '') . '</td>';
                }
                $table .= '</tr>';
            }
            $table .= '</tbody></table>';

            $out[] = $table;
            $i = $j - 1;
        }

        return implode("\n", $out);
    }

    /** Splits a pipe row into cells, ignoring the optional leading and trailing pipes. */
    private function splitTableRow(string $row): array
    {
        $row = preg_replace('/^\||\|$/', '', trim($row)) ?? $row;
        return array_map('trim', explode('|', $row));
    }

    /** Parse then rebuild a narrow safe tree; strip_tags alone retains dangerous attributes. */
    private function sanitize(string $html): string
    {
        if(!class_exists(\DOMDocument::class))return '<p>'.htmlspecialchars(strip_tags($html),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';
        $doc=new \DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);
        try{$ok=$doc->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
        finally{libxml_clear_errors();libxml_use_internal_errors($old);}
        if(!$ok)return '<p>'.htmlspecialchars(strip_tags($html),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';
        $body=$doc->getElementsByTagName('body')->item(0);if(!$body)return '';
        $result='';foreach($body->childNodes as $child)$result.=$this->safeNode($child,0);return $result;
    }

    private function safeNode(\DOMNode $node,int $depth): string
    {
        if($depth>64)return '';
        if($node instanceof \DOMText)return htmlspecialchars($node->nodeValue??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        if(!($node instanceof \DOMElement))return '';
        $tag=strtolower($node->tagName);
        $allowed=['p','br','strong','em','b','i','u','s','ol','ul','li','h1','h2','h3','h4','h5','h6','pre','code','blockquote','hr','a','img','table','thead','tbody','tfoot','tr','th','td','div','span','sub','sup','del','ins','mark','figure','figcaption','cite','q','dl','dt','dd','abbr','address'];
        // Unknown elements are dropped with their subtree: no SVG/MathML, scripts, forms or parser islands.
        if(!in_array($tag,$allowed,true))return '';
        $attributes=[];
        if($tag==='a'){
            $url=$this->safeUrl($node->getAttribute('href'),false);
            if($url!==null){$attributes['href']=$url;if(preg_match('~^https?://~i',$url)){$attributes['target']='_blank';$attributes['rel']='noopener noreferrer';}}
        }elseif($tag==='img'){
            $url=$this->safeUrl($node->getAttribute('src'),true);if($url===null)return htmlspecialchars($node->getAttribute('alt'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $attributes=['src'=>$url,'alt'=>mb_substr($node->getAttribute('alt'),0,500),'loading'=>'lazy','decoding'=>'async'];
        }
        if($node->hasAttribute('title'))$attributes['title']=mb_substr($node->getAttribute('title'),0,500);
        if(preg_match('/^h[1-6]$/',$tag)&&preg_match('/^heading-[a-z0-9-]{1,160}$/D',$node->getAttribute('id')))$attributes['id']=$node->getAttribute('id');
        if($tag==='code'&&preg_match('/^language-[a-zA-Z0-9_-]{1,40}$/D',$node->getAttribute('class')))$attributes['class']=$node->getAttribute('class');
        if(in_array($tag,['th','td'],true)&&preg_match('/^text-align:(left|right|center);?$/D',$node->getAttribute('style'),$m))$attributes['style']='text-align:'.$m[1];
        $out='<'.$tag;foreach($attributes as $name=>$value)$out.=' '.$name.'="'.htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';$out.='>';
        if(in_array($tag,['img','br','hr'],true))return $out;
        foreach($node->childNodes as $child)$out.=$this->safeNode($child,$depth+1);
        return $out.'</'.$tag.'>';
    }

    private function safeUrl(string $value,bool $image): ?string
    {
        $url=trim(html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8'));
        $decoded=rawurldecode($url);
        if($url===''||strlen($url)>2048||preg_match('/[\x00-\x20\x7f\\\\]/',$url)||preg_match('/[\x00-\x1f\x7f\\\\]/',$decoded))return null;
        if(str_starts_with($url,'/')&&!str_starts_with($url,'//')&&!str_starts_with($decoded,'//'))return $url;
        if(!$image&&preg_match('/^#[a-zA-Z0-9_-]+$/D',$url))return $url;
        if(!$image&&str_starts_with($url,'mailto:')&&filter_var(substr($url,7),FILTER_VALIDATE_EMAIL))return $url;
        $parts=parse_url($url);
        if(!is_array($parts)||!in_array(strtolower($parts['scheme']??''),$image?['https']:['http','https'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))return null;
        return $url;
    }

    private function protectCode(string $html): string
    {
        $key=$this->fragmentPrefix.count($this->codeFragments).'Z';$this->codeFragments[$key]=$html;return $key;
    }

    private function renderCodeBlocks(string $html): string
    {
        return preg_replace_callback('/```(\w*)\n(.*?)```/s',function($m){
            $lang=$m[1]?' class="language-'.e($m[1]).'"':'';
            return "\n\n".$this->protectCode('<pre><code'.$lang.'>'.e($m[2]).'</code></pre>')."\n\n";
        },$html)??$html;
    }

    private function renderHeadings(string $html): string
    {
        return preg_replace_callback('/^(#{1,6})\s+(.+)$/m', function ($m) {
            $level = strlen($m[1]);
            $text = trim($m[2]);
            $id = 'heading-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($text));
            $id = trim($id, '-');
            return '<h' . $level . ' id="' . $id . '">' . e($text) . '</h' . $level . '>';
        }, $html);
    }

    private function renderHorizontalRules(string $html): string
    {
        return preg_replace('/^---$/m', '<hr>', $html);
    }

    private function renderUnorderedLists(string $html): string
    {
        $html = preg_replace_callback('/^(?:[-*+]\s+.*(?:\n|$))+/m', function ($m) {
            $items = preg_split('/\n[-*+]\s+/', trim($m[0]));
            $lis = '';
            foreach ($items as $i => $item) {
                if ($i === 0) {
                    $item = preg_replace('/^[-*+]\s+/', '', $item);
                }
                $lis .= '<li>' . e(trim($item)) . '</li>';
            }
            return '<ul>' . $lis . '</ul>';
        }, $html);
        return $html;
    }

    private function renderOrderedLists(string $html): string
    {
        $html = preg_replace_callback('/^(?:\d+\.\s+.*(?:\n|$))+/m', function ($m) {
            $items = preg_split('/\n\d+\.\s+/', trim($m[0]));
            $lis = '';
            foreach ($items as $i => $item) {
                if ($i === 0) {
                    $item = preg_replace('/^\d+\.\s+/', '', $item);
                }
                $lis .= '<li>' . e(trim($item)) . '</li>';
            }
            return '<ol>' . $lis . '</ol>';
        }, $html);
        return $html;
    }

    private function renderInlineFormatting(string $html): string
    {
        $html=preg_replace_callback('/`([^`]+)`/',fn($m)=>$this->protectCode('<code>'.e($m[1]).'</code>'),$html)??$html;
        $html=preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/',function($m){
            return '<img src="'.e($m[2]).'" alt="'.e($m[1]).'" loading="lazy">';
        },$html)??$html;
        $html=preg_replace_callback('/(?<!!)\[([^\]]+)\]\(([^)]+)\)/',function($m){
            return '<a href="'.e($m[2]).'">'.e($m[1]).'</a>';
        },$html)??$html;
        $html=preg_replace('/\*\*(.+?)\*\*/','<strong>$1</strong>',$html)??$html;
        return preg_replace('/\*(.+?)\*/','<em>$1</em>',$html)??$html;
    }

    private function renderParagraphs(string $html): string
    {
        $blocks = preg_split('/\n{2,}/', $html);
        $result = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') continue;
            if (str_starts_with($block,$this->fragmentPrefix) || preg_match('/^<(h[1-6]|ul|ol|li|pre|blockquote|hr|div|table|p)/', $block)) {
                $result[] = $block;
            } else {
                $result[] = '<p>' . $block . '</p>';
            }
        }
        return implode("\n", $result);
    }

    private function resolveAssetPath(string $path): string
    {
        return $path;
    }
}
