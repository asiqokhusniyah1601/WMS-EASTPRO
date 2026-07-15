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

    // About Us
    Route::view('/about-us', 'about-us')->name('about');

    // Manajemen Pengguna — Super Admin only
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

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
        Route::get('/transfer/api/racks', [PageController::class, 'apiGetRacks'])->name('transfer.api.racks');
        Route::get('/transfer/api/rack-devices', [PageController::class, 'apiGetRackDevices'])->name('transfer.api.rack_devices');
        Route::post('/transfer/rack', [PageController::class, 'postTransferRack'])->name('transfer.rack.post');
        Route::get('/issue', [PageController::class, 'issue'])->name('issue');
        Route::post('/issue', [PageController::class, 'postIssue'])->name('issue.post');
        Route::post('/issue/accept-handover', [PageController::class, 'postAcceptHandover'])->name('issue.accept.handover');
        
        Route::get('/api/handover-history', [PageController::class, 'apiGetHandoverHistory'])->name('api.handover.history');
        Route::post('/api/handover-accept', [PageController::class, 'postMarkHandoverAccepted'])->name('api.handover.accept');

        // API Endpoints (AJAX)
        Route::get('/api/devices/search', [PageController::class, 'apiSearchDevices'])->name('api.devices.search');
        Route::post('/api/devices/bulk-search', [PageController::class, 'apiBulkSearchDevices'])->name('api.devices.bulk-search');
        Route::get('/api/pending-acceptance', [PageController::class, 'apiGetPendingAcceptance'])->name('api.pending.acceptance');
        Route::get('/api/dashboard/stock', [PageController::class, 'apiDashboardDeviceStock'])->name('api.dashboard.stock');
        Route::get('/api/dashboard/stock/details', [PageController::class, 'apiDashboardDeviceStockDetails'])->name('api.dashboard.stock.details');

        // Tanda Terima (handover receipt) — bisa dicetak / disimpan PDF
        Route::get('/receipt/{receiptNo}', [PageController::class, 'showReceipt'])->name('receipt.show');
        Route::get('/search', [PageController::class, 'search'])->name('search');

        // Garansi Perangkat
        Route::get('/warranty', [PageController::class, 'warranty'])->name('warranty');
        Route::post('/warranty/renew', [PageController::class, 'renewWarranty'])->name('warranty.renew');
        Route::post('/warranty/stop', [PageController::class, 'stopWarranty'])->name('warranty.stop');

        // Return & Inspection Routes
        Route::get('/return', [PageController::class, 'returnDevice'])->name('return');
        Route::post('/return', [PageController::class, 'postReturn'])->name('return.post');
        Route::get('/api/return-history', [PageController::class, 'apiGetReturnHistory'])->name('api.return.history');
        Route::get('/return-receipt/{receiptNo}', [PageController::class, 'showReturnReceipt'])->name('return_receipt.show');
        // Quality Control terpadu (QC Penerimaan + QC Return/Inspeksi) — Tim RND / QC, Admin Gudang, Super Admin
        Route::middleware('role:qc,admin,super_admin')->group(function () {
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

        // ---- Stock Opname Warehouse (Semua role yang berwenang) ----
        Route::get('/stock-opname', [PageController::class, 'stockOpname'])->name('stock.opname');
        // Sesi opname: mulai & lihat — Admin Gudang / Super Admin / PIC
        Route::middleware('role:admin,super_admin,pic')->group(function () {
            Route::post('/stock-opname/session/start', [PageController::class, 'startOpnameSession'])->name('stock.opname.session.start');
            Route::post('/stock-opname/session/{id}/cancel', [PageController::class, 'cancelOpnameSession'])->name('stock.opname.session.cancel');
            Route::post('/stock-opname/session/{id}/complete', [PageController::class, 'completeOpnameSession'])->name('stock.opname.session.complete');
            Route::post('/stock-opname/session/{id}/apply', [PageController::class, 'applyOpnameSession'])->name('stock.opname.session.apply');
        });
        // Halaman scan & AJAX endpoints — semua role termasuk staff_gudang
        Route::get('/stock-opname/session/{id}', [PageController::class, 'showOpnameSession'])->name('stock.opname.session.show');
        Route::post('/stock-opname/api/resolve-barcode', [PageController::class, 'apiResolveOpnameBarcode'])->name('stock.opname.api.resolve');
        Route::post('/stock-opname/session/{id}/scan', [PageController::class, 'postOpnameScan'])->name('stock.opname.scan');
        Route::put('/stock-opname/session/{id}/scan/{itemId}', [PageController::class, 'updateOpnameScan'])->name('stock.opname.scan.update');
        Route::delete('/stock-opname/session/{id}/scan/{itemId}', [PageController::class, 'deleteOpnameScan'])->name('stock.opname.scan.delete');
        Route::get('/stock-opname/session/{id}/export/raw', [PageController::class, 'exportOpnameRaw'])->name('stock.opname.export.raw');
        Route::get('/stock-opname/session/{id}/export/result', [PageController::class, 'exportOpnameResult'])->name('stock.opname.export.result');
        // Legacy manual correction (kept for backward-compatibility)
        Route::post('/stock-opname/legacy', [PageController::class, 'postStockOpname'])->name('stock.opname.post');

        // ---- Opname Teknisi ----
        Route::get('/stock-opname/teknisi/data', [PageController::class, 'opnameTeknisiData'])->name('stock.opname.teknisi.data');
        Route::post('/stock-opname/teknisi/crosscheck', [PageController::class, 'crosscheckOpnameTeknisi'])->name('stock.opname.teknisi.crosscheck');
        Route::post('/stock-opname/teknisi/export', [PageController::class, 'exportOpnameTeknisi'])->name('stock.opname.teknisi.export');

        // ---- Barcode Lokasi ----
        Route::post('/stock-opname/barcode/save', [PageController::class, 'saveBarcodeLocations'])->name('stock.opname.barcode.save');


        // Reports & Analytics Routes
        Route::get('/reports', [PageController::class, 'reports'])->name('reports');
        Route::get('/reports/print', [PageController::class, 'printReport'])->name('reports.print');
        Route::get('/reports/export/{type}', [PageController::class, 'exportReport'])->name('reports.export');
        Route::get('/reports/export-template', [PageController::class, 'exportTemplateExcel'])->name('reports.export.template');
        Route::get('/reports/export-custom-excel', [PageController::class, 'exportCustomExcel'])->name('reports.export.custom_excel');

        // ------------------------------------------------------------------
        // Super Admin & Admin
        // ------------------------------------------------------------------
        Route::middleware('role:super_admin,admin')->group(function () {
            // Master Data Management Routes
            Route::get('/master-data', [PageController::class, 'masterData'])->name('master_data');
            Route::post('/master-data/warehouse', [PageController::class, 'storeWarehouse'])->name('master_data.warehouse.store');
            Route::put('/master-data/warehouse/{code}', [PageController::class, 'updateWarehouse'])->name('master_data.warehouse.update');
            Route::delete('/master-data/warehouse/{code}', [PageController::class, 'deleteWarehouse'])->name('master_data.warehouse.delete');
            
            // Warehouse Thresholds
            Route::post('/master-data/warehouse-threshold', [PageController::class, 'storeWarehouseThreshold'])->name('master_data.warehouse_threshold.store');
            Route::delete('/master-data/warehouse-threshold/{id}', [PageController::class, 'deleteWarehouseThreshold'])->name('master_data.warehouse_threshold.delete');

            // Rack Storage
            Route::post('/master-data/rack', [PageController::class, 'storeRack'])->name('master_data.rack.store');
            Route::delete('/master-data/rack/{id}', [PageController::class, 'deleteRack'])->name('master_data.rack.delete');
            Route::get('/master-data/rack/export', [PageController::class, 'exportRacks'])->name('master_data.rack.export');

            Route::post('/master-data/customer', [PageController::class, 'storeCustomer'])->name('master_data.customer.store');
            Route::delete('/master-data/customer/{id}', [PageController::class, 'deleteCustomer'])->name('master_data.customer.delete');

            Route::post('/master-data/technician', [PageController::class, 'storeTechnician'])->name('master_data.technician.store');
            Route::delete('/master-data/technician/{code}', [PageController::class, 'deleteTechnician'])->name('master_data.technician.delete');
            
            // Technician Limits
            Route::post('/master-data/technician-limit', [PageController::class, 'storeTechnicianLimit'])->name('master_data.technician_limit.store');
            Route::delete('/master-data/technician-limit/{id}', [PageController::class, 'deleteTechnicianLimit'])->name('master_data.technician_limit.delete');

            Route::post('/master-data/accessory', [PageController::class, 'storeAccessory'])->name('master_data.accessory.store');
            Route::delete('/master-data/accessory/{code}', [PageController::class, 'deleteAccessory'])->name('master_data.accessory.delete');

            Route::post('/master-data/simcard', [PageController::class, 'storeSimcard'])->name('master_data.simcard.store');
            Route::delete('/master-data/simcard/{id}', [PageController::class, 'deleteSimcard'])->name('master_data.simcard.delete');

            Route::post('/master-data/device-model', [PageController::class, 'storeDeviceModel'])->name('master_data.device_model.store');
            Route::delete('/master-data/device-model/{id}', [PageController::class, 'deleteDeviceModel'])->name('master_data.device_model.delete');

            Route::post('/master-data/import', [PageController::class, 'importMasterData'])->name('master_data.import');
            Route::get('/master-data/sample-csv/{type}', [PageController::class, 'downloadSampleCsv'])->name('master_data.sample_csv');
        });

        // ------------------------------------------------------------------
        // Pengaturan Personal (Bisa diakses semua role)
        // ------------------------------------------------------------------
        Route::post('/settings/theme', [PageController::class, 'updateTheme'])->name('settings.theme');

        // ------------------------------------------------------------------
        // Super Admin Only — Pengaturan
        // ------------------------------------------------------------------
        Route::middleware('role:super_admin')->group(function () {
            // Settings Routes
            Route::get('/settings', [PageController::class, 'settings'])->name('settings');
            Route::post('/settings/logo', [PageController::class, 'updateLogo'])->name('settings.logo');
            Route::post('/settings/favicon', [PageController::class, 'updateFavicon'])->name('settings.favicon');
            Route::post('/settings/alerts', [PageController::class, 'updateStockAlerts'])->name('settings.alerts');
            Route::get('/settings/backup', [PageController::class, 'downloadBackup'])->name('settings.backup');
        });
    });
});
