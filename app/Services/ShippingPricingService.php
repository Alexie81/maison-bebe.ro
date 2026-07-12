<?php
declare(strict_types=1);
namespace MaisonBebe\Services;
use MaisonBebe\Core\Database;

final class ShippingPricingService {
 public function config():array{
  $row=Database::connection()->query("SELECT config_json FROM shipping_providers WHERE is_enabled=1 ORDER BY is_default DESC,id LIMIT 1")->fetchColumn();
  $c=json_decode((string)$row,true)?:[];
  return ['standard_minor'=>max(0,(int)($c['base_price_minor']??2500)),'free_threshold_minor'=>max(0,(int)($c['free_threshold_minor']??50000)),'free_enabled'=>array_key_exists('free_shipping_enabled',$c)?(bool)$c['free_shipping_enabled']:true];
 }
 public function cost(int $eligibleTotalMinor):int{$c=$this->config();return $c['free_enabled']&&$c['free_threshold_minor']>0&&$eligibleTotalMinor>=$c['free_threshold_minor']?0:$c['standard_minor'];}
}