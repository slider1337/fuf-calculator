<?php

declare(strict_types=1);
namespace App\Domain\Form;

/** Exact fixed point values, at most six decimals, never converted through float. */
final class Decimal
{
    public static function normalize(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) { return null; }
        $value = (string) $value;
        if (!preg_match('/^-?\d{1,12}(?:\.\d{1,6})?$/D', $value)) { return null; }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');
        $negative = str_starts_with($whole, '-');
        $whole = ltrim($whole, '-0'); $whole = $whole === '' ? '0' : $whole;
        $fraction = rtrim($fraction, '0');
        return ($negative && ($whole !== '0' || $fraction !== '') ? '-' : '') . $whole . ($fraction !== '' ? '.' . $fraction : '');
    }
    /** Arbitrary-size exact decimal sum, without float or extension requirements.
     * @param list<string|int> $values
     */
    public static function sum(array $values): string
    {
        $positive = '0'; $negative = '0';
        $add = static function (string $a, string $b): string {
            $a=strrev($a);$b=strrev($b);$carry=0;$out='';
            for($i=0;$i<max(strlen($a),strlen($b))||$carry;$i++){$n=(int)($a[$i]??0)+(int)($b[$i]??0)+$carry;$out.=($n%10);$carry=intdiv($n,10);}
            return strrev($out);
        };
        foreach($values as $v){[$whole,$fraction]=array_pad(explode('.',ltrim((string)$v,'-')),2,'');$scaled=ltrim($whole.str_pad($fraction,6,'0'),'0')?:'0';if(str_starts_with((string)$v,'-')){$negative=$add($negative,$scaled);}else{$positive=$add($positive,$scaled);}}
        $cmp=strlen($positive)<=>strlen($negative);if($cmp===0){$cmp=strcmp($positive,$negative);} $minus=$cmp<0;
        $a=strrev($minus?$negative:$positive);$b=strrev($minus?$positive:$negative);$out='';$borrow=0;
        for($i=0;$i<strlen($a);$i++){$n=(int)$a[$i]-(int)($b[$i]??0)-$borrow;$borrow=$n<0?1:0;$out.=($n+10)%10;}
        $out=ltrim(strrev($out),'0')?:'0';$out=str_pad($out,7,'0',STR_PAD_LEFT);$whole=ltrim(substr($out,0,-6),'0')?:'0';$fraction=rtrim(substr($out,-6),'0');
        return ($minus&&($whole!=='0'||$fraction!=='')?'-':'').$whole.($fraction!==''?'.'.$fraction:'');
    }
    public static function compare(string $a, string $b): int
    {
        $scaled = static function (string $v): int {
            [$whole, $fraction] = array_pad(explode('.', ltrim($v, '-')), 2, '');
            return ((int) $whole * 1000000 + (int) str_pad($fraction, 6, '0')) * (str_starts_with($v, '-') ? -1 : 1);
        };
        return $scaled($a) <=> $scaled($b);
    }
}
