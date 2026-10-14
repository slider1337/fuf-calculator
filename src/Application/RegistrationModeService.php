<?php

declare(strict_types=1);
namespace App\Application;
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
final readonly class RegistrationModeService
{
    public function __construct(private AccessPolicy $policy,private SqliteWorkflowRepository $store) {}
    public static function validate(mixed $mode): string
    {
        if(!is_string($mode)||!in_array($mode,['clubdesk_csv','easyverein'],true)){throw new ValidationException(['registrationMode'=>'clubdesk_csv_or_easyverein']);}return $mode;
    }
    public function read(int $trip): array
    {
        $this->policy->trip($trip,'trip.view');$m=$this->policy->metadata($trip);
        return ['registrationMode'=>$m['registration_mode'],'revision'=>(int)$m['registration_mode_revision']];
    }
    public function change(int $trip,array $p): array
    {
        return $this->policy->writeTrip($trip,'trip.manage',function()use($trip,$p){
            $mode=self::validate($p['registrationMode']??null);$m=$this->policy->metadata($trip);
            if(($p['revision']??null)!==(int)$m['registration_mode_revision']){throw new AccessException('revision_conflict',409);}
            $reason=$this->policy->reason(is_string($p['reason']??null)?$p['reason']:'');
            if(($p['confirmed']??null)!==true){throw new ValidationException(['confirmed'=>'explicit_confirmation_required']);}
            if($mode===$m['registration_mode']){return $this->read($trip);}
            foreach(['sync_jobs','term_update_jobs','participation_jobs'] as $table){if($this->store->query("SELECT 1 FROM $table WHERE trip_id=? AND status IN ('pending','running','unknown','reconciling')",[$trip])->fetchColumn()!==false){throw new AccessException('registration_mode_operation_in_progress',409);}}
            if($mode==='clubdesk_csv'){
                if($this->store->query('SELECT 1 FROM registrations WHERE trip_id=? AND managed_booking=1',[$trip])->fetchColumn()!==false){throw new AccessException('registration_mode_managed_bookings_exist',409);}
                if($this->store->query("SELECT 1 FROM guest_approvals WHERE trip_id=? AND status='approved'",[$trip])->fetchColumn()!==false){throw new AccessException('registration_mode_guest_approvals_exist',409);}
                $this->store->query('UPDATE member_trip_settings SET enabled=0 WHERE trip_id=?',[$trip]);
            }
            $this->store->query('UPDATE trips SET registration_mode=?,registration_mode_revision=registration_mode_revision+1 WHERE id=?',[$mode,$trip]);
            $this->policy->audit('trip.registration_mode','trip',$trip,$reason.' · '.$m['registration_mode'].' → '.$mode);
            return $this->read($trip);
        });
    }
}
