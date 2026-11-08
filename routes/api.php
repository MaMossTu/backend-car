<?php

use App\Http\Controllers\AddressEmsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BranchesController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CostController;
use App\Http\Controllers\CustomerAddressesController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DistrictsController;
use App\Http\Controllers\DocController;
use App\Http\Controllers\DocCustomerController;
use App\Http\Controllers\DocTransactionController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\EmployeesController;
use App\Http\Controllers\ExpenseTypeController;
use App\Http\Controllers\GasBrandController;
use App\Http\Controllers\InspectionsController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\InsuranceLinkPhoneController;
use App\Http\Controllers\InsuranceNameController;
use App\Http\Controllers\InsuranceRenewalTypesController;
use App\Http\Controllers\InsurancesRenewalsController;
use App\Http\Controllers\InsuranceTypesController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemTypeController;
use App\Http\Controllers\LineItemController;
use App\Http\Controllers\MenupermissionsController;
use App\Http\Controllers\MenusController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\PrbRenewalsController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\ProvincesController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RepairServiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportToController;
use App\Http\Controllers\RolePermissionsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SaveDocumentController;
use App\Http\Controllers\ServiceDocTypeController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\ServiceTransactionsController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubDepartmentController;
use App\Http\Controllers\SubDistrictsController;
use App\Http\Controllers\TaxInvoiceController;
use App\Http\Controllers\UserPositionController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\VehicleBrandsController;
use App\Http\Controllers\VehicleGasController;
use App\Http\Controllers\VehicleInspectionTypesController;
use App\Http\Controllers\VehicleModelsController;
use App\Http\Controllers\VehiclesController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkOrderPaymentController;
use App\Http\Controllers\WorkOrderServiceController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth',
], function ($router) {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api')->name('logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api')->name('refresh');
    Route::post('/me', [AuthController::class, 'me'])->middleware('auth:api')->name('me');
    Route::post('/showpermissionUser', [AuthController::class, 'showpermissionUser'])->middleware('auth:api');
});

Route::middleware('auth:api')->group(function () {
    //
});

//Brache
Route::get('/Brache/show', [BranchesController::class, 'index']);
Route::get('/Brache/showID/{id}', [BranchesController::class, 'showID']);
Route::delete('/Brache/delete/{id}', [BranchesController::class, 'destroy'])->middleware('auth:api');
Route::post('/Brache/add', [BranchesController::class, 'store'])->middleware('auth:api');
Route::post('/Brache/update/{id}', [BranchesController::class, 'update'])->middleware('auth:api');
Route::post('/Brache/getPage', [BranchesController::class, 'getPage']);

//Customer Addresses
Route::get('/customer_add/show', [CustomerAddressesController::class, 'index']);
Route::get('/customer_add/showID/{id}', [CustomerAddressesController::class, 'showID']);
Route::delete('/customer_add/delete/{id}', [CustomerAddressesController::class, 'destroy'])->middleware('auth:api');
Route::post('/customer_add/add', [CustomerAddressesController::class, 'store'])->middleware('auth:api');
Route::post('/customer_add/update/{id}', [CustomerAddressesController::class, 'update'])->middleware('auth:api');
Route::post('/customer_add/getPage', [CustomerAddressesController::class, 'getPage']);

Route::post('uploadExcel', [CustomersController::class , 'uploadExcel']);

//Permissions
Route::get('/permissions/show', [PermissionsController::class, 'index']);
Route::get('/permissions/showID/{id}', [PermissionsController::class, 'showID']);
Route::delete('/permissions/delete/{id}', [PermissionsController::class, 'destroy'])->middleware('auth:api');
Route::post('/permissions/add', [PermissionsController::class, 'store'])->middleware('auth:api');
Route::post('/permissions/update/{id}', [PermissionsController::class, 'update'])->middleware('auth:api');
Route::post('/permissions/getPage', [PermissionsController::class, 'getPage']);

//Provinces
Route::get('/provinces/show', [ProvincesController::class, 'index']);
Route::get('/provinces/showID/{id}', [ProvincesController::class, 'showID']);
Route::delete('/provinces/delete/{id}', [ProvincesController::class, 'destroy'])->middleware('auth:api');
Route::post('/provinces/add', [ProvincesController::class, 'store'])->middleware('auth:api');
Route::post('/provinces/update/{id}', [ProvincesController::class, 'update'])->middleware('auth:api');
Route::post('/provinces/getPage', [ProvincesController::class, 'getPage']);
Route::get('/provinces/filter/{id}', [ProvincesController::class, 'filter_District']);

//Districts
Route::get('/districts/show', [DistrictsController::class, 'index']);
Route::get('/districts/showID/{id}', [DistrictsController::class, 'showID']);
Route::delete('/districts/delete/{id}', [DistrictsController::class, 'destroy'])->middleware('auth:api');
Route::post('/districts/add', [DistrictsController::class, 'store'])->middleware('auth:api');
Route::post('/districts/update/{id}', [DistrictsController::class, 'update'])->middleware('auth:api');
Route::post('/districts/getPage', [DistrictsController::class, 'getPage']);
Route::get('/districts/filter/{id}', [DistrictsController::class, 'filter_subdistrict']);

//Subdistricts
Route::get('/subdistricts/show', [SubDistrictsController::class, 'index']);
Route::get('/subdistricts/showID/{id}', [SubDistrictsController::class, 'showID']);
Route::delete('/subdistricts/delete/{id}', [SubDistrictsController::class, 'destroy'])->middleware('auth:api');
Route::post('/subdistricts/add', [SubDistrictsController::class, 'store'])->middleware('auth:api');
Route::post('/subdistricts/update/{id}', [SubDistrictsController::class, 'update'])->middleware('auth:api');
Route::post('/subdistricts/getPage', [SubDistrictsController::class, 'getPage']);

//Role
Route::get('/role/show', [RolesController::class, 'index']);
Route::get('/role/showID/{id}', [RolesController::class, 'showID']);
Route::delete('/role/delete/{id}', [RolesController::class, 'destroy'])->middleware('auth:api');
Route::post('/role/add', [RolesController::class, 'store'])->middleware('auth:api');
Route::post('/role/update/{id}', [RolesController::class, 'update'])->middleware('auth:api');
Route::post('/role/getPage', [RolesController::class, 'getPage']);

//RoleMenu
Route::get('/rolemenu/show', [RolePermissionsController::class, 'index']);
Route::post('/rolemenu/add', [RolePermissionsController::class, 'store']);
Route::post('/rolemenu/update/{id}', [RolePermissionsController::class, 'update']);
Route::delete('/rolemenu/delete/{id}', [RolePermissionsController::class, 'update']);

//Customer
Route::get('/customer/get_customers', [CustomersController::class, 'getList']);

