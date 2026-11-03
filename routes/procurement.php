<?php

use App\Http\Controllers\Procurement\GoodsReceiptController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Procurement\SupplierController;
use Illuminate\Support\Facades\Route;

// There is only one supplier permission (suppliers.manage), so it guards the whole screen,
// the list included. The form pages are here too: a user who cannot save should not open the form.
Route::middleware('permission:suppliers.manage')->group(function () {
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
});

// Looking at purchase orders needs purchase_orders.view.
// whereNumber() makes sure "create" is never read as an order id.
Route::middleware('permission:purchase_orders.view')->group(function () {
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase_orders.index');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
        ->whereNumber('purchaseOrder')
        ->name('purchase_orders.show');
});

// Creating, editing, placing and cancelling need purchase_orders.manage.
Route::middleware('permission:purchase_orders.manage')->group(function () {
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase_orders.create');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase_orders.store');
    Route::get('/purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase_orders.edit');
    Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase_orders.update');
    Route::post('/purchase-orders/{purchaseOrder}/place', [PurchaseOrderController::class, 'place'])->name('purchase_orders.place');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase_orders.cancel');
});

// Goods receipts. There is only one permission (goods_receipts.manage), so it guards the whole
// receipts screen, the list included. A receipt is always created FROM an order, hence the order in the URL.
Route::middleware('permission:goods_receipts.manage')->group(function () {
    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->name('goods_receipts.index');
    Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])
        ->whereNumber('goodsReceipt')
        ->name('goods_receipts.show');
    Route::get('/purchase-orders/{purchaseOrder}/receive', [GoodsReceiptController::class, 'create'])->name('goods_receipts.create');
    Route::post('/purchase-orders/{purchaseOrder}/goods-receipts', [GoodsReceiptController::class, 'store'])->name('goods_receipts.store');
    Route::get('/goods-receipts/{goodsReceipt}/edit', [GoodsReceiptController::class, 'edit'])->name('goods_receipts.edit');
    Route::put('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'update'])->name('goods_receipts.update');
    Route::post('/goods-receipts/{goodsReceipt}/post', [GoodsReceiptController::class, 'post'])->name('goods_receipts.post');
    Route::post('/goods-receipts/{goodsReceipt}/cancel', [GoodsReceiptController::class, 'cancel'])->name('goods_receipts.cancel');
});