<?php
 namespace App\Services\Partner;

use App\Enums\IsMain;
use App\Models\Partner\PartnerAddress;

class PartnerAddressService
{
    public function allPartnerAddresses(int $partnerableId, string $partnerableType)
    {
        $partnerAddresses =PartnerAddress::where('partnerable_id',$partnerableId)->where('partnerable_type', $partnerableType)->get();
        return $partnerAddresses;
    }
    public function editPartnerAddress(int $id)
    {
        return PartnerAddress::find($id);;
    }
    public function createPartnerAddress(array $data)
    {
        $partnerAddress=PartnerAddress::create([
            'partnerable_id' => $data['partnerableId'],
            'partnerable_type' => $data['partnerableType'],
            'address' => $data['address'],
            'city' => $data['city']??null,
            'state' => $data['state']??null,
            'zip_code' => $data['zipCode']??null,
            'is_main' => IsMain::from($data['isMain'])->value,
        ]);

        if ($partnerAddress->is_main == IsMain::MAIN) {
            PartnerAddress::whereNot('id', $partnerAddress->id)->update(['is_main' => IsMain::SECONDARY]);
        }

        return $partnerAddress;
    }
    public function updatePartnerAddress(int $id , array $data)
    {
        $partnerAddress = PartnerAddress::find($id);
        $partnerAddress->update([
            'address' => $data['address'],
            'city' => $data['city']??null,
            'state' => $data['state']??null,
            'zip_code' => $data['zipCode']??null,
            'is_main' => IsMain::from($data['isMain'])->value,
        ]);
        if ($partnerAddress->is_main == IsMain::MAIN) {
            PartnerAddress::whereNot('id', $partnerAddress->id)->update(['is_main' => IsMain::SECONDARY]);
        }
        return $partnerAddress;
    }
    public function deletePartnerAddress(int $id)
    {
        PartnerAddress::find($id)->delete();
    }
}