Route::get('/customer/show', [CustomersController::class, 'index']);
Route::get('/customer/showall_backoffice', [CustomersController::class, 'showall_backoffice']);
Route::get('/customer/showID/{id}', [CustomersController::class, 'showID']);
Route::delete('/customer/delete/{id}', [CustomersController::class, 'destroy'])->middleware('auth:api');
Route::post('/customer/add', [CustomersController::class, 'store'])->middleware('auth:api');
Route::post('/customer/update/{id}', [CustomersController::class, 'update'])->middleware('auth:api');
Route::post('/customer/getPage', [CustomersController::class, 'getPage']);

//Rolepermission
Route::get('/rolepermisions/show', [RolePermissionsController::class, 'index']);
Route::get('/rolepermisions/showID/{id}', [RolePermissionsController::class, 'showID']);
Route::delete('/rolepermisions/delete/{id}', [RolePermissionsController::class, 'destroy'])->middleware('auth:api');
Route::post('/rolepermisions/add', [RolePermissionsController::class, 'store'])->middleware('auth:api');
Route::post('/rolepermisions/update/{id}', [RolePermissionsController::class, 'update'])->middleware('auth:api');
Route::post('/rolepermisions/getPage', [RolePermissionsController::class, 'getPage']);

//Employees
Route::get('/employees/show', [EmployeesController::class, 'index']);
Route::get('/employees/showall_engineer', [EmployeesController::class, 'showall_engineer']);
Route::get('/employees/showID/{id}', [EmployeesController::class, 'showID']);
Route::delete('/employees/delete/{id}', [EmployeesController::class, 'destroy'])->middleware('auth:api');
Route::post('/employees/add', [EmployeesController::class, 'store'])->middleware('auth:api');
Route::post('/employees/update/{id}', [EmployeesController::class, 'update'])->middleware('auth:api');
Route::post('/employees/getPage', [EmployeesController::class, 'getPage']);

//Services
Route::get('/services/show', [ServicesController::class, 'index']);
Route::get('/services/showall', [ServicesController::class, 'indexall']);
Route::get('/services/showID/{id}', [ServicesController::class, 'showID']);
Route::delete('/services/delete/{id}', [ServicesController::class, 'destroy'])->middleware('auth:api');
Route::post('/services/add', [ServicesController::class, 'store'])->middleware('auth:api');
Route::post('/services/update/{id}', [ServicesController::class, 'update'])->middleware('auth:api');
Route::post('/services/getPage', [ServicesController::class, 'getPage']);

//Servicetransactions
Route::get('/servicetran/show', [ServiceTransactionsController::class, 'index']);
Route::get('/servicetran/showID/{id}', [ServiceTransactionsController::class, 'showID']);
Route::delete('/servicetran/delete/{id}', [ServiceTransactionsController::class, 'destroy'])->middleware('auth:api');
Route::post('/servicetran/add', [ServiceTransactionsController::class, 'store'])->middleware('auth:api');
Route::post('/servicetran/update/{id}', [ServiceTransactionsController::class, 'update'])->middleware('auth:api');
Route::post('/servicetran/getPage', [ServiceTransactionsController::class, 'getPage']);
Route::put('/servicetran/or_check/{id}', [ServiceTransactionsController::class, 'puth_or_check']);

//UserPosition
Route::get('/userposition/show', [UserPositionController::class, 'index']);
Route::get('/userposition/showID/{id}', [UserPositionController::class, 'showID']);
Route::delete('/userposition/delete/{id}', [UserPositionController::class, 'destroy'])->middleware('auth:api');
Route::post('/userposition/add', [UserPositionController::class, 'store'])->middleware('auth:api');
Route::post('/userposition/update/{id}', [UserPositionController::class, 'update'])->middleware('auth:api');
Route::post('/userposition/getPage', [UserPositionController::class, 'getPage']);

//Users
Route::get('/user/show', [UsersController::class, 'index']);
Route::get('/user/showID/{id}', [UsersController::class, 'showID']);
Route::delete('/user/delete/{id}', [UsersController::class, 'destroy'])->middleware('auth:api');
Route::post('/user/add', [UsersController::class, 'store'])->middleware('auth:api');
Route::post('/user/update/{id}', [UsersController::class, 'update'])->middleware('auth:api');
Route::post('/user/getPage', [UsersController::class, 'getPage']);

//Inspections
Route::get('/inspections/show', [InspectionsController::class, 'index']);
Route::get('/inspections/show_vat', [InspectionsController::class, 'index_vat']);
Route::get('/inspections/showID/{id}', [InspectionsController::class, 'showID']);
Route::delete('/inspections/delete/{id}', [InspectionsController::class, 'destroy'])->middleware('auth:api');
Route::post('/inspections/add', [InspectionsController::class, 'store'])->middleware('auth:api');
Route::post('/inspections/update/{id}', [InspectionsController::class, 'update'])->middleware('auth:api');
Route::post('/inspections/getPage', [InspectionsController::class, 'getPage']);
Route::post('/inspections/notification', [InspectionsController::class, 'notifications']);
Route::post('/inspections/Getpage_history', [InspectionsController::class, 'Getpage_history_paid']);
Route::post('/inspections/Getpage_history_bill', [InspectionsController::class, 'Getpage_history_bill']);
Route::post('/inspections/cal_tax', [InspectionsController::class, 'cal_tax']);
Route::post('/inspections/cal_gas', [InspectionsController::class, 'gas_cal_']);
Route::post('/inspections/cal_checkcar', [InspectionsController::class, 'checkcar_cal']);
Route::post('/inspections/setting_cal', [InspectionsController::class, 'setting_cal']);
Route::post('/inspections/getPage_report', [InspectionsController::class, 'getPage_report']);
Route::post('/inspections/status', [InspectionsController::class, 'Update_status']);

//Vehicles
Route::get('/vehicle/show', [VehiclesController::class, 'index']);
Route::get('/vehicle/showID/{id}', [VehiclesController::class, 'showID']);
Route::post('/vehicle/add', [VehiclesController::class, 'store'])->middleware('auth:api');
Route::post('/vehicle/update/{id}', [VehiclesController::class, 'update'])->middleware('auth:api');
Route::delete('/vehicle/delete/{id}', [VehiclesController::class, 'destroy'])->middleware('auth:api');
Route::post('/vehicle/getPage', [VehiclesController::class, 'getPage']);
Route::put('/vehicle/update_call_log/{id}', [VehiclesController::class, 'update_call_log'])->middleware('auth:api');
Route::post('/vehicle/update_tax_renewal_date/{id}', [VehiclesController::class, 'updateTaxRenewalDate'])->middleware('auth:api');

//VehicleInspectionTypes
Route::get('/vehicle/inspection/show', [VehicleInspectionTypesController::class, 'index']);
Route::post('/vehicle/inspection/add', [VehicleInspectionTypesController::class, 'store'])->middleware('auth:api');
Route::post('/vehicle/inspection/update/{id}', [VehicleInspectionTypesController::class, 'update'])->middleware('auth:api');
Route::get('/vehicle/inspection/showID/{id}', [VehicleInspectionTypesController::class, 'showID']);
Route::delete('/vehicle/inspection/delete/{id}', [VehicleInspectionTypesController::class, 'destroy'])->middleware('auth:api');
Route::post('/vehicle/inspection/getPage', [VehicleInspectionTypesController::class, 'getPage']);
Route::get('/vehicle/inspection/filter/{id}', [VehicleInspectionTypesController::class, 'filter']);

