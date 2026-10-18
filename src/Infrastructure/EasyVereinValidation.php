<?php
declare(strict_types=1);
namespace App\Infrastructure;

/**
 * Reduce untrusted validation responses to field names and fixed local reason codes.
 * Never retain response text, submitted values, URLs, tokens or contact data.
 */
final class EasyVereinValidation
{
    public const FIELDS = ['name','start','end','all_day','calendar','location_name','location_object','description','prologue','min_participators','max_participators','start_participation','end_participation','access','note','creator','show_memberarea','is_public','is_reservation','reservation_parent_event','allow_multiple_price_groups_per_participation','confirmation_to_addresses','send_mail_check','participation_event','participation_address','state','is_companion','companion_name','companion_email','parent','participation_price_groups','price_group','pieces'];
    /** @return list<array{field:string,reason:string}> */
    public static function fromBody(string $body): array
    {
        if (strlen($body)>65536) return [];
        try {$data=json_decode($body,true,16,JSON_THROW_ON_ERROR);} catch (\Throwable) {return [];}
        if (!is_array($data)) return [];
        $found=[];
        $walk=function(mixed $value,?string $field=null,int $depth=0) use (&$walk,&$found): void {
            if ($depth>8 || count($found)>=24) return;
            if (is_array($value)) {
                // Accept both DRF field dictionaries and structured field/message errors.
                $named=$value['field']??$value['attribute']??null;
                if (is_string($named)&&in_array($named,self::FIELDS,true)) $field=$named;
                foreach($value as $key=>$child) {
                    if(in_array($key,['field','attribute'],true)) continue;
                    $next=is_string($key)&&in_array($key,self::FIELDS,true)?$key:$field;
                    if(in_array($key,['non_field_errors','detail'],true)&&$next===null) $next='event';
                    $walk($child,$next,$depth+1);
                }
            } elseif (is_string($value)) {
                $text=mb_strtolower($value);
                if(str_contains($text,'anmeldezeitraum')&&str_contains($text,'überschneid')){$found['end_participation:overlap']=['field'=>'end_participation','reason'=>'overlap'];return;}
                $field??='event';
                $reason=match(true) {
                    (bool)preg_match('/required|pflicht|erforderlich/',$text)=>'required',
                    (bool)preg_match('/blank|empty|leer/',$text)=>'blank',
                    (bool)preg_match('/null/',$text)=>'null',
                    (bool)preg_match('/does not exist|not found|existiert nicht|nicht gefunden|invalid pk/',$text)=>'reference',
                    (bool)preg_match('/permission|not allowed|berechtigung|nicht erlaubt/',$text)=>'permission',
                    (bool)preg_match('/greater|less than|at least|at most|maximum|minimum|max_length|min_length|höchstens|mindestens/',$text)=>'range',
                    default=>'invalid',
                };
                $found[$field.':'.$reason]=['field'=>$field,'reason'=>$reason];
            }
        };
        $walk($data);return array_values($found);
    }
}
