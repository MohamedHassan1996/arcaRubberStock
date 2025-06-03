<?php

namespace App\Services\Invoice;

use App\Enums\Product\HasSubUnit;
use App\Models\Product\Product;
use App\Models\Stock\InStockItem;
use App\Models\Stock\ProductStock;

class PurchaseInvoiceItemService
{
    public function allPurchaseInvoices(int $purchaseInvoiceId)
    {

        $purchaseInvoiceItems = InStockItem::where('in_stock_id', $purchaseInvoiceId)->get();

        return $purchaseInvoiceItems;

    }
    public function editPurchaseInvoiceItem(int $id)
    {
        return InStockItem::find($id);
    }
    public function createPurchaseInvoiceItem(array $data)
    {
        $purchaseInvoiceItem = InStockItem::create([
            'quantity'=>$data['quantity'],
            'product_id'=>$data['productId'],
            'in_stock_id'=>$data['purchaseInvoiceId']??null,
            'is_sub_unit'=> HasSubUnit::from($data['isSubUnit'])->value,
            'cost'=> $data['cost']
        ]);

        $quantity = $purchaseInvoiceItem->quantity;
        $cost = $purchaseInvoiceItem->cost;

        $product = Product::find($purchaseInvoiceItem->product_id);


        if($product->has_sub_unit == HasSubUnit::YES && $purchaseInvoiceItem->is_sub_unit == HasSubUnit::NO){
            $quantity = $purchaseInvoiceItem->quantity * $product->sub_unit_conversion_rate;
            $cost = $purchaseInvoiceItem->cost / $product->sub_unit_conversion_rate;
        }

        /*if($product->has_sub_unit == HasSubUnit::YES && $purchaseInvoiceItem->is_sub_unit == HasSubUnit::YES){
            $cost = $purchaseInvoiceItem->cost / $product->sub_unit_conversion_rate;
        }*/

        if($data['addToStock'] != true){
            return $purchaseInvoiceItem;
        }

        $productStock = ProductStock::create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'cost' => $cost
        ]);

        $purchaseInvoiceItem->product_stock_id = $productStock->id;
        $purchaseInvoiceItem->save();

        return $purchaseInvoiceItem;

    }

    public function updatePurchaseInvoiceItem(int $id, array $data)
    {
        $purchaseInvoiceItem= InStockItem::find($id);
        $purchaseInvoiceItem->update([
            'quantity'=>$data['quantity'],
            'product_id'=>$data['productId'],
            'is_sub_unit'=> HasSubUnit::from($data['isSubUnit'])->value,
            'cost'=> $data['cost']
        ]);

        $quantity = $purchaseInvoiceItem->quantity;
        $cost = $purchaseInvoiceItem->cost;

        $product = Product::find($purchaseInvoiceItem->product_id);

        if($product->has_sub_unit == HasSubUnit::YES && $purchaseInvoiceItem->is_sub_unit == HasSubUnit::NO){
            $quantity = $purchaseInvoiceItem->quantity * $product->sub_unit_conversion_rate;
            $cost = $purchaseInvoiceItem->cost / $product->sub_unit_conversion_rate;
        }

        if($data['addToStock'] != true){
            return $purchaseInvoiceItem;
        }

        $productStock = ProductStock::updateOrCreate(
            ['id' => $purchaseInvoiceItem->product_stock_id], // Search condition
            [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'cost' => $cost
            ] // Fields to update or create
        );

        $purchaseInvoiceItem->product_stock_id = $productStock->id;
        $purchaseInvoiceItem->save();

        return $purchaseInvoiceItem;
    }
    public function deletePurchaseInvoiceItem(int $id): void
    {
        InStockItem::find($id)->delete();
    }
}