//VehicleBrands
Route::get('/vehicle/brands/show', [VehicleBrandsController::class, 'index']);
Route::post('/vehicle/brands/add', [VehicleBrandsController::class, 'store'])->middleware('auth:api');
Route::get('/vehicle/brands/showID/{id}', [VehicleBrandsController::class, 'showID']);
Route::post('/vehicle/brands/update/{id}', [VehicleBrandsController::class, 'update'])->middleware('auth:api');
Route::delete('/vehicle/brands/delete/{id}', [VehicleBrandsController::class, 'destroy'])->middleware('auth:api');
Route::get('/vehicle/brands/showIDandData/{id}', [VehicleBrandsController::class, 'showIDandData']);
Route::post('/vehicle/brands/getPage', [VehicleBrandsController::class, 'getPage']);
Route::get('/vehicle/brands/filter_vehicleModel/{id}', [VehicleBrandsController::class, 'filter_vehicleModel']);

//GasBrands
Route::get('/Gas/brands/show', [GasBrandController::class, 'index']);
Route::post('/Gas/brands/add', [GasBrandController::class, 'store'])->middleware('auth:api');
Route::get('/Gas/brands/showID/{id}', [GasBrandController::class, 'showID']);
Route::post('/Gas/brands/update/{id}', [GasBrandController::class, 'update'])->middleware('auth:api');
Route::delete('/Gas/brands/delete/{id}', [GasBrandController::class, 'destroy'])->middleware('auth:api');
Route::post('/Gas/brands/getPage', [GasBrandController::class, 'getPage']);

//VehicleModels
Route::get('/vehicle/models/show', [VehicleModelsController::class, 'index']);
Route::post('/vehicle/models/add', [VehicleModelsController::class, 'store'])->middleware('auth:api');
Route::get('/vehicle/models/showID/{id}', [VehicleModelsController::class, 'showID']);
Route::post('/vehicle/models/update/{id}', [VehicleModelsController::class, 'update'])->middleware('auth:api');
Route::delete('/vehicle/models/delete/{id}', [VehicleModelsController::class, 'destroy'])->middleware('auth:api');
Route::post('/vehicle/models/getPage', [VehicleModelsController::class, 'getPage']);
Route::get('/vehicle/models/filter_vehicleYear/{id}', [VehicleModelsController::class, 'filter_vehicleYear']);
//InsuranceTypes
Route::get('/vehicle/insuranceTypes/show', [InsuranceTypesController::class, 'index']);
Route::post('/vehicle/insuranceTypes/add', [InsuranceTypesController::class, 'store'])->middleware('auth:api');
Route::get('/vehicle/insuranceTypes/showID/{id}', [InsuranceTypesController::class, 'showID']);
Route::post('/vehicle/insuranceTypes/update/{id}', [InsuranceTypesController::class, 'update'])->middleware('auth:api');
Route::delete('/vehicle/insuranceTypes/delete/{id}', [InsuranceTypesController::class, 'destroy'])->middleware('auth:api');
Route::post('/vehicle/insuranceTypes/getPage', [InsuranceTypesController::class, 'getPage']);

//ProductType
Route::get('/producttype/show', [ProductTypeController::class, 'index']);
Route::post('/producttype/add', [ProductTypeController::class, 'store'])->middleware('auth:api');
Route::get('/producttype/showID/{id}', [ProductTypeController::class, 'showID']);
Route::post('/producttype/update/{id}', [ProductTypeController::class, 'update'])->middleware('auth:api');
Route::delete('/producttype/delete/{id}', [ProductTypeController::class, 'destroy'])->middleware('auth:api');
Route::post('/producttype/getPage', [ProductTypeController::class, 'getPage']);

//ServiceType
Route::get('/servicetype/show', [ServiceTypeController::class, 'index']);
Route::post('/servicetype/add', [ServiceTypeController::class, 'store'])->middleware('auth:api');
Route::get('/servicetype/showID/{id}', [ServiceTypeController::class, 'showID']);
Route::post('/servicetype/update/{id}', [ServiceTypeController::class, 'update'])->middleware('auth:api');
Route::delete('/servicetype/delete/{id}', [ServiceTypeController::class, 'destroy'])->middleware('auth:api');
Route::post('/servicetype/getPage', [ServiceTypeController::class, 'getPage']);

//ExpenseType
Route::get('/expensetype/show', [ExpenseTypeController::class, 'index']);
Route::post('/expensetype/add', [ExpenseTypeController::class, 'store'])->middleware('auth:api');
Route::get('/expensetype/showID/{id}', [ExpenseTypeController::class, 'showID']);
Route::post('/expensetype/update/{id}', [ExpenseTypeController::class, 'update'])->middleware('auth:api');
Route::delete('/expensetype/delete/{id}', [ExpenseTypeController::class, 'destroy'])->middleware('auth:api');
Route::post('/expensetype/getPage', [ExpenseTypeController::class, 'getPage']);

//Menus
Route::get('/menus/show', [MenusController::class, 'index']);
Route::post('/menus/add', [MenusController::class, 'store'])->middleware('auth:api');
Route::get('/menus/showID/{id}', [MenusController::class, 'showID']);
Route::post('/menus/update/{id}', [MenusController::class, 'update'])->middleware('auth:api');
Route::delete('/menus/delete/{id}', [MenusController::class, 'destroy'])->middleware('auth:api');
Route::post('/menus/getPage', [MenusController::class, 'getPage']);

//MenusPermission
Route::get('/menuspermission/show', [MenupermissionsController::class, 'index']);
Route::post('/menuspermission/add', [MenupermissionsController::class, 'store'])->middleware('auth:api');
Route::get('/menuspermission/showID/{id}', [MenupermissionsController::class, 'showID']);
Route::post('/menuspermission/update/{id}', [MenupermissionsController::class, 'update'])->middleware('auth:api');
Route::delete('/menuspermission/delete/{id}', [MenupermissionsController::class, 'destroy'])->middleware('auth:api');
Route::post('/menuspermission/getPage', [MenupermissionsController::class, 'getPage']);

//Department
Route::get('/department/show', [DepartmentController::class, 'index']);
Route::post('/department/add', [DepartmentController::class, 'store'])->middleware('auth:api');
Route::get('/department/showID/{id}', [DepartmentController::class, 'showID']);
Route::post('/department/update/{id}', [DepartmentController::class, 'update'])->middleware('auth:api');
Route::delete('/department/delete/{id}', [DepartmentController::class, 'destroy'])->middleware('auth:api');
Route::post('/department/getPage', [DepartmentController::class, 'getPage']);

