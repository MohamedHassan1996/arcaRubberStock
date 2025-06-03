<?php
namespace App\Services\Partner;

use App\Enums\IsMain;
use App\Models\Partner\PartnerContact;

class PartnerContactService
{
    public function allPartnerContacts(int $partnerableId, $partnerableType)
    {
        return PartnerContact::where('partnerable_id', $partnerableId)->where('partnerable_type', $partnerableType)->get();
    }
    public function editPartnerContact(int $id)
    {
        return PartnerContact::find($id);
    }
    public function createPartnerContact(array $data)
    {
        $clientPhone = PartnerContact::create([
            'name' => $data['name'] ?? null,
            'partnerable_id' => $data['partnerableId'],
            'partnerable_type' => $data['partnerableType'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_main' => IsMain::from($data['isMain'])->value,
            'country_code' => $data['countryCode'] ?? null,
        ]);
        if ($clientPhone->is_main == IsMain::MAIN) {
            PartnerContact::whereNot('id', $clientPhone->id)->update(['is_main' => IsMain::SECONDARY]);
        }
        return $clientPhone;
    }

    public function updatePartnerContact($id, array $data)
    {
        $clientPhone = PartnerContact::find($id);

        $clientPhone->update([
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_main' => IsMain::from($data['isMain'])->value,
            'country_code' => $data['countryCode'] ?? null,
        ]);

        if ($clientPhone->is_main == IsMain::MAIN) {
            PartnerContact::whereNot('id', $clientPhone->id)->update(['is_main' => IsMain::SECONDARY]);
        }
        return $clientPhone;
    }

    public function deletePartnerContact(int $id)
    {
        $clientPhone = PartnerContact::find($id);

        $clientPhone->delete();
    }

}
