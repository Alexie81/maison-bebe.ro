<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use RuntimeException;

final class AccountingStockReportService
{
    public function data(int $variantId, int $warehouseId): array
    {
        $pdo=Database::connection();
        $header=$pdo->prepare("SELECT v.id variant_id,v.sku,p.name product_name,p.is_gift_box,w.name warehouse_name,
            COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') variant_name,
            COALESCE(b.current_quantity,0) current_quantity,COALESCE(b.current_accounting_value,0) current_accounting_value,
            COALESCE(b.calculated_unit_cost,0) calculated_unit_cost,b.projection_version
            FROM product_variants v JOIN products p ON p.id=v.product_id CROSS JOIN accounting_warehouses w
            LEFT JOIN accounting_stock_balances b ON b.product_variant_id=v.id AND b.warehouse_id=w.id
            LEFT JOIN variant_option_values vov ON vov.variant_id=v.id LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
            LEFT JOIN product_options po ON po.id=ov.option_id WHERE v.id=? AND w.id=? GROUP BY v.id,w.id");
        $header->execute([$variantId,$warehouseId]);$item=$header->fetch();
        if(!$item)throw new RuntimeException('Fișa de stoc nu a fost găsită.');
        $movements=$pdo->prepare("SELECT m.*,av.calculated_unit_cost,av.calculated_movement_value,av.balance_quantity_after,av.balance_value_after
            FROM accounting_stock_movements m LEFT JOIN accounting_stock_valuations av ON av.movement_id=m.id AND av.valuation_run_id=?
            WHERE m.product_variant_id=? AND m.warehouse_id=? ORDER BY m.effective_date,m.effective_time,m.posted_at,m.id");
        $movements->execute([(int)$item['projection_version'],$variantId,$warehouseId]);
        return [$item,$movements->fetchAll()];
    }

    public function pdf(int $variantId,int $warehouseId): string
    {
        [$item,$movements]=$this->data($variantId,$warehouseId);
        $pages=array_chunk($movements,32)?:[[]];$streams=[];
        foreach($pages as $pageIndex=>$rows){
            $content="BT\n/F2 15 Tf\n1 0 0 1 38 800 Tm\n(".$this->escape('FISA DE STOC CONTABILA').") Tj\nET\n";
            $this->line($content,38,786,557,786);
            $this->text($content,38,766,10,'SKU: '.$item['sku'].' | '.$item['product_name'].' / '.$item['variant_name'],true);
            $this->text($content,38,749,8,'Gestiune: '.$item['warehouse_name'].' | Sold: '.$this->n($item['current_quantity'],0).' buc | Cost: '.$this->n($item['calculated_unit_cost'],2).' | Valoare: '.$this->n($item['current_accounting_value'],2));
            $this->text($content,38,727,7,'Data       Tip                       Document          Intrare   Iesire    Cost       Sold       Valoare',true);
            $y=711;
            foreach($rows as $row){
                $document=trim((string)($row['source_document_number']??''));
                $line=sprintf('%-10s %-25s %-16s %8s %8s %10s %10s %11s',date('d.m.Y',strtotime($row['effective_date'])),substr($row['movement_type'],0,25),substr($document,0,16),$this->n($row['quantity_in'],0),$this->n($row['quantity_out'],0),$this->n($row['calculated_unit_cost'],2),$this->n($row['balance_quantity_after'],0),$this->n($row['balance_value_after'],2));
                $this->text($content,38,$y,6,$line);$y-=19;
            }
            $this->text($content,470,25,7,'Pagina '.($pageIndex+1).' / '.count($pages));
            $streams[]=$content;
        }
        return $this->build($streams);
    }

    private function n(mixed $value,int $scale):string{return number_format((float)$value,$scale,'.','');}
    private function line(string &$content,float $x1,float $y1,float $x2,float $y2):void{$content.=sprintf('%.1F %.1F m %.1F %.1F l S',$x1,$y1,$x2,$y2)."\n";}
    private function text(string &$content,float $x,float $y,int $size,string $value,bool $bold=false):void{$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;$content.="BT\n/".($bold?'F2':'F1')." {$size} Tf\n1 0 0 1 {$x} {$y} Tm\n(".$this->escape($ascii).") Tj\nET\n";}
    private function escape(string $value):string{return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$value);}
    private function build(array $streams):string
    {
        $objects=[1=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',2=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>'];$pageIds=[];$next=3;
        foreach($streams as $stream){$page=$next++;$content=$next++;$pageIds[]=$page;$objects[$page]='';$objects[$content]='<< /Length '.strlen($stream).">>\nstream\n{$stream}endstream";}
        $pages=$next++;$catalog=$next++;foreach($pageIds as $page)$objects[$page]='<< /Type /Page /Parent '.$pages.' 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 1 0 R /F2 2 0 R >> >> /Contents '.($page+1).' 0 R >>';
        $objects[$pages]='<< /Type /Pages /Kids ['.implode(' ',array_map(static fn($id)=>$id.' 0 R',$pageIds)).'] /Count '.count($pageIds).' >>';$objects[$catalog]='<< /Type /Catalog /Pages '.$pages.' 0 R >>';ksort($objects);
        $pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.="{$id} 0 obj\n{$object}\nendobj\n";}$xref=strlen($pdf);$size=max(array_keys($objects))+1;$pdf.="xref\n0 {$size}\n0000000000 65535 f \n";for($id=1;$id<$size;$id++)$pdf.=isset($offsets[$id])?sprintf('%010d 00000 n',$offsets[$id])."\n":"0000000000 00000 f \n";return $pdf."trailer\n<< /Size {$size} /Root {$catalog} 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