//SubDepartment
Route::get('/subdepartment/show', [SubDepartmentController::class, 'index']);
Route::post('/subdepartment/add', [SubDepartmentController::class, 'store'])->middleware('auth:api');
Route::get('/subdepartment/showID/{id}', [SubDepartmentController::class, 'showID']);
Route::post('/subdepartment/update/{id}', [SubDepartmentController::class, 'update'])->middleware('auth:api');
Route::delete('/subdepartment/delete/{id}', [SubDepartmentController::class, 'destroy'])->middleware('auth:api');
Route::post('/subdepartment/getPage', [SubDepartmentController::class, 'getPage']);

//Caltotal, Calgreenweight, Calblueweight
Route::post('/calTotal', [InspectionsController::class, 'calTotal']);
Route::post('/calgreenweight', [InspectionsController::class, 'calgreenweight']);
Route::post('/calblueweight', [InspectionsController::class, 'calbluenweight']);
Route::post('/calculate', [InspectionsController::class, 'calculate']);

//WorkOrderService
Route::get('/WorkOrderService/show', [WorkOrderServiceController::class, 'index']);
Route::post('/WorkOrderService/add', [WorkOrderServiceController::class, 'store'])->middleware('auth:api');
Route::post('/WorkOrderService/update/{id}', [WorkOrderServiceController::class, 'update'])->middleware('auth:api');
Route::delete('/WorkOrderService/delete/{id}', [WorkOrderServiceController::class, 'destroy'])->middleware('auth:api');

//WorkOrder
Route::get('/WorkOrder/show', [WorkOrderController::class, 'index']);
Route::get('/WorkOrder/show_paid', [WorkOrderController::class, 'index_paid']);
Route::post('/WorkOrder/add', [WorkOrderController::class, 'store'])->middleware('auth:api');
Route::post('/WorkOrder/update/{id}', [WorkOrderController::class, 'update'])->middleware('auth:api');
Route::delete('/WorkOrder/delete/{id}', [WorkOrderController::class, 'destroy'])->middleware('auth:api');
Route::post('/Getpage/work_order/info', [WorkOrderController::class, 'Getpage_info']);
Route::post('/Getpage/work_order/', [WorkOrderController::class, 'Getpage']);
//Boom
Route::get('/WorkOrder/show_all', [WorkOrderController::class, 'show_All_workorder']);
Route::get('/WorkOrder/showID/{id}', [WorkOrderController::class, 'show_workorderByID']);
Route::post('/WorkOrder/getPage', [WorkOrderController::class, 'getpage_workorder']);
Route::put('/WorkOrder/update_status/{id}', [WorkOrderController::class, 'update_status']);

Route::get('/WorkOrder/report', [WorkOrderController::class, 'reportWorkOrder']);
Route::get('/WorkOrder/pdf/{id}', [ReportController::class, 'pdfWorkOrder']);

//Vendor
Route::get('/Vendor/show', [VendorController::class, 'index']);
Route::post('/Vendor/add', [VendorController::class, 'store'])->middleware('auth:api');
Route::post('/Vendor/update/{id}', [VendorController::class, 'update'])->middleware('auth:api');
Route::delete('/Vendor/delete/{id}', [VendorController::class, 'destroy'])->middleware('auth:api');
Route::post('/Vendor/getPage', [VendorController::class, 'getPage']);

//LineItem
Route::get('/LineItem/show', [LineItemController::class, 'index']);
Route::post('/LineItem/add', [LineItemController::class, 'store'])->middleware('auth:api');
Route::post('/LineItem/update/{id}', [LineItemController::class, 'update'])->middleware('auth:api');
Route::delete('/LineItem/delete/{id}', [LineItemController::class, 'destroy'])->middleware('auth:api');

//ItemType
Route::get('/ItemType/show', [ItemTypeController::class, 'index']);
Route::post('/ItemType/add', [ItemTypeController::class, 'store'])->middleware('auth:api');
Route::post('/ItemType/update/{id}', [ItemTypeController::class, 'update'])->middleware('auth:api');
Route::delete('/ItemType/delete/{id}', [ItemTypeController::class, 'destroy'])->middleware('auth:api');
Route::post('/ItemType/getPage', [ItemTypeController::class, 'getPage']);

//Item
Route::get('/Item/show', [ItemController::class, 'index']);
Route::post('/Item/add', [ItemController::class, 'store'])->middleware('auth:api');
Route::post('/Item/update/{id}', [ItemController::class, 'update'])->middleware('auth:api');
Route::delete('/Item/delete/{id}', [ItemController::class, 'destroy'])->middleware('auth:api');

//Inventory
Route::get('/Inventory/show', [InventoryController::class, 'index']);
Route::post('/Inventory/add', [InventoryController::class, 'store'])->middleware('auth:api');
Route::post('/Inventory/update/{id}', [InventoryController::class, 'update'])->middleware('auth:api');
Route::delete('/Inventory/delete/{id}', [InventoryController::class, 'destroy'])->middleware('auth:api');

//Dashboard
Route::post('/dashboard/service', [DashboardController::class, 'Inspections_per_month']);

