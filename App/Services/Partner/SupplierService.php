<?php
namespace App\Services\Partner;

use App\Filters\Partner\FilterSupplier;
use App\Models\Partner\Supplier;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;


class SupplierService
{
    protected $supplierContactService;
    protected $supplierAddressService;

   public function __construct(PartnerContactService $supplierContactService, PartnerAddressService $supplierAddressService)
    {
        $this->supplierContactService = $supplierContactService;
        $this->supplierAddressService = $supplierAddressService;
    }
    public function allSuppliers()
    {

        $perPage = request()->get('pageSize', 10);

        $suppliers = QueryBuilder::for(Supplier::class)
            ->allowedFilters([
                AllowedFilter::custom('search', new FilterSupplier()),
            ])
            ->paginate($perPage); // Pagination applied here

        return $suppliers;

    }

    public function editSupplier(int $id)
    {
        return Supplier::with(['contacts', 'addresses'])->find($id);;
    }
    public function createSupplier(array $data)
    {
        $supplier=Supplier::create([
            'name'=>$data['name'],
            'note'=>$data['note']??null,
            'tax_number'=>$data['taxNumber']??null,

        ]);
        if (isset($data['contacts'])) {
            foreach ($data['contacts'] as $contact) {
                $this->supplierContactService->createPartnerContact([
                    'partnerableId'=>$supplier->id,
                    'partnerableType'=>Supplier::class,
                     ...$contact
                ]);
            }
        }

        if (isset($data['addresses'])) {
            foreach ($data['addresses'] as $address) {
                $this->supplierAddressService->createPartnerAddress([
                    'partnerableId'=>$supplier->id,
                    'partnerableType'=>Supplier::class,
                    ...$address
                ]);
            }
        }

        return $supplier;
    }
    public function updateSupplier(int $id,array $data )
    {
        $supplier=Supplier::find($id);
        $supplier->update([
            'name'=>$data['name'],
            'note'=>$data['note']??null,
            'tax_number'=>$data['taxNumber']??null,
        ]);
        return $supplier;
    }
    public function deleteSupplier(int $id): void
    {
        Supplier::find($id)->delete();
    }
}
