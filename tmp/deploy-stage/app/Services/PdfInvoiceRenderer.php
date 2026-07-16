<?php
declare(strict_types=1);
namespace MaisonBebe\Services;
use MaisonBebe\Core\Database;
use RuntimeException;

final class PdfInvoiceRenderer {
 private array $themes=[
  ['Clasic curat',[183,124,116],[248,238,233],[47,41,40]],
  ['Boutique verde',[82,115,107],[234,242,239],[36,54,51]],
  ['Premium cadou',[196,138,58],[255,243,225],[51,39,27]],
  ['Compact operational',[65,95,140],[238,244,250],[30,42,57]],
  ['Modern Maison Bebe',[138,111,142],[244,238,246],[50,42,53]]
 ];
 public function render(array $i,array $items,string $path):void{
  $issuer=json_decode((string)$i['issuer_snapshot_json'],true)?:[];$client=json_decode((string)$i['customer_snapshot_json'],true)?:[];
  $style=$this->style((int)($i['template_version_id']??0));[$theme,$primary,$accent,$dark]=$this->themes[$style];
  $pay=$this->payment((int)$i['order_id']);$paid=($pay['payment_status']??'')==='paid';
  $c="q\n";$this->fill($c,$accent);$this->rect($c,0,770,595,72,true);
  if($style===1){$this->fill($c,$primary);$this->rect($c,0,0,13,842,true);}
  if($style===2){$this->fill($c,$primary);$this->rect($c,42,812,511,4,true);}
  if($style===3){$this->fill($c,$dark);$this->rect($c,0,770,595,72,true);}
  if($style===4){$this->fill($c,$primary);$this->rect($c,500,770,95,72,true);}
  $hc=$style===3?[255,255,255]:$dark;
  $this->text($c,44,811,16,'MAISON BEBE',true,$hc);$this->text($c,44,792,8,'PREMIUM BABY BOUTIQUE',false,$hc);
  $this->text($c,551,811,22,'FACTURA',true,$hc,'right');$this->text($c,551,790,9,(string)($i['number']??''),true,$hc,'right');$this->text($c,551,777,7,$theme,false,$hc,'right');
  $this->label($c,44,742,'DATE FACTURA',$primary);$this->text($c,44,724,9,'Emisa: '.($i['issue_date']??''),false,$dark);$this->text($c,190,724,9,'Scadenta: '.($i['due_date']??''),false,$dark);
  $badge=$paid?'ACHITATA':'NEACHITATA';$badgeColor=$paid?[72,120,88]:[166,91,75];$this->fill($c,$badgeColor);$this->rect($c,432,711,119,27,true);$this->text($c,491,720,9,$badge,true,[255,255,255],'right');
  $method=($pay['payment_method']??'')==='stripe'?'Card online':((($pay['payment_method']??'')==='cod')?'Ramburs la curier':ucfirst((string)($pay['payment_method']??'')));
  $this->text($c,551,699,7,'Plata: '.$method,false,$dark,'right');
  $this->panel($c,44,578,247,108,$accent,$primary);$this->panel($c,304,578,247,108,[255,255,255],$primary);
  $this->label($c,57,666,'FURNIZOR',$primary);$this->text($c,57,647,10,(string)($issuer['legal_name']??''),true,$dark);$this->text($c,57,631,8,'CUI: '.($issuer['tax_id']??''),false,$dark);$this->text($c,57,617,8,'Reg. Com.: '.($issuer['registration_number']??''),false,$dark);$this->wrap($c,57,603,215,$this->address($issuer['address']??[]),8,$dark,11);$this->text($c,57,585,7,(string)($issuer['billing_email']??''),false,$dark);
  $this->label($c,317,666,'CLIENT',$primary);$name=(string)($client['company_name']??trim(($client['first_name']??'').' '.($client['last_name']??'')));$this->text($c,317,647,10,$name?:((string)($client['email']??'')),true,$dark);$this->text($c,317,631,8,'Email: '.($client['email']??''),false,$dark);$this->text($c,317,617,8,'Telefon: '.($client['phone']??''),false,$dark);$this->text($c,317,603,8,'CUI/CNP: '.($client['tax_id']??''),false,$dark);$this->wrap($c,317,589,215,$this->address($client['address']??[]),8,$dark,11);
  $y=544;$this->fill($c,$primary);$this->rect($c,44,$y,507,25,true);foreach([['Produs / serviciu',51],['Cant.',376],['Pret',423],['Total',493]] as [$t,$x])$this->text($c,$x,$y+8,8,$t,true,[255,255,255]);$y-=25;$alt=false;
  foreach($items as $item){$h=32;if($y-$h<150)break;if($alt){$this->fill($c,[250,248,246]);$this->rect($c,44,$y-$h+1,507,$h,true);}$this->stroke($c,[222,212,204]);$this->line($c,44,$y-$h+1,551,$y-$h+1);$this->wrap($c,51,$y-12,305,(string)$item['name'],8,$dark,10);$this->text($c,395,$y-12,8,rtrim(rtrim((string)$item['quantity'],'0'),'.'),false,$dark,'right');$this->text($c,477,$y-12,8,$this->money((int)$item['unit_price_minor']),false,$dark,'right');$this->text($c,544,$y-12,8,$this->money((int)$item['total_minor']),true,$dark,'right');$y-=$h;$alt=!$alt;}
  $t=max(80,$y-15);$this->fill($c,$accent);$this->rect($c,338,$t-75,213,76,true);$this->text($c,445,$t-18,9,'Subtotal',false,$dark,'right');$this->text($c,544,$t-18,9,$this->money((int)$i['subtotal_minor']),true,$dark,'right');
  if((int)$i['discount_minor']>0){$this->text($c,445,$t-35,9,'Reducere',false,$dark,'right');$this->text($c,544,$t-35,9,'-'.$this->money((int)$i['discount_minor']),true,$dark,'right');}
  $this->text($c,445,$t-50,9,'TVA',false,$dark,'right');$this->text($c,544,$t-50,9,$this->money((int)$i['vat_minor']),true,$dark,'right');$this->stroke($c,$primary);$this->line($c,350,$t-57,539,$t-57);$this->text($c,445,$t-72,11,'TOTAL',true,$dark,'right');$this->text($c,544,$t-72,12,$this->money((int)$i['grand_total_minor']),true,$primary,'right');
  $this->label($c,44,$t-12,'MENTIUNI',$primary);$this->wrap($c,44,$t-29,270,(string)($i['notes']??'Factura emisa electronic.'),8,$dark,11);
  $this->stroke($c,[218,208,200]);$this->line($c,44,39,551,39);$this->text($c,44,24,7,'Factura pentru client - Maison Bebe',false,[115,105,100]);$this->text($c,551,24,7,'Document generat electronic',false,[115,105,100],'right');$c.="Q\n";$this->write($c,$path);
 }
 private function payment(int $id):array{$s=Database::connection()->prepare('SELECT payment_status,payment_method FROM orders WHERE id=?');$s->execute([$id]);return $s->fetch()?:[];}
 private function style(int $v):int{if($v<1)return 0;$pdo=Database::connection();$s=$pdo->prepare('SELECT t.id FROM invoice_template_versions v JOIN invoice_templates t ON t.id=v.template_id WHERE v.id=?');$s->execute([$v]);$id=(int)$s->fetchColumn();$ids=array_map('intval',$pdo->query('SELECT id FROM invoice_templates ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));$p=array_search($id,$ids,true);return $p===false?0:min(4,(int)$p);}
 private function address(array $a):string{return implode(', ',array_filter([$a['line1']??null,$a['line2']??null,$a['city']??null,$a['county']??null,$a['postal_code']??null]));}
 private function money(int $n):string{return number_format($n/100,2,',','.').' RON';}
 private function color(array $r):string{return sprintf('%.3F %.3F %.3F',$r[0]/255,$r[1]/255,$r[2]/255);}
 private function fill(string &$c,array $r):void{$c.=$this->color($r)." rg\n";}private function stroke(string &$c,array $r):void{$c.=$this->color($r)." RG\n";}
 private function rect(string &$c,float $x,float $y,float $w,float $h,bool $f=false):void{$c.=sprintf('%.2F %.2F %.2F %.2F re %s',$x,$y,$w,$h,$f?'f':'S')."\n";}private function line(string &$c,float $a,float $b,float $d,float $e):void{$c.=sprintf('%.2F %.2F m %.2F %.2F l S',$a,$b,$d,$e)."\n";}
 private function panel(string &$c,float $x,float $y,float $w,float $h,array $f,array $b):void{$this->fill($c,$f);$this->rect($c,$x,$y,$w,$h,true);$this->stroke($c,$b);$this->rect($c,$x,$y,$w,$h);}
 private function label(string &$c,float $x,float $y,string $t,array $r):void{$this->text($c,$x,$y,7,$t,true,$r);}
 private function text(string &$c,float $x,float $y,int $s,string $t,bool $b,array $r,string $a='left'):void{$v=$this->ascii($t);if($a==='right')$x-=strlen($v)*$s*.49;$c.="BT\n".$this->color($r)." rg\n".($b?'/F2':'/F1').' '.$s." Tf\n1 0 0 1 ".round($x,2).' '.round($y,2)." Tm\n(".$this->escape($v).") Tj\nET\n";}
 private function wrap(string &$c,float $x,float $y,float $w,string $t,int $s,array $r,int $l):void{$m=max(10,(int)floor($w/($s*.52)));$words=preg_split('/\s+/',trim($this->ascii($t)))?:[];$line='';foreach($words as $word){$test=trim($line.' '.$word);if(strlen($test)>$m&&$line!==''){$this->text($c,$x,$y,$s,$line,false,$r);$y-=$l;$line=$word;}else $line=$test;}if($line!=='')$this->text($c,$x,$y,$s,$line,false,$r);}
 private function ascii(string $v):string{return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v)?:preg_replace('/[^\x20-\x7E]/','',$v)?:'';}private function escape(string $v):string{return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$v);}
 private function write(string $cmd,string $path):void{$o=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',3=>'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>',4=>'<< /Length '.strlen($cmd).">>\nstream\n".$cmd.'endstream',5=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',6=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'];$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$off=[0];foreach($o as $id=>$ob){$off[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$ob."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 7\n0000000000 65535 f \n";for($j=1;$j<=6;$j++)$pdf.=sprintf('%010d 00000 n',$off[$j])."\n";$pdf.="trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";$dir=dirname($path);if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Directorul nu poate fi creat.');if(file_put_contents($path,$pdf,LOCK_EX)===false)throw new RuntimeException('PDF-ul nu a putut fi salvat.');}
}