//ReportPDF
Route::get('/report/reportExportPDFmonth', [ReportController::class, 'reportExportPDFmonth']);
Route::get('/report/reportExportPDFday', [ReportController::class, 'reportExportPDFday']);
Route::get('/report/reportExportPDFSMS', [ReportController::class, 'reportExportPDFSMS']);
Route::get('/report/reportExportPDFextent_customer', [ReportController::class, 'reportExportPDFextent_customer']);
Route::get('/report/excel/extent_customer', [ReportController::class, 'downloadExcel_extent_customer']);
Route::get('/report/reportExportPDF_gas', [ReportController::class, 'reportExportPDF_gas']);
Route::get('/report/reportExportPDFexpense', [ReportController::class, 'reportExportPDFexpense']);
Route::get('/report/reportExportPDFcheck_gas', [ReportController::class, 'reportExportPDFcheck_gas']);
Route::get('/report/reportExportPDFdaily_report', [ReportController::class, 'reportExportPDFdaily_report']);
Route::get('/report/reportExportPDFinsurance_report', [ReportController::class, 'reportExportPDFinsurance_report']);
Route::get('/report/reportExportPDFinsurance', [ReportController::class, 'reportExportPDFinsurance']);
Route::get('/report/reportExportPDFreport_car_tax', [ReportController::class, 'reportExportPDFreport_car_tax']);
Route::get('/report/excel/reportExportPDFreport_car_tax', [ReportController::class, 'reportExportExcel_report_car_tax']);
Route::get('/report/reportExportPDFexpense_report', [ReportController::class, 'reportExportPDFexpense_report']);
Route::get('/report/reportExportPDFlist_check_gas', [ReportController::class, 'reportExportPDFlist_check_gas']);
Route::get('/report/reportExportPDFcommission_employee', [ReportController::class, 'reportExportPDFcommission_employee']);
Route::get('/report/reportExportPDFprofit_report', [ReportController::class, 'reportExportPDFprofit_report']);
Route::get('/report/reportExportPDFcheck_car_report', [ReportController::class, 'reportExportPDFcheck_car_report']);
Route::get('/report/reportExportcheck_car_report', [ReportController::class, 'downloadExcelCheckCarReport']);
// Route::get('/report/exportPDF/billquotation/{id}',[ReportController::class, 'billquotation']);
// Route::get('/report/exportPDF/billservice/{id}',[ReportController::class, 'billserviceExportPDF']);
// Route::get('/report/exportPDF/receipt/{id}',[ReportController::class, 'receipt']);
// Route::get('/report/exportPDF/billtax_invioce/{id}',[ReportController::class, 'billtax_invioce']);
Route::get('/report/exportPDF/receiptRepaire/{id}', [ReportController::class, 'receiptRepaire']);
Route::get('/report/exportPDF/billquotationRepaire/{id}', [ReportController::class, 'billquotationRepaire']);
Route::get('/report/exportPDF/billserviceRepaire/{id}', [ReportController::class, 'billserviceRepaire']);
Route::get('/report/exportPDF/billtax_invioceRepaire/{id}', [ReportController::class, 'billtax_invioceRepaire']);
Route::get('/report/ListPDFcheck_testLPG/{id}', [ReportController::class, 'ListPDFcheck_testLPG']);
Route::get('/report/report_cng/{id}', [ReportController::class, 'report_cng']);
Route::get('/report/exportPDF/certificate_gas/{id}', [ReportController::class, 'certificate']);
Route::get('/report/exportPDF/billorder/{id}', [ReportController::class, 'billorder']);
Route::get('/report/exportPDF/addresEms/{id}', [ReportController::class, 'Address_the_envelope']);
Route::get('/report/exportPDF/reportexpenseaday', [ReportController::class, 'reportPDFexpenseaday']);

//ReportPDF okay
Route::get('/report/exportPDF/billquotation/{id}', [ReportToController::class, 'Invoice']); // *ใบวางบิล
Route::get('/report/exportPDF/Quotation/{id}', [ReportToController::class, 'Quotation']); //ใบเสนอราคา
Route::get('/report/exportPDF/billservice/{id}', [ReportToController::class, 'receipt']); // *ใบเสร็จ
Route::get('/report/exportPDF/receipt/{id}', [ReportToController::class, 'Voucher']); //ใบสำคัญรับเงิน
Route::get('/report/exportPDF/billtax_invioce/{id}', [ReportToController::class, 'billtax_invioce']); //ใบกำกับภาษี
Route::get('/report/exportPDF/allreceipt/{id}', [ReportToController::class, 'AllVoucher']); //รวมใบสำคัญรับเงิน

Route::get('/report/exportPDF/certificate/{id}', [ReportToController::class, 'certificatePDF']); //ใบส่งมอบรถ
Route::get('/report/exportPDF/receiptcar/{id}', [ReportToController::class, 'receiptcarPDF']); //ใบส่งมอบรถ
Route::get('/report/exportPDF/billing_report', [ReportToController::class, 'billing_report']); // รายงานบิลรายบุคคล
Route::get('/report/exportPDF/calprice_billing_report', [ReportToController::class, 'cal_price_billing_report']); // รายงานบิลรายบุคคล
Route::get('report/exportPDF/tax_report_tran', [ReportToController::class, 'tax_report']);
Route::get('/report/exportPDF/receiptPDF/{id}', [ReportToController::class, 'receiptPDF']);//ใบเสร็จรับเงิน

//Commissions
Route::post('/commission_cal', [CommissionController::class, 'commission_cal']);
Route::post('/commission/add', [CommissionController::class, 'store'])->middleware('auth:api');
Route::get('/commission/count_com', [CommissionController::class, 'count_commissions']);
Route::post('/commission/commiss', [CommissionController::class, 'Getpage_employ_commiss']);

//Report Excel
Route::get('/report/excel/carcheck', [ReportController::class, 'downloadExcelcar_check']);
Route::get('/report/excel/cartax', [ReportController::class, 'downloadExcelcar_tax']);
Route::get('/report/excel/commissionEmployee', [ReportController::class, 'downloadExcelcommission_employee']);
Route::get('/report/excel/dailyreport', [ReportController::class, 'downloadExceldaily_report']);
Route::get('/report/excel/expense', [ReportController::class, 'downloadExcelexpense']);
Route::get('/report/excel/expensereport', [ReportController::class, 'downloadExcelexpensereport']);
Route::get('/report/excel/GasCheckListExport', [ReportController::class, 'downloadExcelgas_check_list']);
Route::get('/report/excel/gas_report', [ReportController::class, 'downloadExcelgas_reportExport']);
Route::get('/report/excel/gas1_report', [ReportController::class, 'downloadExportExcelgas_report']);
Route::get('/report/excel/gascheck', [ReportController::class, 'downloadExcelgas_check']);
Route::get('/report/excel/insurance', [ReportController::class, 'downloadExcelInsuranceExport']);
Route::get('/report/excel/insurancereport', [ReportController::class, 'downloadExcelInsuranceReportExport']);
Route::get('/report/excel/bill_manage_list', [InspectionsController::class, 'exportExcelBillManageList']);
Route::get('/report/reportExportPDFbill_manage_list', [InspectionsController::class, 'exportPDFBillManageList']);
Route::get('/report/excel/day', [ReportController::class, 'downloadExcelDay']);
Route::get('/report/excel/month', [ReportController::class, 'downloadExcelmonth']);
Route::get('/report/excel/profit', [ReportController::class, 'downloadExcelProfitReportExport']);
Route::get('/report/excel/reportcartax', [ReportController::class, 'downloadExcelReportCarTaxExport']);
Route::get('/report/excel/exportExcel_reportcost', [ReportController::class, 'exportExcel_reportcost']);
Route::get('/report/excel/exportExcel_customerreport', [ReportController::class, 'exportExcel_customerreport']);
Route::get('/report/excel/exportExcel_carreport', [ReportController::class, 'exportExcel_carreport']);
Route::get('/report/excel/exportExcel_sparepartreport', [ReportController::class, 'exportExcel_sparepartreport']);

Route::get('/report/excel/cartaxbystatus', [ReportController::class, 'exportExcel_tax_by_status']);

//installment
Route::get('/installment/showall', [InstallmentController::class, 'index']);
Route::get('/installment/showID/{id}', [InstallmentController::class, 'showID']);
Route::get('/installment/showID_by_inspection/{id}', [InstallmentController::class, 'showID_by_inspection']);
Route::get('/installment/getPaymentInfo/{id}', [InstallmentController::class, 'getPaymentInfo']);
Route::post('/installment/add', [InstallmentController::class, 'store'])->middleware('auth:api');
Route::put('/installment/update/{id}', [InstallmentController::class, 'update'])->middleware('auth:api');
Route::delete('/installment/delete/{id}', [InstallmentController::class, 'destroy'])->middleware('auth:api');
Route::post('/installment/invoice', [InstallmentController::class, 'invoice']);
Route::post('/installment/update_session/{id}', [InstallmentController::class, 'update_']);

