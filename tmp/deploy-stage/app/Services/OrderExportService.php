<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

final class OrderExportService
{
    private const ORDER_LABELS = [
        'new'=>'Nouă','confirmed'=>'Confirmată','processing'=>'În pregătire',
        'ready_for_shipping'=>'Pregătită pentru curier','shipped'=>'Expediată',
        'delivered'=>'Livrată','cancelled'=>'Anulată','return_requested'=>'Retur solicitat',
        'returned'=>'Returnată','partially_refunded'=>'Rambursată parțial','refunded'=>'Rambursată',
    ];
    private const PAYMENT_LABELS = [
        'unpaid'=>'Neplătită','pending'=>'În așteptare','paid'=>'Plătită',
        'failed'=>'Eșuată','partially_refunded'=>'Rambursată parțial','refunded'=>'Rambursată',
    ];

    public function csv(array $orders): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['Comandă','Client','Email','Telefon','Tip plată','Plată','Status','Data','Total RON'], ';');
        foreach ($orders as $order) {
            fputcsv($stream, [
                $order['order_number'], trim(($order['first_name'] ?? '').' '.($order['last_name'] ?? '')),
                $order['email'], $order['phone'], $this->method((string)$order['payment_method']),
                self::PAYMENT_LABELS[$order['payment_status']] ?? $order['payment_status'],
                self::ORDER_LABELS[$order['order_status']] ?? $order['order_status'],
                date('d.m.Y H:i', strtotime((string)$order['created_at'])),
                number_format(((int)$order['grand_total_minor']) / 100, 2, ',', '.'),
            ], ';');
        }
        rewind($stream);
        return (string) stream_get_contents($stream);
    }

    public function pdf(array $orders): string
    {
        $chunks = array_chunk($orders, 18);
        if (!$chunks) $chunks = [[]];
        $objects = [];
        $pageIds = [];
        $nextId = 3;
        foreach ($chunks as $page => $rows) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageIds[] = $pageId;
            $stream = $this->page($rows, $page + 1, count($chunks));
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 1 0 R /F2 '.(2 + count($chunks) * 2 + 1).' 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = "<< /Length ".strlen($stream).">>\nstream\n".$stream."endstream";
        }
        $boldId = 2 + count($chunks) * 2 + 1;
        $catalogId = $boldId + 1;
        $pagesId = $catalogId + 1;
        foreach ($pageIds as $id) $objects[$id] = str_replace('/Parent 2 0 R','/Parent '.$pagesId.' 0 R',$objects[$id]);
        $objects[1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$boldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[$catalogId] = '<< /Type /Catalog /Pages '.$pagesId.' 0 R >>';
        $objects[$pagesId] = '<< /Type /Pages /Kids ['.implode(' ',array_map(static fn($id)=>$id.' 0 R',$pageIds)).'] /Count '.count($pageIds).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) { $offsets[$id] = strlen($pdf); $pdf .= $id." 0 obj\n".$object."\nendobj\n"; }
        $xref = strlen($pdf); $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id=1;$id<$size;$id++) $pdf .= isset($offsets[$id]) ? sprintf('%010d 00000 n',$offsets[$id])."\n" : "0000000000 00000 f \n";
        $pdf .= "trailer\n<< /Size {$size} /Root {$catalogId} 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        return $pdf;
    }

    private function page(array $orders, int $page, int $pages): string
    {
        $c="q\n0.969 0.949 0.925 rg\n0 770 595 72 re f\n";
        $this->text($c,36,807,17,'MAISON BEBE - COMENZI',true);
        $this->text($c,36,786,8,'Export operational generat la '.date('d.m.Y H:i'));
        $this->text($c,559,807,9,'Pagina '.$page.' / '.$pages,true,'right');
        $headers=[['Comanda',36],['Client / plata',150],['Status',344],['Data',444],['Total',559]];
        $c.="0.541 0.435 0.369 rg\n36 735 523 28 re f\n";
        foreach($headers as [$label,$x])$this->text($c,$x,745,8,$label,true,$x===559?'right':'left',[255,255,255]);
        $y=720;
        foreach($orders as $index=>$order){
            if($index%2===0)$c.="0.985 0.974 0.960 rg\n36 ".($y-29)." 523 38 re f\n";
            $this->text($c,40,$y-7,8,(string)$order['order_number'],true);
            $client=trim((string)(($order['first_name']??'').' '.($order['last_name']??''))) ?: (string)$order['email'];
            $this->text($c,150,$y-5,8,$this->cut($client,29),true);
            $this->text($c,150,$y-18,7,$this->method((string)$order['payment_method']).' / '.(self::PAYMENT_LABELS[$order['payment_status']]??$order['payment_status']));
            $this->text($c,344,$y-7,8,$this->cut((string)(self::ORDER_LABELS[$order['order_status']]??$order['order_status']),20));
            $this->text($c,444,$y-7,8,date('d.m.Y H:i',strtotime((string)$order['created_at'])));
            $this->text($c,559,$y-7,8,number_format(((int)$order['grand_total_minor'])/100,2,',','.').' lei',true,'right');
            $c.="0.870 0.830 0.790 RG\n36 ".($y-30)." m 559 ".($y-30)." l S\n";$y-=38;
        }
        if(!$orders)$this->text($c,297,430,13,'Nu exista comenzi pentru filtrele selectate.',false,'center');
        $this->text($c,36,25,7,'Maison Bebe - document intern',false);
        $this->text($c,559,25,7,count($orders).' comenzi pe aceasta pagina',false,'right');
        return $c."Q\n";
    }

    private function method(string $method): string { return $method==='stripe' ? 'Card online' : 'Ramburs la curier'; }
    private function cut(string $value,int $length):string{return mb_strlen($value)>$length?mb_substr($value,0,$length-1).'…':$value;}
    private function text(string &$c,float $x,float $y,int $size,string $value,bool $bold=false,string $align='left',array $color=[61,49,43]):void
    {
        $value=$this->ascii($value);if($align==='right')$x-=strlen($value)*$size*.49;elseif($align==='center')$x-=strlen($value)*$size*.245;
        $c.=sprintf('%.3F %.3F %.3F rg', $color[0]/255,$color[1]/255,$color[2]/255)."\nBT\n".($bold?'/F2':'/F1')." {$size} Tf\n1 0 0 1 {$x} {$y} Tm\n(".$this->escape($value).") Tj\nET\n";
    }
    private function ascii(string $value):string{return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:preg_replace('/[^\x20-\x7E]/','',$value)?:'';}
    private function escape(string $value):string{return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$value);}
}