<?php
namespace App\Services\Invoice;

use App\Enums\ActionStatus;
use App\Enums\Stock\OutStockStatus;
use App\Enums\Stock\OutStockType;
use App\Filters\Invoice\FilterSaleInvoice;
use App\Models\Stock\OutStock;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SaleInvoiceService
{
    protected $saleInvoiceItemService;
    public function __construct( SaleInvoiceItemService $saleInvoiceItemService)
    {
        $this->saleInvoiceItemService = $saleInvoiceItemService;
    }
    public function allSaleInvoices()
    {

        $filters = request()->get('filter')??null;
        $perPage = request()->get('pageSize', 10);

        $saleInvoices = QueryBuilder::for(OutStock::class)
            ->allowedFilters([
                AllowedFilter::custom('search', new FilterSaleInvoice()),
                AllowedFilter::exact('clientId', 'client_id'),
            ])
            ->with(['client'])
            ->where('type', OutStockType::SALE_INVOICE->value)
            ->paginate($perPage); // Pagination applied here

        $total = OutStock::where('type', OutStockType::SALE_INVOICE->value)->when(
            isset($filters['clientId']), function ($query) use($filters) {
                return $query->where('client_id', $filters['clientId']);
            }
        )->count();
        $totalAmount = DB::table('out_stocks as os')
            ->join('out_stock_items as osi', 'os.id', '=', 'osi.out_stock_id')
            ->when(isset($filters['clientId']), function ($query) use ($filters) {
                return $query->where('os.client_id', $filters['clientId']);
            })
            ->where('os.type', OutStockType::SALE_INVOICE->value)
            ->sum(DB::raw('osi.price * osi.quantity'));
        return [
            'saleInvoices' => $saleInvoices,
            'totalOrders' => $total,
            'totalAmount' => $totalAmount
        ];

    }
    public function editSaleInvoice(int $id)
    {
        return OutStock::where('type', OutStockType::SALE_INVOICE->value)
        ->with(['outStockItems'])->find($id);
    }
    public function createSaleInvoice(array $data)
    {

        $saleInvoice = OutStock::create([
            'date'=>$data['invoiceDate'],
            'supplier_in_stock_number'=>$data['supplierInvoicNumber']??null,
            'supplier_id'=>$data['supplierId']??null,
            'status'=> OutStockStatus::from($data['status'])->value,
            'note'=>$data['note']??null,
            'type'=>OutStockType::SALE_INVOICE
        ]);

        foreach ($data['saleInvoiceItems'] as $saleInvoiceItemData) {
            $saleInvoiceItem = $this->saleInvoiceItemService->createSaleInvoiceItem([
                'saleInvoiceId'=>$saleInvoice->id,
                ...$saleInvoiceItemData
            ]);

            $saleInvoice->total_cost = $saleInvoiceItem->cost * $saleInvoiceItem->quantity;
            $saleInvoice->total_price += $saleInvoiceItem->price * $saleInvoiceItem->quantity;

        }

        $saleInvoice->total_price_after_discount = $saleInvoice->total_price - $saleInvoice->discount_amount;

        $saleInvoice->save();

        return $saleInvoice;
    }
    public function updateSaleInvoice(int $id, array $data)
    {
        $saleInvoice= OutStock::find($id);

        $saleInvoice->update([
            'date'=>$data['invoiceDate'],
            'supplier_in_stock_number'=>$data['supplierInvoicNumber']??null,
            'supplier_id'=>$data['supplierId']??null,
            'status'=> OutStockStatus::from($data['status'])->value,
            'note'=>$data['note']??null,
        ]);

        foreach ($data['purchaseInvoiceItems'] as $purchaseInvoiceItem) {
            if($purchaseInvoiceItem['actionStatus'] == ActionStatus::CREATE->value) {
                $this->saleInvoiceItemService->createSaleInvoiceItem([
                    'purchaseInvoiceId'=> $saleInvoice->id,
                    ...$purchaseInvoiceItem
                ]);
            }

            if($purchaseInvoiceItem['actionStatus'] == ActionStatus::UPDATE->value) {
                $this->saleInvoiceItemService->updateSaleInvoiceItem($purchaseInvoiceItem['purchaseInvoiceItemId'],
                    $purchaseInvoiceItem
                );
            }

            if($purchaseInvoiceItem['actionStatus'] == ActionStatus::DELETE->value) {
                $this->saleInvoiceItemService->deleteSaleInvoiceItem($purchaseInvoiceItem['purchaseInvoiceItemId']);
            }
        }

        return $saleInvoice;
    }
    public function deleteSaleInvoice(int $id): void
    {
        OutStock::find($id)->delete();
    }
}