//save_doc
Route::get('/save_doc/showall', [SaveDocumentController::class, 'index']);
Route::post('/save_doc/add', [SaveDocumentController::class, 'store'])->middleware('auth:api');
Route::post('/save_doc/update/{id}', [SaveDocumentController::class, 'update'])->middleware('auth:api');
Route::delete('/save_doc/delete/{id}', [SaveDocumentController::class, 'destroy'])->middleware('auth:api');

//report_pdf getpage
Route::post('/Getpage/GetpageCheck_car_report', [ReportController::class, 'GetpageCheck_car_report']);
Route::post('/Getpage/Car_tax', [ReportController::class, 'GetpageCar_tax']);
Route::post('/Getpage/check_gas', [ReportController::class, 'Getpagecheck_gas2']);
Route::post('/Getpage/EMS', [ReportController::class, 'GetPageEMS']);
Route::post('/Getpage/PrbRenewal', [ReportController::class, 'GetPage_PrbRenewals']);
Route::post('/Getpage/InsurancesRenewals', [ReportController::class, 'GetPage_InsurancesRenewals']);

Route::post('/Getpage/notifications', [ReportController::class, 'Getpage_Extent_customer']);
Route::post('/Getpage/Expense', [ReportController::class, 'GetpageExpense']);
Route::post('/Getpage/Daily_report', [ReportController::class, 'GetpageDaily_report']);
route::post('/Getpage/GetpageInsurance_report', [ReportController::class, 'GetpageInsurance_report']);
route::post('/Getpage/GetpageInsurance', [ReportController::class, 'GetpageInsurance']);
route::post('/Getpage/GetpageExpense_report', [ReportController::class, 'Getpage_Expense_report']);
Route::post('/Getpage/GetpageList_check_gas', [ReportController::class, 'GetpageList_check_gas']);
Route::post('/Getpage/GetpageCommission_employee', [ReportController::class, 'GetpageCommission_employee']);
Route::post('/Getpage/GetpageProfit_report', [ReportController::class, 'GetpageProfit_report']);
Route::post('/Getpage/reportExportPDF_gas_report_getpage', [ReportController::class, 'reportExportPDF_gas_report_getpage']);
Route::post('/Getpage/daily_report_getpage', [ReportController::class, 'daily_report_getpage']);

Route::get('Getreport/report_aday_cal', [ReportController::class, 'report_aday_cal']);

Route::post('/Getpage/work_order', [WorkOrderController::class, 'getPage']);
Route::put('/update/status/workorder/{id}', [WorkOrderController::class, 'updateStatus']);

//item
Route::post('/Getpage/item', [ItemController::class, 'getPage']);
Route::get('/item/showall', [ItemController::class, 'index']);
Route::get('/item/showID/{id}', [ItemController::class, 'showID']);
Route::post('/item/update/{id}', [ItemController::class, 'update'])->middleware('auth:api');
Route::post('/item/add', [ItemController::class, 'store'])->middleware('auth:api');
Route::delete('/item/delete/{id}', [ItemController::class, 'destroy'])->middleware('auth:api');

//line_item
Route::post('/Getpage/line_item', [LineItemController::class, 'getPage']);
Route::get('/line_item/showall', [LineItemController::class, 'index']);
Route::get('/line_item/showID/{id}', [LineItemController::class, 'showID']);

//RepaireService
Route::get('/repaireservice/showall', [RepairServiceController::class, 'index']);
Route::post('/repaireservice/add', [RepairServiceController::class, 'store'])->middleware('auth:api');
Route::post('/repaireservice/update/{id}', [RepairServiceController::class, 'update'])->middleware('auth:api');
Route::delete('/repaireservice/delete/{id}', [RepairServiceController::class, 'destroy'])->middleware('auth:api');
Route::post('/repaireservice/getPage', [RepairServiceController::class, 'getPage']);
Route::post('/line_item/add', [LineItemController::class, 'store'])->middleware('auth:api');
Route::post('/line_item/update/{id}', [LineItemController::class, 'update'])->middleware('auth:api');
Route::delete('/line_item/delete/{id}', [LineItemController::class, 'destroy'])->middleware('auth:api');
Route::put('/line_item/update_operation/{id}', [LineItemController::class, 'update_operation'])->middleware('auth:api');

//work_order
// Route::post('/Getpage/work_order/vehicles',[WorkOrderController::class,'getPage_vehicles']);
// Route::post('/Getpage/work_order/customer',[WorkOrderController::class,'getPage_Customer']);

//License
Route::get('/license/{id}', [ReportController::class, 'license']);
Route::get('/license_image/{id}', [ReportController::class, 'license_image']);

//VehicleGas
Route::get('/vehiclegas/showall', [VehicleGasController::class, 'index']);
Route::post('/vehiclegas/add', [VehicleGasController::class, 'store'])->middleware('auth:api');
Route::post('/vehiclegas/update/{id}', [VehicleGasController::class, 'update'])->middleware('auth:api');
Route::delete('/vehiclegas/delete/{id}', [VehicleGasController::class, 'destroy'])->middleware('auth:api');
Route::post('/vehiclegas/getPage', [VehicleGasController::class, 'getPage']);
Route::get('/vehiclegas/showallID/{id}', [VehicleGasController::class, 'showallID']);

//insurance_name
Route::get('/insurance_name/showall', [InsuranceNameController::class, 'index']);
Route::get('/insurance_name/showID/{id}', [InsuranceNameController::class, 'showID']);
Route::post('/insurance_name/add', [InsuranceNameController::class, 'store'])->middleware('auth:api');
Route::post('insurance_name/update/{id}', [InsuranceNameController::class, 'update'])->middleware('auth:api');
Route::delete('/insurance_name/delete/{id}', [InsuranceNameController::class, 'destroy'])->middleware('auth:api');
Route::post('/insurance_name/getPage', [InsuranceNameController::class, 'getPage']);

//insurance_link
Route::get('/insurance_collect/showall', [InsuranceLinkPhoneController::class, 'index']);
Route::get('/insurance_collect/showID/{id}', [InsuranceLinkPhoneController::class, 'showID']);
Route::post('/insurance_collect/add', [InsuranceLinkPhoneController::class, 'store'])->middleware('auth:api');
Route::post('/insurance_collect/update/{id}', [InsuranceLinkPhoneController::class, 'update'])->middleware('auth:api');
Route::delete('/insurance_collect/delete/{id}', [InsuranceLinkPhoneController::class, 'destroy'])->middleware('auth:api');

