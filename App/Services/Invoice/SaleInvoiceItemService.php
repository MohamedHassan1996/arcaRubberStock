<?php

namespace App\Services\Invoice;

use App\Enums\Product\HasSubUnit;
use App\Models\Product\Product;
use App\Models\Stock\OutStockItem;
use App\Models\Stock\ProductStock;

class SaleInvoiceItemService
{
    public function allSaleInvoices(int $saleInvoiceId)
    {

        $saleInvoiceItems = OutStockItem::where('in_stock_id', $saleInvoiceId)->get();

        return $saleInvoiceItems;

    }
    public function editSaleInvoiceItem(int $id)
    {
        return OutStockItem::find($id);
    }
    public function createSaleInvoiceItem(array $data)
    {
        $saleInvoiceItem = OutStockItem::create([
            'quantity'=>$data['quantity'],
            'product_id'=>$data['productId'],
            'out_stock_id'=>$data['saleInvoiceId']??null,
            'is_sub_unit'=> HasSubUnit::from($data['isSubUnit'])->value,
        ]);

        $quantity = $saleInvoiceItem->quantity;

        $product = Product::find($saleInvoiceItem->product_id);

        if($product->has_sub_unit == HasSubUnit::YES && $saleInvoiceItem->is_sub_unit == HasSubUnit::NO){
            $quantity = $saleInvoiceItem->quantity * $product->sub_unit_conversion_rate;
        }

        $remainingQuantity = $quantity;
        $productStockIds = [];
        $productCosts = [];

        $productStocks = ProductStock::where('product_id', $saleInvoiceItem->product_id)->get();

        foreach($productStocks as $index => $productStock){

            if($remainingQuantity == 0){
                break;
            }

            if($remainingQuantity > $productStock->quantity){
                $remainingQuantity -= $productStock->quantity;
                $productStock->quantity -= $productStock->quantity;
            }
            else{
                $productStock->quantity -= $remainingQuantity;

                $remainingQuantity = 0;
            }

            $productStock->save();

            $productStockIds[] = $productStock->id;
            if($productStock->has_sub_unit == HasSubUnit::YES && $saleInvoiceItem->is_sub_unit == HasSubUnit::NO){
                $productCosts[] = $productStock->cost * $product->sub_unit_conversion_rate;
            } else {
                $productCosts[] = $productStock->cost;
            }


        }


        $saleInvoiceItem->product_stock_ids = $productStockIds;
        $saleInvoiceItem->cost = array_sum($productCosts) / count($productCosts);
        $saleInvoiceItem->price =  $product->productActualPrice($saleInvoiceItem->is_sub_unit == HasSubUnit::YES);

        if($product->has_sub_unit == HasSubUnit::YES && $saleInvoiceItem->is_sub_unit == HasSubUnit::NO){
            $saleInvoiceItem->quantity = $quantity / $product->sub_unit_conversion_rate;
            $saleInvoiceItem->cost = $saleInvoiceItem->cost * $product->sub_unit_conversion_rate;
        }

        $saleInvoiceItem->save();

        return $saleInvoiceItem;

    }

    public function updateSaleInvoiceItem(int $id, array $data)
    {
        $saleInvoiceItem= OutStockItem::find($id);
        $saleInvoiceItem->update([
            'quantity'=>$data['quantity'],
            'product_id'=>$data['productId'],
            'is_sub_unit'=> HasSubUnit::from($data['isSubUnit'])->value,
            'cost'=> $data['cost']
        ]);

        $quantity = $saleInvoiceItem->quantity;
        $cost = $saleInvoiceItem->cost;

        $product = Product::find($saleInvoiceItem->product_id);

        if($product->has_sub_unit == HasSubUnit::YES && $saleInvoiceItem->is_sub_unit == HasSubUnit::NO){
            $quantity = $saleInvoiceItem->quantity * $product->sub_unit_conversion_rate;
            $cost = $saleInvoiceItem->cost / $product->sub_unit_conversion_rate;
        }

        $productStock = ProductStock::where('in_stock_item_id', $id)->first();

        $productStock->update([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'cost' => $cost
        ]);

        $saleInvoiceItem->product_stock_id = $productStock->id;
        $saleInvoiceItem->save();

        return $saleInvoiceItem;
    }
    public function deleteSaleInvoiceItem(int $id): void
    {
        OutStockItem::find($id)->delete();
    }
}
