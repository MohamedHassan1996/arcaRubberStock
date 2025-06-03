<?php
namespace App\Services\Invoice;

//use App\Filters\Partner\FilterClient;

use App\Enums\ActionStatus;
use App\Enums\Order\DiscountType;
use App\Enums\Stock\InStockStatus;
use App\Enums\Stock\InStockType;
use App\Filters\Invoice\FilterPurchaseInvoice;
use App\Models\Stock\InStock;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PurchaseInvoiceService
{
    protected $purchaseInvoiceItemService;
    public function __construct( PurchaseInvoiceItemService $purchaseInvoiceItemService)
    {
        $this->purchaseInvoiceItemService = $purchaseInvoiceItemService;
    }
    public function allPurchaseInvoices()
    {

        $perPage = request()->get('pageSize', 10);

        $purchaseInvoices = QueryBuilder::for(InStock::class)
            ->allowedFilters([
                AllowedFilter::custom('search', new FilterPurchaseInvoice()),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('supplierId', 'supplier_id'),
            ])
            ->with(['supplier'])
            ->where('type', InStockType::PURCHASE_INVOICE->value)
            ->paginate($perPage); // Pagination applied here

        return $purchaseInvoices;

    }
    public function editPurchaseInvoice(int $id)
    {
        return InStock::where('type', InStockType::PURCHASE_INVOICE->value)
        ->with(['inStockItems'])->find($id);
    }
    public function createPurchaseInvoice(array $data)
    {

        $purchaseInvoice = InStock::create([
            'date'=>$data['invoiceDate'],
            'supplier_in_stock_number'=>$data['supplierInvoicNumber']??null,
            'supplier_id'=>$data['supplierId']??null,
            'status'=> InStockStatus::from($data['status']),
            'note'=>$data['note']??null,
            'type'=>InStockType::PURCHASE_INVOICE,
            'discount' => $data['discount']??0,
            'discount_type' => DiscountType::from($data['discountType'])
        ]);

        foreach ($data['purchaseInvoiceItems'] as $purchaseInvoiceItemData) {
            $purchaseInvoiceItem = $this->purchaseInvoiceItemService->createPurchaseInvoiceItem([
                'purchaseInvoiceId'=>$purchaseInvoice->id,
                'addToStock'=> $purchaseInvoice->status == InStockStatus::APPROVED ? true : false,
                ...$purchaseInvoiceItemData
            ]);

            $purchaseInvoice->total_cost += $purchaseInvoiceItem->cost * $purchaseInvoiceItem->quantity;
        }


        $purchaseInvoice->total_cost_after_discount = $purchaseInvoice->total_cost - $purchaseInvoice->discount_amount;

        $purchaseInvoice->save();

        return $purchaseInvoice;
    }
    public function updatePurchaseInvoice(int $id, array $data)
    {
        $purchaseInvoice= InStock::find($id);

        $prevStatus = $purchaseInvoice->status;

        $purchaseInvoice->update([
            'date'=>$data['invoiceDate'],
            'supplier_in_stock_number'=>$data['supplierInvoicNumber']??null,
            'supplier_id'=>$data['supplierId']??null,
            'status'=> InStockStatus::from($data['status'])->value,
            'note'=>$data['note']??null,
            'discount' => $data['discount']??0,
            'discount_type' => DiscountType::from($data['discountType'])
        ]);


        if(InStockStatus::APPROVED != $prevStatus){
            $purchaseInvoice->total_cost = 0;
            $purchaseInvoice->total_cost_after_discount = 0;

            foreach ($data['purchaseInvoiceItems'] as $purchaseInvoiceItem) {
                if($purchaseInvoiceItem['actionStatus'] == ActionStatus::CREATE->value) {
                    $item = $this->purchaseInvoiceItemService->createPurchaseInvoiceItem([
                        'purchaseInvoiceId'=> $purchaseInvoice->id,
                        'addToStock'=> $purchaseInvoice->status == InStockStatus::APPROVED ? true : false,
                        ...$purchaseInvoiceItem
                    ]);

                    $purchaseInvoice->total_cost += $item->cost * $item->quantity;
                }

                if($purchaseInvoiceItem['actionStatus'] == ActionStatus::UPDATE->value) {
                    $item = $this->purchaseInvoiceItemService->updatePurchaseInvoiceItem($purchaseInvoiceItem['purchaseInvoiceItemId'],
                        [...$purchaseInvoiceItem, 'addToStock'=> $purchaseInvoice->status == InStockStatus::APPROVED ? true : false]
                    );

                    $purchaseInvoice->total_cost += $item->cost * $item->quantity;

                }

                if($purchaseInvoiceItem['actionStatus'] == ActionStatus::DELETE->value) {
                    $this->purchaseInvoiceItemService->deletePurchaseInvoiceItem($purchaseInvoiceItem['purchaseInvoiceItemId']);

                    $purchaseInvoice->total_cost -= $purchaseInvoiceItem['cost'] * $purchaseInvoiceItem['quantity'];

                }

                if($purchaseInvoiceItem['actionStatus'] == ActionStatus::NO_ACTIVE->value) {
                    $purchaseInvoice->total_cost += $purchaseInvoiceItem['cost'] * $purchaseInvoiceItem['quantity'];
                }
            }
        }

        $purchaseInvoice->total_cost_after_discount = $purchaseInvoice->total_cost - $purchaseInvoice->discount_amount;

        $purchaseInvoice->save();

        return $purchaseInvoice;
    }
    public function deletePurchaseInvoice(int $id): void
    {
        InStock::find($id)->delete();
    }
}