//cost
Route::get('/cost/showall', [CostController::class, 'index']);
Route::post('/cost/add', [CostController::class, 'store'])->middleware('auth:api');
Route::post('/cost/update/{id}', [CostController::class, 'update'])->middleware('auth:api');
Route::delete('/cost/delete/{id}', [CostController::class, 'destroy'])->middleware('auth:api');
Route::get('/cost/showID/{id}', [CostController::class, 'showID']);

//doc
Route::get('/docs/show', [DocController::class, 'index']);
Route::get('/docs/showID/{id}', [DocController::class, 'showID']);
Route::post('/docs/add', [DocController::class, 'store'])->middleware('auth:api');
Route::post('/docs/update/{id}', [DocController::class, 'update'])->middleware('auth:api');
Route::delete('/docs/delete/{id}', [DocController::class, 'destroy'])->middleware('auth:api');
Route::post('/docs/getPage', [DocController::class, 'getPage']);

//doc_transactions
Route::get('/doc_transactions/show', [DocTransactionController::class, 'index']);
Route::get('/doc_transactions/showID/{id}', [DocTransactionController::class, 'showID']);
Route::post('/doc_transactions/report_branch', [DocTransactionController::class, 'report_branch']);
Route::post('/doc_transactions/add', [DocTransactionController::class, 'store'])->middleware('auth:api');
Route::post('/doc_transactions/update/{id}', [DocTransactionController::class, 'update'])->middleware('auth:api');
Route::delete('/doc_transactions/delete/{id}', [DocTransactionController::class, 'destroy'])->middleware('auth:api');
Route::post('/doc_transactions/getPage', [DocTransactionController::class, 'getPage']);

//document_type
Route::get('/document_types/show', [DocumentTypeController::class, 'index']);
Route::get('/document_types/showID/{id}', [DocumentTypeController::class, 'showID']);
Route::post('/document_types/add', [DocumentTypeController::class, 'store'])->middleware('auth:api');
Route::post('/document_types/update/{id}', [DocumentTypeController::class, 'update'])->middleware('auth:api');
Route::delete('/document_types/delete/{id}', [DocumentTypeController::class, 'destroy'])->middleware('auth:api');
Route::post('/document_types/getPage', [DocumentTypeController::class, 'getPage']);

//doc_customer
Route::get('/doc_customer/show', [DocCustomerController::class, 'index']);
Route::get('/doc_customer/showID/{id}', [DocCustomerController::class, 'showID']);
Route::get('/doc_customer/by_customer/{id}', [DocCustomerController::class, 'indexby_customer']);
Route::post('/doc_customer/add', [DocCustomerController::class, 'store'])->middleware('auth:api');
Route::post('/doc_customer/update/{id}', [DocCustomerController::class, 'update'])->middleware('auth:api');
Route::delete('/doc_customer/delete/{id}', [DocCustomerController::class, 'destroy'])->middleware('auth:api');
Route::post('/doc_customer/getPage', [DocCustomerController::class, 'getPage']);

//doctype_service
Route::get('/doctype_service/show', [ServiceDocTypeController::class, 'index']);
Route::get('/doctype_service/showID/{id}', [ServiceDocTypeController::class, 'showID']);
Route::post('/doctype_service/add', [ServiceDocTypeController::class, 'store'])->middleware('auth:api');
Route::post('/doctype_service/update/{id}', [ServiceDocTypeController::class, 'update'])->middleware('auth:api');
Route::delete('/doctype_service/delete/{id}', [ServiceDocTypeController::class, 'destroy'])->middleware('auth:api');
Route::post('/doctype_service/getPage', [ServiceDocTypeController::class, 'getPage']);

//Addresses ems
Route::get('/addressems/show', [AddressEmsController::class, 'index']);
Route::get('/addressems/showID/{id}', [AddressEmsController::class, 'showID']);
Route::delete('/addressems/delete/{id}', [AddressEmsController::class, 'destroy'])->middleware('auth:api');
Route::post('/addressems/add', [AddressEmsController::class, 'store'])->middleware('auth:api');
Route::post('/addressems/update/{id}', [AddressEmsController::class, 'update'])->middleware('auth:api');
Route::post('/addressems/getPage', [AddressEmsController::class, 'getPage']);

// *Update status paid
Route::put('/update/status/inspection/discount/{id}', [InspectionsController::class, 'updateStatus_discount'])->middleware('auth:api');
Route::put('/update/status/inspecions/tax/{id}', [InspectionsController::class, 'update_vehicles_status']);
Route::put('/update/status/inspecions/type_document/{id}', [InspectionsController::class, 'update_vehicles_type_document']);
Route::put('/update/status/inspecions/paytrue/{id}', [InspectionsController::class, 'update_vehicles_paytrue']);
Route::put('/update/status/inspecions/tax', [InspectionsController::class, 'update_vehicles_status_array']);
Route::put('/update/status/inspecions/{id}', [InspectionsController::class, 'updateStatus']);
// *Update gas
Route::post('/update/inspecions/gas/{id}', [InspectionsController::class, 'update_gas2']);
Route::post('/add/inspecions/gas', [InspectionsController::class, 'addgas_agent']);
Route::get('/vehicles_gas_by_checkgas_id/{id}', [InspectionsController::class, 'vehicles_gas_by_checkgas_id']);

// *update policy number
Route::put('/update/insurancesrenewals/{id}', [InsurancesRenewalsController::class, 'update_policy_number']);
Route::put('/update/prbrenewals/{id}', [PrbRenewalsController::class, 'update_policy_number']);

Route::post('/Getpage/log', [AuthController::class, 'getPage']);

//UpdateInspection_Result
Route::put('/update/inspection_result/{id}', [InspectionsController::class, 'updateInspection_Result']);

//InsuranceRenewalTypes
Route::get('/InsuranceRenewalTypes/showall', [InsuranceRenewalTypesController::class, 'index']);
Route::get('/InsuranceRenewalTypes/showID/{id}', [InsuranceRenewalTypesController::class, 'showID']);
Route::post('/InsuranceRenewalTypes/add', [InsuranceRenewalTypesController::class, 'store'])->middleware('auth:api');
Route::post('/InsuranceRenewalTypes/update/{id}', [InsuranceRenewalTypesController::class, 'update'])->middleware('auth:api');
Route::delete('/InsuranceRenewalTypes/del/{id}', [InsuranceRenewalTypesController::class, 'destroy'])->middleware('auth:api');
Route::post('/InsuranceRenewalTypes/getPage', [InsuranceRenewalTypesController::class, 'getPage']);

//quotation
Route::get('/quotation/show', [QuotationController::class, 'index']);
Route::get('/quotation/showID/{id}', [QuotationController::class, 'showID']);
Route::post('/quotation/add', [QuotationController::class, 'store'])->middleware('auth:api');
Route::post('/quotation/update/{id}', [QuotationController::class, 'update'])->middleware('auth:api');
Route::delete('/quotation/delete/{id}', [QuotationController::class, 'destroy'])->middleware('auth:api');
Route::post('/quotation/getPage', [QuotationController::class, 'getPage']);
Route::put('/quotation/update_status/{id}', [QuotationController::class, 'update_status']);
Route::get('/quotation/report/{id}', [QuotationController::class, 'pdf']); // *ใบเสนอราคา

