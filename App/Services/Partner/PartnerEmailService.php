<?php
namespace App\Services\Partner;

use App\Enums\IsMain;
use App\Models\Partner\PartnerEmail;

class PartnerEmailService {
    public function allPartnerEmails(int $partnerId, $partnerType)
    {
        return PartnerEmail::where('partnerable_id', $partnerId)->where('partnerable_type', $partnerType)->get();
    }

    public function createPartnerEmail($data)
    {
        $clientEmail= PartnerEmail::create([
            'partnerable_id' => $data['partnerableId'],
            'partnerable_type' => $data['partnerableType'],
            'email' => $data['email'],
            'is_main' => IsMain::from($data['isMain'])->value,
        ]);

        if ($clientEmail->is_main == IsMain::MAIN) {
            PartnerEmail::whereNot('id', $clientEmail->id)->update(['is_main' => IsMain::SECONDARY]);
        }

        return $clientEmail;
    }

    public function editPartnerEmail(int $id)
    {
        $PartnerEmail=PartnerEmail::find($id);
        return $PartnerEmail;
    }
    public function updatePartnerEmail(int $id,array $data)
    {
        $clientEmail=PartnerEmail::find($id);
        $clientEmail->update([
            'email' => $data['email'],
            'is_main' => IsMain::from($data['isMain'])->value,
        ]);

        if ($clientEmail->is_main == IsMain::MAIN) {
            PartnerEmail::whereNot('id', $clientEmail->id)->update(['is_main' => IsMain::SECONDARY]);
        }
        return $clientEmail;
    }
    public function deletePartnerEmail($clientId): void
    {
        $PartnerEmail=PartnerEmail::find($clientId);
        $PartnerEmail->delete();
    }
}


