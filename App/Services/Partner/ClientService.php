<?php
namespace App\Services\Partner;

use App\Enums\Client\ClientType;
use App\Filters\Partner\FilterClient;
use App\Models\Partner\Client;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;


class ClientService
{
    protected $clientService;
    protected $partnerContactService;
    protected $clientEmailService;
    protected $clientAddressService;
   public function __construct(  PartnerContactService $partnerContactService, PartnerEmailService $clientEmailService, PartnerAddressService $clientAddressService)
    {
        $this->partnerContactService = $partnerContactService;
        $this->clientEmailService = $clientEmailService;
        $this->clientAddressService = $clientAddressService;
    }
    public function allClients()
    {

        $perPage = request()->get('pageSize', 10);

        $clients = QueryBuilder::for(Client::class)
            ->allowedFilters([
                AllowedFilter::custom('search', new FilterClient()),
            ])
            ->paginate($perPage); // Pagination applied here

        return $clients;

    }

    public function editClient(int $id)
    {
        $client=Client::with(['contacts', 'addresses'])->find($id);
        return $client;
    }
    public function createClient(array $data)
    {
        $client=Client::create([
            'name'=>$data['name'],
            'note'=>$data['note'],
            'type'=>ClientType::from($data['type'])->value,
            'tax_number'=>$data['taxNumber']??null
        ]);
        if (isset($data['contacts'])) {
            foreach ($data['contacts'] as $contact) {
                $this->partnerContactService->createPartnerContact([
                    'partnerableId'=>$client->id,
                    'partnerableType'=>Client::class,
                     ...$contact
                ]);
            }
        }
        if (isset($data['addresses'])) {
            foreach ($data['addresses'] as $address) {
                $this->clientAddressService->createPartnerAddress([
                    'partnerableId'=>$client->id,
                    'partnerableType'=>Client::class,
                    ...$address
                ]);
            }
        }

        return $client;
    }
    public function updateClient(int $id,array $data )
    {
        $client=Client::find($id);
        $client->update([
            'name'=>$data['name'],
            'note'=>$data['note'],
            'type'=>\App\Enums\Client\ClientType::from($data['type'])->value,
            'tax_number'=>$data['taxNumber']??null
        ]);
        return $client;
    }
    public function deleteClient(int $id)
    {
        Client::find($id)->delete();
    }
}