//invoice
Route::get('/invoice/show', [InvoiceController::class, 'index']);
Route::get('/invoice/showAllpaid', [InvoiceController::class, 'index_paid']);
Route::get('/invoice/show_invoice_paid', [InvoiceController::class, 'index_invoice']);
Route::get('/invoice/showby', [InvoiceController::class, 'indexby']);
Route::get('/invoice/show_for_billing', [BillingController::class, 'index_for_billing']);
Route::get('/invoice/showID/{id}', [InvoiceController::class, 'showID']);
Route::post('/invoice/add', [InvoiceController::class, 'store'])->middleware('auth:api');
Route::post('/invoice/update/{id}', [InvoiceController::class, 'update'])->middleware('auth:api');
Route::delete('/invoice/delete/{id}', [InvoiceController::class, 'destroy'])->middleware('auth:api');
Route::post('/invoice/getPage', [InvoiceController::class, 'getPage']);
Route::put('/invoice/update_status/{id}', [InvoiceController::class, 'update_status']);
Route::get('/invoice/report/{id}', [InvoiceController::class, 'pdf']); // *ใบแจ้งหนี้

//invoicepayment
Route::get('/invoicepayment/show', [InvoicePaymentController::class, 'index']);
Route::get('/invoicepayment/showID/{id}', [InvoicePaymentController::class, 'showID']);
Route::post('/invoicepayment/add', [InvoicePaymentController::class, 'store'])->middleware('auth:api');
Route::post('/invoicepayment/update/{id}', [InvoicePaymentController::class, 'update'])->middleware('auth:api');
Route::delete('/invoicepayment/delete/{id}', [InvoicePaymentController::class, 'destroy'])->middleware('auth:api');

//WorkOrderPayment
Route::get('/workorderpayment/show', [WorkOrderPaymentController::class, 'index']);
Route::get('/workorderpayment/showID/{id}', [WorkOrderPaymentController::class, 'showID']);
Route::post('/workorderpayment/add', [WorkOrderPaymentController::class, 'store'])->middleware('auth:api');
Route::post('/workorderpayment/update/{id}', [WorkOrderPaymentController::class, 'update'])->middleware('auth:api');
Route::delete('/workorderpayment/delete/{id}', [WorkOrderPaymentController::class, 'destroy'])->middleware('auth:api');

//billing
Route::get('/billing/show', [BillingController::class, 'index']);
Route::get('/billing/showID/{id}', [BillingController::class, 'show']);
Route::post('/billing/add', [BillingController::class, 'store'])->middleware('auth:api');
Route::post('/billing/update/{id}', [BillingController::class, 'update'])->middleware('auth:api');
Route::delete('/billing/delete/{id}', [BillingController::class, 'destroy'])->middleware('auth:api');
Route::post('/billing/getPage', [BillingController::class, 'getPage']);
Route::put('/billing/update_status/{id}', [BillingController::class, 'update_status']);
Route::get('/billing/report/{id}', [BillingController::class, 'pdf']); // *ใบวางบิล

//taxinvoice
Route::get('/taxinvoice/show', [TaxInvoiceController::class, 'index']);
Route::get('/taxinvoice/showID/{id}', [TaxInvoiceController::class, 'showID']);
Route::post('/taxinvoice/add', [TaxInvoiceController::class, 'store'])->middleware('auth:api');
Route::post('/taxinvoice/store_by_invoice', [TaxInvoiceController::class, 'store_by_invoice'])->middleware('auth:api');
Route::post('/taxinvoice/store_by_work_order', [TaxInvoiceController::class, 'store_by_work_order'])->middleware('auth:api');
Route::post('/taxinvoice/update/{id}', [TaxInvoiceController::class, 'update'])->middleware('auth:api');
Route::delete('/taxinvoice/delete/{id}', [TaxInvoiceController::class, 'destroy'])->middleware('auth:api');
Route::post('/taxinvoice/getPage', [TaxInvoiceController::class, 'getPage_all']);
Route::get('/report/excel/taxinvoice_list', [TaxInvoiceController::class, 'exportExcelTaxinvoiceList']);
Route::get('/report/reportExportPDFtaxinvoice_list', [TaxInvoiceController::class, 'exportPDFTaxinvoiceList']);
Route::put('/taxinvoice/update_status/{id}', [TaxInvoiceController::class, 'update_status']);
Route::get('/taxinvoice/report/{id}', [TaxInvoiceController::class, 'pdf']); // *ใบกำกับภาษีlo

Route::post('/addworkorder', [WorkOrderController::class, 'AddWorkOrder'])->middleware('auth:api');
Route::put('/updateworkorder/{id}', [WorkOrderController::class, 'UpdateWorkOrder'])->middleware('auth:api');
Route::delete('/deleteworkorder/{id}', [WorkOrderController::class, 'DeleteWorkOrder']);

//setting
Route::get('/setting/show', [SettingController::class, 'index']);
Route::get('/setting/showID/{id}', [SettingController::class, 'showID']);
Route::delete('/setting/delete/{id}', [SettingController::class, 'destroy'])->middleware('auth:api');
Route::post('/setting/add', [SettingController::class, 'store'])->middleware('auth:api');
Route::post('/setting/update/{id}', [SettingController::class, 'update'])->middleware('auth:api');
Route::post('/setting/getPage', [SettingController::class, 'getPage']);

//report
Route::get('/report/reportdaily', [ReportController::class, 'reportdaily']);
Route::get('/report/query_income', [ReportController::class, 'query_income']);
Route::get('/report/query_income_service', [ReportController::class, 'query_income_service']);
Route::get('/report/query_income_byservice', [ReportController::class, 'query_income_byservice']);

//report_Prb_PDF
Route::get('/report/exportPDF/Prb_report', [ReportToController::class, 'Prb_report']);
Route::get('/report/excel/Prb_report', [ReportToController::class, 'Prb_report_export_excel']);
Route::get('/report/exportPDF/InsurancesRenewals_report', [ReportToController::class, 'InsurancesRenewals_report']);
Route::get('/report/excel/InsurancesRenewals_report', [ReportToController::class, 'InsurancesRenewals_report_export_excel']);
Route::get('/report/exportPDF/EMS_report', [ReportToController::class, 'EMS_report']);
Route::get('/report/excel/EMS_report', [ReportToController::class, 'EMS_report_export_excel']);
Route::get('/report/gas_report', [ReportController::class, 'gas_report']);
Route::get('/report/remind_report', [ReportToController::class, 'remind_report']);
Route::get('/export/commissPDF',[CommissionController::class,'commissPDF']);
Route::get('/export/commissExcel',[CommissionController::class,'commissExcel']);
