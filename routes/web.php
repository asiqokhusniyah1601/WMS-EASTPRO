<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

// ---------------------------------------------------------------------------
// Authentication (guest)
// ---------------------------------------------------------------------------
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ---------------------------------------------------------------------------
// Authenticated routes (login required, warehouse not yet required)
// ---------------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    // Warehouse Session Selector
    Route::get('/select-warehouse', [PageController::class, 'selectWarehouse'])->name('select.warehouse');
    Route::post('/set-warehouse', [PageController::class, 'setWarehouse'])->name('set.warehouse');

    // ----------------------------------------------------------------------
    // Authenticated + warehouse-scoped routes
    // ----------------------------------------------------------------------
    Route::middleware('warehouse')->group(function () {
        Route::get('/', [PageController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard/drilldown', [PageController::class, 'dashboardDrilldown'])->name('dashboard.drilldown');
        Route::get('/alerts', [PageController::class, 'alertCenter'])->name('alerts');
        Route::get('/receiving', [PageController::class, 'receiving'])->name('receiving');
        Route::post('/receiving', [PageController::class, 'postReceiving'])->name('receiving.post');
        Route::post('/receiving/accessory', [PageController::class, 'postReceivingAccessory'])->name('receiving.accessory.post');
        Route::post('/receiving/simcard', [PageController::class, 'postReceivingSimcard'])->name('receiving.simcard.post');
        Route::get('/transfer', [PageController::class, 'transfer'])->name('transfer');
        Route::post('/transfer/create', [PageController::class, 'postCreateTransfer'])->name('transfer.create');
        Route::post('/transfer/approve', [PageController::class, 'postApproveTransfer'])->name('transfer.approve');
        Route::get('/issue', [PageController::class, 'issue'])->name('issue');
        Route::post('/issue', [PageController::class, 'postIssue'])->name('issue.post');

        // Tanda Terima (handover receipt) — bisa dicetak / disimpan PDF
        Route::get('/receipt/{receiptNo}', [PageController::class, 'showReceipt'])->name('receipt.show');
        Route::get('/search', [PageController::class, 'search'])->name('search');

        // Return & Inspection Routes
        Route::get('/return', [PageController::class, 'returnDevice'])->name('return');
        Route::post('/return', [PageController::class, 'postReturn'])->name('return.post');
        // Quality Control terpadu (QC Penerimaan + QC Return/Inspeksi) — Tim RND / QC, Admin Gudang, Super Admin
        Route::middleware('role:qc,warehouse_admin,super_admin')->group(function () {
            Route::get('/quality-control', [PageController::class, 'qualityControl'])->name('quality.control');
            Route::post('/qc-penerimaan', [PageController::class, 'postQcIncoming'])->name('qc.incoming.post');
            Route::post('/inspection/submit', [PageController::class, 'postInspection'])->name('inspection.submit');

            // Redirect rute lama → menu terpadu (kompatibilitas bookmark/link).
            Route::get('/qc-penerimaan', [PageController::class, 'qualityControl'])->name('qc.incoming');
            Route::get('/inspection', [PageController::class, 'inspection'])->name('inspection');
        });
        Route::post('/device/{id}/flag', [PageController::class, 'flagDevice'])->name('device.flag');
        Route::post('/device/{id}/dispose', [PageController::class, 'disposeDevice'])->name('device.dispose');

        // Manual Stock Correction Routes
        Route::post('/device/adjust', [PageController::class, 'postAdjustDevice'])->name('device.adjust');
        Route::get('/stock-opname', [PageController::class, 'stockOpname'])->name('stock.opname');
        Route::post('/stock-opname', [PageController::class, 'postStockOpname'])->name('stock.opname.post');

        // Reports & Analytics Routes
        Route::get('/reports', [PageController::class, 'reports'])->name('reports');
        Route::get('/reports/print', [PageController::class, 'printReport'])->name('reports.print');
        Route::get('/reports/export/{type}', [PageController::class, 'exportReport'])->name('reports.export');

        // ------------------------------------------------------------------
        // Super Admin only
        // ------------------------------------------------------------------
        Route::middleware('role:super_admin')->group(function () {
            // User Management
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            // Master Data Management Routes
            Route::get('/master-data', [PageController::class, 'masterData'])->name('master_data');
            Route::post('/master-data/warehouse', [PageController::class, 'storeWarehouse'])->name('master_data.warehouse.store');
            Route::delete('/master-data/warehouse/{code}', [PageController::class, 'deleteWarehouse'])->name('master_data.warehouse.delete');

            Route::post('/master-data/customer', [PageController::class, 'storeCustomer'])->name('master_data.customer.store');
            Route::delete('/master-data/customer/{id}', [PageController::class, 'deleteCustomer'])->name('master_data.customer.delete');

            Route::post('/master-data/technician', [PageController::class, 'storeTechnician'])->name('master_data.technician.store');
            Route::delete('/master-data/technician/{code}', [PageController::class, 'deleteTechnician'])->name('master_data.technician.delete');

            Route::post('/master-data/accessory', [PageController::class, 'storeAccessory'])->name('master_data.accessory.store');
            Route::delete('/master-data/accessory/{code}', [PageController::class, 'deleteAccessory'])->name('master_data.accessory.delete');

            Route::post('/master-data/simcard', [PageController::class, 'storeSimcard'])->name('master_data.simcard.store');
            Route::delete('/master-data/simcard/{id}', [PageController::class, 'deleteSimcard'])->name('master_data.simcard.delete');

            Route::post('/master-data/device-model', [PageController::class, 'storeDeviceModel'])->name('master_data.device_model.store');
            Route::delete('/master-data/device-model/{id}', [PageController::class, 'deleteDeviceModel'])->name('master_data.device_model.delete');

            Route::post('/master-data/import', [PageController::class, 'importMasterData'])->name('master_data.import');
            Route::get('/master-data/sample-csv/{type}', [PageController::class, 'downloadSampleCsv'])->name('master_data.sample_csv');

            // Settings Routes
            Route::get('/settings', [PageController::class, 'settings'])->name('settings');
            Route::post('/settings/logo', [PageController::class, 'updateLogo'])->name('settings.logo');
            Route::post('/settings/favicon', [PageController::class, 'updateFavicon'])->name('settings.favicon');
            Route::post('/settings/theme', [PageController::class, 'updateTheme'])->name('settings.theme');
            Route::post('/settings/alerts', [PageController::class, 'updateStockAlerts'])->name('settings.alerts');
            Route::get('/settings/backup', [PageController::class, 'downloadBackup'])->name('settings.backup');
        });
    });
});
